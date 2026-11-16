@props(['lesson', 'step'])

{{-- SCR-03 の全段階に共通する見出し（docs/screens/SCR-03_lesson.md 1章） --}}
<hgroup>
    <p>第{{ $lesson->chapter->id }}章 {{ $lesson->chapter->title }} / {{ $lesson->number() }}</p>
    <h1>{{ $lesson->title }}</h1>
</hgroup>
<nav aria-label="レッスンの段階">
    <ol>
        @foreach ([1 => '事前の問い', 2 => '本文', 3 => '思い出す', 4 => '完了'] as $number => $label)
            <li @if ($number === $step) aria-current="step" @endif>
                @if ($number === $step)
                    <strong>{{ $number }} {{ $label }}</strong>
                @else
                    {{ $number }} {{ $label }}
                @endif
            </li>
        @endforeach
    </ol>
</nav>
