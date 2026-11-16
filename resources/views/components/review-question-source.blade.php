@props(['question'])

{{-- 出題中の問題がどのレッスンのものか。答えを見に行けてしまうため、レッスンへのリンクにはしない --}}
<p>
    <small>
        第{{ $question->lesson->chapter->id }}章 / {{ $question->lesson->number() }} {{ $question->lesson->title }}
        @if ($question->reviewState !== null)
            / 段階 {{ $question->reviewState->stage }}
        @endif
    </small>
</p>
