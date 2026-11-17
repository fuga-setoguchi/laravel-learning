@extends('layouts.app')

@section('title', '今日の復習')

@section('content')
    <h1>今日の復習</h1>

    <x-today-review-status :summary="$summary" />

    @if ($summary->status === App\Review\TodayReviewStatus::Completed)
        <p>
            今日の回答: {{ $summary->answeredCount() }} 問
            （正解・思い出せた {{ $summary->grades['pass'] }} / あいまい {{ $summary->grades['hold'] }} / 不正解・思い出せなかった {{ $summary->grades['fail'] }}）
        </p>
    @endif

    <p><a href="{{ route('home') }}" role="button">ホームへ</a></p>
    @if ($nextLesson !== null)
        <p>次のレッスン: <a href="{{ route('lessons.show', $nextLesson) }}">{{ $nextLesson->number() }} {{ $nextLesson->title }}</a></p>
    @endif
@endsection
