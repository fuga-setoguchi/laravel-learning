@extends('layouts.app')

@section('title', '今日の復習')

@section('content')
    <hgroup>
        <h1>今日の復習</h1>
        <p>
            残り {{ $remaining->total }} 問
            @if ($remaining->practice > 0)
                （うち再出題 {{ $remaining->practice }} 問）
            @endif
        </p>
    </hgroup>

    <x-review-question-source :question="$question" />

    <article>
        @if ($item->mode === App\Enums\ReviewMode::Practice)
            <header><mark>再出題（練習）</mark> <small>結果は段階に影響しません</small></header>
        @endif

        <x-markdown :text="$question->prompt" />

        <form method="POST" action="{{ route('review.answers.store') }}">
            @csrf
            <input type="hidden" name="question_id" value="{{ $question->id }}">
            <input type="hidden" name="mode" value="{{ $item->mode->value }}">
            <input type="hidden" name="last_log_id" value="{{ $item->lastLogId }}">

            @if ($question->type === App\Enums\QuestionType::Recall)
                <p><small>頭の中で答えてから開いてください。</small></p>
                {{-- 評価ボタンは答えの中に置き、答えを開く前に評価させない --}}
                <details>
                    <summary>答えを見る</summary>
                    <x-markdown :text="(string) $question->answer" />
                    <div role="group">
                        <button type="submit" name="rating" value="remembered">思い出せた</button>
                        <button type="submit" name="rating" value="vague" class="secondary">あいまい</button>
                        <button type="submit" name="rating" value="forgot" class="contrast">思い出せなかった</button>
                    </div>
                </details>
            @else
                @if ($question->code !== null)
                    <x-code-block :code="$question->code" :language="$question->code_language ?? 'php'" />
                @endif
                <fieldset>
                    <legend>選択肢</legend>
                    @foreach ($choices as $choice)
                        <label>
                            <input type="radio" name="choice_key" value="{{ $choice->key }}" required>
                            {{ $loop->iteration }}. {{ $choice->body }}
                        </label>
                    @endforeach
                </fieldset>
                <button type="submit">回答する</button>
            @endif
        </form>
    </article>
@endsection
