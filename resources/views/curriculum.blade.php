@extends('layouts.app')

@section('title', 'カリキュラム')

@section('content')
    <h1>カリキュラム</h1>

    @foreach ($chapters as $chapter)
        @php
            $lessonCount = $chapter->lessons->count();
            $completedCount = $chapter->lessons->filter(fn ($lesson) => $lesson->progress !== null)->count();
        @endphp
        <section>
            <h2>
                第{{ $chapter->id }}章 {{ $chapter->title }}
                <small>
                    @if ($lessonCount === 0)
                        準備中
                    @else
                        {{ $completedCount }} / {{ $lessonCount }}（{{ intdiv($completedCount * 100, $lessonCount) }}%）
                    @endif
                </small>
            </h2>

            @if ($lessonCount > 0)
                <ul>
                    @foreach ($chapter->lessons as $lesson)
                        <li>
                            @if ($lesson->progress !== null)
                                <span aria-hidden="true">✓</span> <small>完了</small>
                            @else
                                <span aria-hidden="true">・</span>
                            @endif
                            <a href="{{ route('lessons.show', $lesson) }}">{{ $lesson->number() }} {{ $lesson->title }}</a>
                            @if ($lesson->progress !== null && $lesson->unregistered_count > 0)
                                <mark>未登録の問題 {{ $lesson->unregistered_count }}</mark>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
@endsection
