<?php

declare(strict_types=1);

use App\Http\Controllers\CurriculumController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\ReviewController;
use Illuminate\Support\Facades\Route;

// SCR-01 ホーム・SCR-02 カリキュラム
Route::get('/', HomeController::class)->name('home');
Route::get('curriculum', CurriculumController::class)->name('curriculum');

// SCR-03 レッスン（docs/screens/SCR-03_lesson.md）
Route::controller(LessonController::class)->prefix('lessons/{lesson}')->name('lessons.')->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::get('read', 'read')->name('read');
    Route::get('recall', 'recall')->name('recall');
    Route::post('complete', 'complete')->name('complete');
    Route::get('completed', 'completed')->name('completed');
});

// SCR-04 復習（docs/screens/SCR-04_review.md）
Route::controller(ReviewController::class)->prefix('review')->name('review.')->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::post('answers', 'store')->name('answers.store');
    Route::get('answers/{reviewLog}', 'result')->name('answers.show');
});
