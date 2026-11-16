@extends('layouts.app')

@section('title', $lesson->title.'（思い出す）')

@section('content')
    <x-lesson-header :lesson="$lesson" :step="3" />

    <p>本文を見ずに、頭の中で答えてから開いてください（ここでの結果は記録されません）。</p>

    @foreach ($cards as $card)
        <article>
            <header><strong>Q{{ $loop->iteration }}.</strong></header>
            <x-markdown :text="$card->prompt" />
            <details>
                <summary>答えを見る</summary>
                <x-markdown :text="(string) $card->answer" />
            </details>
        </article>
    @endforeach

    <form method="POST" action="{{ route('lessons.complete', $lesson) }}">
        @csrf
        <button type="submit">完了して復習に登録する</button>
    </form>
    <p><small>本文に戻る場合は、ブラウザの「戻る」を使ってください。</small></p>
@endsection
