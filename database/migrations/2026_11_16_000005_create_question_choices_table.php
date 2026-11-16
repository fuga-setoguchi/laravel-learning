<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md question_choices
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_choices', function (Blueprint $table): void {
            $table->id();
            // 教材の子。取り込みで問題単位に作り直すため、問題の削除に追従させる
            $table->string('question_id', 24);
            $table->foreign('question_id')->references('id')->on('questions')->cascadeOnDelete();
            $table->string('key', 1);
            $table->text('body');
            $table->boolean('is_correct');

            $table->unique(['question_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_choices');
    }
};
