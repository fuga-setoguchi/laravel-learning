@extends('layouts.app')

@section('title', $lesson->title)

@section('content')
    <x-lesson-header :lesson="$lesson" :step="2" />

    <article>
        <header>
            <strong>事前の問い: {{ $chosen->key === $correct->key ? '正解 ✓' : '不正解 ✗' }}</strong>
        </header>
        <p>あなたの答え: {{ $chosen->body }}</p>
        @if ($chosen->key !== $correct->key)
            <p>正解: {{ $correct->body }}</p>
        @endif
        <x-markdown :text="$lesson->pre_question->explanation" />
    </article>

    <x-lesson-sources :lesson="$lesson" />

    <x-markdown :text="$lesson->body" />

    <p><a href="{{ route('lessons.recall', $lesson) }}" role="button">本文を閉じて思い出す</a></p>
@endsection
