@extends('layouts.app')

@section('title', '今日の復習は完了')

@section('content')
    <h1>今日の復習は完了しました</h1>

    @php($total = $grades['pass'] + $grades['hold'] + $grades['fail'])
    @if ($total === 0)
        <p>今日復習する問題はありませんでした。</p>
    @else
        <p>
            今日の回答: {{ $total }} 問
            （正解・思い出せた {{ $grades['pass'] }} / あいまい {{ $grades['hold'] }} / 不正解・思い出せなかった {{ $grades['fail'] }}）
        </p>
    @endif

    <p><a href="{{ route('home') }}" role="button">ホームへ</a></p>
    @if ($nextLesson !== null)
        <p>次のレッスン: <a href="{{ route('lessons.show', $nextLesson) }}">{{ $nextLesson->number() }} {{ $nextLesson->title }}</a></p>
    @endif
@endsection
