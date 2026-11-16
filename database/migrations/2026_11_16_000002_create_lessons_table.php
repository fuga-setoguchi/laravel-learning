<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md lessons
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table): void {
            // 教材の固定id（例: ch03-l02）
            $table->string('id', 16)->primary();
            // chapters.id と同じ integer にする（foreignId は bigint になるため使わない）
            $table->integer('chapter_id');
            $table->foreign('chapter_id')->references('id')->on('chapters');
            $table->integer('position');
            $table->string('title');
            $table->text('body');
            $table->string('laravel_version', 16);
            $table->jsonb('pre_question');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->unique(['chapter_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
