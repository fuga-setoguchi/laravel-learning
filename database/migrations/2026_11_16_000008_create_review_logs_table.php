<?php

declare(strict_types=1);

use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md review_logs（学習記録）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('question_id', 24);
            $table->foreign('question_id')->references('id')->on('questions');
            $table->enum('mode', ReviewMode::values());
            $table->enum('result', ReviewResult::values());
            $table->string('choice_key', 1)->nullable();
            $table->smallInteger('stage_before')->nullable();
            $table->smallInteger('stage_after')->nullable();
            $table->timestamp('answered_at')->index();
            $table->date('answered_on');
            $table->timestamps();

            $table->index(['answered_on', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_logs');
    }
};
