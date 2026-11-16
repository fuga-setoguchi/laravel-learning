<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LessonProgressFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** レッスンの完了状態（学習記録。docs/03_database.md lesson_progress） */
class LessonProgress extends Model
{
    /** @use HasFactory<LessonProgressFactory> */
    use HasFactory;

    /** 規約どおりだと lesson_progresses になるため、テーブル名を指定する */
    protected $table = 'lesson_progress';

    protected $primaryKey = 'lesson_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['lesson_id', 'completed_at'];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    protected function casts(): array
    {
        return [
            'completed_at' => 'immutable_datetime',
        ];
    }
}
