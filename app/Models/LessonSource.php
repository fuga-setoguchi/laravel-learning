<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LessonSourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 出典（docs/03_database.md lesson_sources） */
class LessonSource extends Model
{
    /** @use HasFactory<LessonSourceFactory> */
    use HasFactory;

    /** 取り込みで作り直す行のため、作成・更新日時を持たない */
    public $timestamps = false;

    protected $fillable = ['lesson_id', 'position', 'title', 'url'];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
