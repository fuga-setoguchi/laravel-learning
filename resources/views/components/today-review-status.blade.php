@props(['summary'])

{{-- 残りが0件のときの今日の復習の状態（docs/05_review-logic.md 4.4） --}}
@switch($summary->status)
    @case(App\Review\TodayReviewStatus::NotStarted)
        <p>まだ復習する問題はありません。レッスンを完了すると、翌日から復習が始まります。</p>
        @break
    @case(App\Review\TodayReviewStatus::Completed)
        <p>今日の復習は完了しました。</p>
        @break
    @case(App\Review\TodayReviewStatus::NothingDue)
        <p>今日復習する問題はありません。</p>
        @break
@endswitch
@if ($summary->status !== App\Review\TodayReviewStatus::InProgress && $summary->nextReviewOn !== null)
    <p>次回の復習: {{ $summary->nextReviewOn->toDateString() }}</p>
@endif
