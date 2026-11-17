<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\RecordReviewAnswer;
use App\Actions\Rejected;
use App\Enums\QuestionType;
use App\Enums\ReviewMode;
use App\Http\Requests\SubmitReviewAnswerRequest;
use App\Models\Lesson;
use App\Models\QuestionChoice;
use App\Models\ReviewLog;
use App\Review\ChoiceShuffler;
use App\Review\ReviewQueue;
use App\Review\ReviewScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * SCR-04 復習（docs/screens/SCR-04_review.md）。
 * 次に出す問題はリクエストのたびに ReviewQueue が決める。
 */
class ReviewController extends Controller
{
    /** 次の問題、またはその日の復習の完了画面 */
    public function show(ReviewQueue $queue, ChoiceShuffler $shuffler): View
    {
        $today = CarbonImmutable::today();
        $item = $queue->next($today);

        if ($item === null) {
            return view('review.done', [
                'summary' => $queue->summary($today),
                'nextLesson' => Lesson::nextToStudy()?->load('chapter'),
            ]);
        }

        return view('review.question', [
            'item' => $item,
            'question' => $item->question,
            'choices' => $shuffler->shuffle(array_values($item->question->choices->all()), $item->question->id, $today),
            'remaining' => $queue->remaining($today),
        ]);
    }

    /** 回答の記録（PRG: 選択式は正誤と解説へ、想起カードは次の問題へ） */
    public function store(SubmitReviewAnswerRequest $request, RecordReviewAnswer $recordAnswer): RedirectResponse
    {
        $result = $recordAnswer->handle($request->toAnswer(), CarbonImmutable::now());

        if ($result instanceof Rejected) {
            return redirect()->route('review.show')->with('status', $result->reason->message());
        }

        $log = $result->log;

        return $log->question()->firstOrFail()->type === QuestionType::Recall
            ? redirect()->route('review.show')
            : redirect()->route('review.answers.show', $log);
    }

    /** 選択式・AIコードレビュー問題の正誤と解説。状態は変えない */
    public function result(ReviewLog $reviewLog, ChoiceShuffler $shuffler): View
    {
        $question = $reviewLog->question()->with(['choices', 'lesson.chapter', 'lesson.sources', 'lesson.references'])->firstOrFail();
        abort_if($question->type === QuestionType::Recall, 404);

        // 次回の復習日は、review_states ではなく回答時点の段階から求める（後で状態が変わっても変わらないように）
        $nextReviewOn = $reviewLog->mode === ReviewMode::Scheduled && $reviewLog->stage_after !== null
            ? $reviewLog->answered_on->addDays(ReviewScheduler::INTERVALS[$reviewLog->stage_after] ?? 0)
            : null;

        return view('review.result', [
            'log' => $reviewLog,
            'question' => $question,
            // 出題時と同じ順（回答した日をシードにする）
            'choices' => $shuffler->shuffle(array_values($question->choices->all()), $question->id, $reviewLog->answered_on),
            'correct' => $question->choices->first(static fn (QuestionChoice $choice): bool => $choice->is_correct),
            'nextReviewOn' => $nextReviewOn,
        ]);
    }
}
