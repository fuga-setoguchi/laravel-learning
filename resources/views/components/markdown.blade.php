@props(['text'])

{{-- 教材のMarkdown。HTMLのエスケープと危険なリンクの除去は App\Support\Markdown が行う --}}
<div {{ $attributes }}>{{ App\Support\Markdown::toHtml($text) }}</div>
