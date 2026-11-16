<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md questions
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table): void {
            // 問題の固定id（例: ch03-l02-c1、ch03-l02-q1）
            $table->string('id', 24)->primary();
            $table->string('lesson_id', 16);
            $table->foreign('lesson_id')->references('id')->on('lessons');
            // PostgreSQL では varchar + CHECK 制約になる
            $table->enum('type', QuestionType::values());
            $table->integer('position');
            $table->text('prompt');
            $table->text('code')->nullable();
            $table->string('code_language', 16)->nullable();
            $table->text('answer')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();

            $table->index(['lesson_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
