<?php

declare(strict_types=1);

use App\Http\Controllers\LessonController;
use Illuminate\Support\Facades\Route;

// SCR-01 ホームは、実装の手順 7（docs/02_architecture.md 7章）で HomeController に置き換える
Route::view('/', 'home')->name('home');

// SCR-03 レッスン（docs/screens/SCR-03_lesson.md）
Route::controller(LessonController::class)->prefix('lessons/{lesson}')->name('lessons.')->group(function (): void {
    Route::get('/', 'show')->name('show');
    Route::get('read', 'read')->name('read');
    Route::get('recall', 'recall')->name('recall');
    Route::post('complete', 'complete')->name('complete');
    Route::get('completed', 'completed')->name('completed');
});
