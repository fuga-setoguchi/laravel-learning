<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SourceKind;
use App\Models\Lesson;
use App\Models\LessonSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonSource>
 */
class LessonSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'kind' => SourceKind::Source,
            'position' => 1,
            'title' => 'Service Container',
            'url' => 'https://laravel.com/docs/13.x/container',
        ];
    }

    /** 補足資料（他のバージョンのドキュメント） */
    public function reference(): static
    {
        return $this->state(fn (): array => [
            'kind' => SourceKind::Reference,
            'title' => 'Laravel 11 Release Notes',
            'url' => 'https://laravel.com/docs/11.x/releases',
        ]);
    }
}
