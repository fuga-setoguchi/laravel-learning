<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'position' => 1,
            'title' => 'Service Container',
            'url' => 'https://laravel.com/docs/13.x/container',
        ];
    }
}
