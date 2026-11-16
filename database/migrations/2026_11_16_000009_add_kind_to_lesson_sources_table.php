<?php

declare(strict_types=1);

use App\Enums\SourceKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// docs/03_database.md lesson_sources.kind（出典と補足資料を区別する）
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lesson_sources', function (Blueprint $table): void {
            // 既存の行はすべて出典
            $table->enum('kind', SourceKind::values())->default(SourceKind::Source->value);
        });
    }

    public function down(): void
    {
        Schema::table('lesson_sources', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
