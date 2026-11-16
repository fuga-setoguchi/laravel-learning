<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\ReviewLog;
use App\Models\ReviewState;
use Carbon\CarbonImmutable;

/**
 * 復習のテストデータを作る（章・レッスン・問題・復習状態・回答履歴）。
 */
final class ReviewFixture
{
    /** 章番号を指定して、その章のレッスンを1件作る */
    public static function lesson(int $chapter = 1): Lesson
    {
        $chapterModel = Chapter::query()->find($chapter) ?? Chapter::factory()->create(['id' => $chapter]);

        return Lesson::factory()->for($chapterModel)->create();
    }

    /** 想起カード（$type = 'choice' / 'code_review' で選択式・AIコードレビュー問題） */
    public static function question(int $chapter = 1, string $type = 'recall', ?Lesson $lesson = null): Question
    {
        $factory = Question::factory()->for($lesson ?? self::lesson($chapter));

        return match ($type) {
            'choice' => $factory->choice()->create(),
            'code_review' => $factory->codeReview()->create(),
            default => $factory->create(),
        };
    }

    /** 期限が $dueOn の問題（復習に登録済み）を作る */
    public static function due(string $dueOn, int $chapter = 1, string $type = 'recall', int $stage = 1): Question
    {
        $question = self::question($chapter, $type);
        ReviewState::factory()->for($question)->create(['stage' => $stage, 'next_review_on' => $dueOn]);

        return $question;
    }

    /** 回答履歴を1件作る */
    public static function log(Question $question, ReviewResult $result, string $answeredAt, ReviewMode $mode = ReviewMode::Scheduled): ReviewLog
    {
        $factory = ReviewLog::factory()->for($question)->result($result)
            ->answeredAt(CarbonImmutable::parse($answeredAt, 'Asia/Tokyo'));

        return ($mode === ReviewMode::Practice ? $factory->practice() : $factory->scheduled())->create();
    }

    /** 今日 $answeredAt に期限の出題で不正解になった問題（次回の復習日は明日）を作る */
    public static function failedToday(string $answeredAt, int $chapter = 1, string $type = 'recall'): Question
    {
        $question = self::question($chapter, $type);
        $at = CarbonImmutable::parse($answeredAt, 'Asia/Tokyo');
        ReviewState::factory()->for($question)->create(['stage' => 1, 'next_review_on' => $at->addDay()->toDateString()]);
        self::log($question, $type === 'recall' ? ReviewResult::Forgot : ReviewResult::Incorrect, $answeredAt);

        return $question;
    }
}
