<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Data\ContentSet;
use App\Content\Data\ParsedChapter;
use App\Content\Data\ParsedLesson;
use App\Content\Data\ParsedQuestion;
use App\Content\Data\ParsedSource;
use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\LessonSource;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\ValueObjects\Choice;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 検証済みの教材をDBの教材テーブルに反映する（docs/commands/CMD-01_lessons-import.md 4章）。
 * 学習記録（lesson_progress・review_states・review_logs）には触れない。
 */
final class ContentImporter
{
    public function import(ContentSet $content, bool $dryRun): ImportResult
    {
        $now = CarbonImmutable::now();
        $result = new ImportResult(new ImportCounts, new ImportCounts, new ImportCounts, $content->warnings, $dryRun);

        // すべて反映するか、まったく反映しないか。--dry-run は反映してから巻き戻し、件数だけを得る
        DB::beginTransaction();
        try {
            $this->importChapters($content, $result->chapters, $now);
            $this->importLessons($content, $result, $now);
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $result;
    }

    private function importChapters(ContentSet $content, ImportCounts $counts, CarbonImmutable $now): void
    {
        foreach ($content->chapters as $parsed) {
            $chapter = Chapter::find($parsed->number) ?? new Chapter(['id' => $parsed->number]);
            $counts->count($this->save($chapter, ['title' => $parsed->title]));
        }

        $counts->retired = Chapter::query()
            ->whereNotIn('id', array_map(static fn (ParsedChapter $chapter): int => $chapter->number, $content->chapters))
            ->whereNull('retired_at')
            ->update(['retired_at' => $now]);
    }

    private function importLessons(ContentSet $content, ImportResult $result, CarbonImmutable $now): void
    {
        $questionIds = [];

        foreach ($content->lessons as $parsed) {
            $lesson = Lesson::find($parsed->id) ?? new Lesson(['id' => $parsed->id]);
            $attributes = [
                'chapter_id' => $parsed->chapter,
                'position' => $parsed->position,
                'title' => $parsed->title,
                'body' => $parsed->body,
                'laravel_version' => $parsed->laravelVersion,
            ];
            // 事前の問いは値で比べ、変わったときだけ設定する。jsonb はキーの順や空白を正規化して保存するため、
            // Eloquent の変更検知（保存されたJSON文字列との比較）では、同じ内容でも変更ありと判定されてしまう
            if (! $lesson->exists || $lesson->pre_question != $parsed->preQuestion) {
                $attributes['pre_question'] = $parsed->preQuestion;
            }
            $status = $this->save($lesson, $attributes);
            $sourcesChanged = $this->syncSources($lesson, $parsed->sources);
            $result->lessons->count($status === ImportStatus::Unchanged && $sourcesChanged ? ImportStatus::Updated : $status);

            foreach ($parsed->questions as $question) {
                $this->importQuestion($parsed, $question, $result->questions);
                $questionIds[] = $question->id;
            }
        }

        // ファイルから消えたレッスン・問題を引退させる（引退したレッスンの問題も、ファイルにないため引退する）
        $result->lessons->retired = Lesson::query()
            ->whereNotIn('id', array_map(static fn (ParsedLesson $lesson): string => $lesson->id, $content->lessons))
            ->whereNull('retired_at')
            ->update(['retired_at' => $now]);

        $result->questions->retired = Question::query()
            ->whereNotIn('id', $questionIds)
            ->whereNull('retired_at')
            ->update(['retired_at' => $now]);
    }

    private function importQuestion(ParsedLesson $lesson, ParsedQuestion $parsed, ImportCounts $counts): void
    {
        $question = Question::find($parsed->id) ?? new Question(['id' => $parsed->id]);
        $status = $this->save($question, [
            'lesson_id' => $lesson->id,
            'type' => $parsed->type,
            'position' => $parsed->position,
            'prompt' => $parsed->prompt,
            'code' => $parsed->code,
            'code_language' => $parsed->codeLanguage,
            'answer' => $parsed->answer,
            'explanation' => $parsed->explanation,
        ]);
        $choicesChanged = $this->syncChoices($question, $parsed);
        $counts->count($status === ImportStatus::Unchanged && $choicesChanged ? ImportStatus::Updated : $status);
    }

    /**
     * 値を設定して保存し、引退していれば解除する。値が変わらなければ更新しない。
     *
     * @param  Chapter|Lesson|Question  $model
     * @param  array<string, mixed>  $attributes
     */
    private function save(Model $model, array $attributes): ImportStatus
    {
        $existed = $model->exists;
        $wasRetired = $existed && $model->getAttribute('retired_at') !== null;

        $model->fill([...$attributes, 'retired_at' => null])->save();

        return match (true) {
            ! $existed => ImportStatus::Added,
            $wasRetired => ImportStatus::Restored,
            $model->wasChanged() => ImportStatus::Updated,
            default => ImportStatus::Unchanged,
        };
    }

    /**
     * 出典をレッスン単位で作り直す。内容が同じなら作り直さない。
     *
     * @param  list<ParsedSource>  $sources
     * @return bool 変更があれば true
     */
    private function syncSources(Lesson $lesson, array $sources): bool
    {
        $current = $lesson->sources()->get()
            ->map(static fn (LessonSource $source): array => [$source->title, $source->url])
            ->all();
        $next = array_map(static fn (ParsedSource $source): array => [$source->title, $source->url], $sources);

        if ($current === $next) {
            return false;
        }

        $lesson->sources()->delete();
        foreach ($sources as $index => $source) {
            $lesson->sources()->create(['position' => $index + 1, 'title' => $source->title, 'url' => $source->url]);
        }

        return true;
    }

    /**
     * 選択肢を問題単位で作り直す。内容が同じなら作り直さない。
     *
     * @return bool 変更があれば true
     */
    private function syncChoices(Question $question, ParsedQuestion $parsed): bool
    {
        $current = $question->choices()->get()
            ->map(static fn (QuestionChoice $choice): array => [$choice->key, $choice->body, $choice->is_correct])
            ->all();
        $next = collect($parsed->choices)
            ->sortBy('key')
            ->map(static fn (Choice $choice): array => [$choice->key, $choice->body, $choice->key === $parsed->correctKey])
            ->values()
            ->all();

        if ($current === $next) {
            return false;
        }

        $question->choices()->delete();
        foreach ($parsed->choices as $choice) {
            $question->choices()->create([
                'key' => $choice->key,
                'body' => $choice->body,
                'is_correct' => $choice->key === $parsed->correctKey,
            ]);
        }

        return true;
    }
}
