<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md review_states（学習記録）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_states', function (Blueprint $table): void {
            $table->string('question_id', 24)->primary();
            $table->foreign('question_id')->references('id')->on('questions');
            $table->smallInteger('stage');
            $table->date('next_review_on')->index();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->timestamps();
        });

        // スキーマビルダーに CHECK 制約のメソッドがないため、SQLで追加する
        DB::statement('ALTER TABLE review_states ADD CONSTRAINT review_states_stage_check CHECK (stage BETWEEN 1 AND 6)');
    }

    public function down(): void
    {
        Schema::dropIfExists('review_states');
    }
};
