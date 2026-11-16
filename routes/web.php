<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// SCR-01 ホームは、実装の手順 7（docs/02_architecture.md 7章）で HomeController に置き換える
Route::view('/', 'home')->name('home');
