<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md lesson_sources
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_sources', function (Blueprint $table): void {
            $table->id();
            // 教材の子。取り込みでレッスン単位に作り直すため、レッスンの削除に追従させる
            $table->string('lesson_id', 16);
            $table->foreign('lesson_id')->references('id')->on('lessons')->cascadeOnDelete();
            $table->integer('position');
            $table->string('title');
            $table->string('url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_sources');
    }
};
