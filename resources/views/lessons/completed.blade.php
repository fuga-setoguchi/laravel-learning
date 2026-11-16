@extends('layouts.app')

@section('title', $lesson->title.'（完了）')

@section('content')
    <x-lesson-header :lesson="$lesson" :step="4" />

    <article>
        <header><strong>レッスンを完了しました</strong></header>
        @if ($registeredCount !== null && $registeredCount > 0)
            <p>{{ $registeredCount }} 問を復習に登録しました。最初の復習は明日です。</p>
        @elseif ($registeredCount === 0)
            <p>このレッスンの問題はすでに復習に登録されています。</p>
        @endif

        <p><a href="{{ route('home') }}" role="button">ホームへ</a></p>
        @if ($nextLesson !== null)
            <p>次のレッスン: <a href="{{ route('lessons.show', $nextLesson) }}">{{ $nextLesson->number() }} {{ $nextLesson->title }}</a></p>
        @endif
    </article>
@endsection
