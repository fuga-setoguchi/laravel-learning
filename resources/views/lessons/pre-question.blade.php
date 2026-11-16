@extends('layouts.app')

@section('title', $lesson->title.'（事前の問い）')

@section('content')
    <x-lesson-header :lesson="$lesson" :step="1" />

    <article>
        <p>読む前に、予想で答えてください（記録はされません）。</p>
        <x-markdown :text="$lesson->pre_question->prompt" />

        {{-- 状態を変えない送信のため GET で本文へ進む（docs/02_architecture.md 3.3） --}}
        <form method="GET" action="{{ route('lessons.read', $lesson) }}">
            <fieldset>
                <legend>選択肢</legend>
                @foreach ($choices as $choice)
                    <label>
                        <input type="radio" name="answer" value="{{ $choice->key }}" required>
                        {{ $loop->iteration }}. {{ $choice->body }}
                    </label>
                @endforeach
            </fieldset>
            <button type="submit">答えて本文へ</button>
        </form>
    </article>
@endsection
