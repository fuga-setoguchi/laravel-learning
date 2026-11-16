<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CompleteLesson;
use App\Enums\QuestionType;
use App\Models\Lesson;
use App\Models\Question;
use App\Review\ChoiceShuffler;
use App\ValueObjects\Choice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * SCR-03 レッスン（docs/screens/SCR-03_lesson.md）。
 * 事前の問い → 本文 → その場で思い出す → 完了 を、段階ごとの画面で進める。
 */
class LessonController extends Controller
{
    /** 段階1 事前の問い */
    public function show(Lesson $lesson, ChoiceShuffler $shuffler): View
    {
        $lesson->load('chapter');

        return view('lessons.pre-question', [
            'lesson' => $lesson,
            'choices' => $shuffler->shuffle($lesson->pre_question->choices, $lesson->id, CarbonImmutable::today()),
        ]);
    }

    /** 段階2 本文（?answer= で事前の問いの正誤を表示する） */
    public function read(Request $request, Lesson $lesson): View|RedirectResponse
    {
        $preQuestion = $lesson->pre_question;
        $answer = $request->query('answer');
        $chosen = collect($preQuestion->choices)->first(static fn (Choice $choice): bool => $choice->key === $answer);

        // 事前の問いは回答必須。答えがない・選択肢にない値なら段階1に戻す
        if ($chosen === null) {
            return redirect()->route('lessons.show', $lesson);
        }

        $lesson->load(['chapter', 'sources']);

        return view('lessons.read', [
            'lesson' => $lesson,
            'chosen' => $chosen,
            'correct' => collect($preQuestion->choices)->firstOrFail(static fn (Choice $choice): bool => $choice->key === $preQuestion->answer),
        ]);
    }

    /** 段階3 その場で思い出す（本文・出典は表示しない） */
    public function recall(Lesson $lesson): View
    {
        $lesson->load('chapter');

        return view('lessons.recall', [
            'lesson' => $lesson,
            'cards' => Question::query()
                ->active()
                ->where('lesson_id', $lesson->id)
                ->where('type', QuestionType::Recall)
                ->orderBy('position')
                ->get(),
        ]);
    }

    /** 完了処理（PRG: 完了画面へリダイレクトする） */
    public function complete(Lesson $lesson, CompleteLesson $completeLesson): RedirectResponse
    {
        $result = $completeLesson->handle($lesson, CarbonImmutable::now());

        return redirect()
            ->route('lessons.completed', $lesson)
            ->with('registered_count', $result->registeredCount);
    }

    /** 段階4 完了 */
    public function completed(Lesson $lesson): View|RedirectResponse
    {
        $lesson->load(['chapter', 'progress']);
        if ($lesson->progress === null) {
            return redirect()->route('lessons.show', $lesson);
        }

        $registeredCount = session('registered_count');

        return view('lessons.completed', [
            'lesson' => $lesson,
            // 完了処理の直後だけ件数を表示する（再読み込み・直接アクセスでは表示しない）
            'registeredCount' => is_int($registeredCount) ? $registeredCount : null,
            'nextLesson' => Lesson::nextToStudy()?->load('chapter'),
        ]);
    }
}
