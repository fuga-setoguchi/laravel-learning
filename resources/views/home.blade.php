@extends('layouts.app')

@section('content')
    <h1>Laravel学習</h1>

    <article>
        <header><strong>① 今日の復習</strong></header>
        @if ($remaining->total > 0)
            <p>
                残り {{ $remaining->total }} 問
                @if ($remaining->practice > 0)
                    （うち再出題 {{ $remaining->practice }} 問）
                @endif
            </p>
            <p><a href="{{ route('review.show') }}" role="button">復習を始める</a></p>
        @else
            <p>今日の復習は完了しました。</p>
        @endif
    </article>

    <article>
        <header>
            <strong>② 次のレッスン</strong>
            @if ($remaining->total > 0)
                <small>復習を終えてから進めましょう</small>
            @endif
        </header>
        @if ($nextLesson !== null)
            <p>第{{ $nextLesson->chapter->id }}章 {{ $nextLesson->chapter->title }}</p>
            <p>{{ $nextLesson->number() }} {{ $nextLesson->title }}</p>
            <p><a href="{{ route('lessons.show', $nextLesson) }}" role="button" class="secondary">レッスンを始める</a></p>
        @elseif ($activeLessons === 0)
            <p>教材がありません。<code>php artisan lessons:import</code> で取り込んでください。</p>
        @else
            <p>すべてのレッスンを完了しました。</p>
        @endif
    </article>

    <p>
        全体の進捗: {{ $completedLessons }} / {{ $activeLessons }} レッスン完了（{{ $activeLessons > 0 ? intdiv($completedLessons * 100, $activeLessons) : 0 }}%）<br>
        復習に登録済みの問題: {{ $registeredQuestions }} 問
    </p>
@endsection
