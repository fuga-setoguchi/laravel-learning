@props(['lesson'])

{{-- 対象バージョンと出典（docs/04_content-format.md「教材の正確さ」） --}}
<p>
    <small>
        対象: Laravel {{ $lesson->laravel_version }} ／ 出典:
        @foreach ($lesson->sources as $source)
            <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ $source->title }}</a>@if (! $loop->last)、@endif
        @endforeach
    </small>
</p>
