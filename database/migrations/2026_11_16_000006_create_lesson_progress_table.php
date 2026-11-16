<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md lesson_progress（学習記録）
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_progress', function (Blueprint $table): void {
            // 学習記録から教材への参照は、削除を拒否する（教材の削除で記録を巻き込まない）
            $table->string('lesson_id', 16)->primary();
            $table->foreign('lesson_id')->references('id')->on('lessons');
            $table->timestamp('completed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_progress');
    }
};
