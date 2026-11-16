@props(['lesson'])

{{-- 対象バージョンと出典（内容の根拠）、補足資料（経緯や背景）。docs/04_content-format.md 3.2 --}}
<p>
    <small>
        対象: Laravel {{ $lesson->laravel_version }} ／ 出典:
        @foreach ($lesson->sources as $source)
            <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->title }}</a>@if (! $loop->last)、@endif
        @endforeach
        @if ($lesson->references->isNotEmpty())
            <br>参考（経緯・背景）:
            @foreach ($lesson->references as $reference)
                <a href="{{ $reference->url }}" target="_blank" rel="noopener noreferrer">{{ $reference->title }}</a>@if (! $loop->last)、@endif
            @endforeach
        @endif
    </small>
</p>
