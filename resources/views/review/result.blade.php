@extends('layouts.app')

@section('title', '復習の結果')

@section('content')
    @php($isCorrect = $log->result === App\Enums\ReviewResult::Correct)

    <h1>{{ $isCorrect ? '正解 ✓' : '不正解 ✗' }}</h1>

    <article>
        <x-markdown :text="$question->prompt" />
        @if ($question->code !== null)
            <x-code-block :code="$question->code" :language="$question->code_language ?? 'php'" />
        @endif

        <ol>
            @foreach ($choices as $choice)
                <li>
                    {{ $choice->body }}
                    @if ($choice->key === $log->choice_key)
                        <strong>← あなたの答え</strong>
                    @endif
                    @if ($correct !== null && $choice->key === $correct->key)
                        <strong>← 正解</strong>
                    @endif
                </li>
            @endforeach
        </ol>

        <h2>解説</h2>
        <x-markdown :text="(string) $question->explanation" />
    </article>

    @if ($nextReviewOn !== null)
        <p>次回の復習: {{ $nextReviewOn->toDateString() }}（段階 {{ $log->stage_after }}）</p>
    @endif
    @if (! $isCorrect)
        <p>不正解の問題は、正解するまで今日の最後に出題します。</p>
    @endif

    <x-lesson-sources :lesson="$question->lesson" />

    <p><a href="{{ route('review.show') }}" role="button">次へ</a></p>
@endsection
