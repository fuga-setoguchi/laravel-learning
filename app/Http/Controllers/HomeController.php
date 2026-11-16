<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Lesson;
use App\Models\Question;
use App\Review\ReviewQueue;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

/**
 * SCR-01 ホーム（docs/screens/SCR-01_home.md）。毎日の入口として「今日の復習 → 新しいレッスン」の順に案内する。
 * 集計はすべて件数によらず一定のクエリ数で行う。
 */
class HomeController extends Controller
{
    public function __invoke(ReviewQueue $queue): View
    {
        $activeLessons = Lesson::query()->active()->count();

        return view('home', [
            'remaining' => $queue->remaining(CarbonImmutable::today()),
            'nextLesson' => Lesson::nextToStudy()?->load('chapter'),
            'activeLessons' => $activeLessons,
            'completedLessons' => Lesson::query()->active()->has('progress')->count(),
            'registeredQuestions' => Question::query()->active()->has('reviewState')->count(),
        ]);
    }
}
