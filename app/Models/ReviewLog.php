<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use Database\Factories\ReviewLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 回答履歴（学習記録。docs/03_database.md review_logs） */
class ReviewLog extends Model
{
    /** @use HasFactory<ReviewLogFactory> */
    use HasFactory;

    protected $fillable = [
        'question_id',
        'mode',
        'result',
        'choice_key',
        'stage_before',
        'stage_after',
        'answered_at',
        'answered_on',
    ];

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    protected function casts(): array
    {
        return [
            'mode' => ReviewMode::class,
            'result' => ReviewResult::class,
            'stage_before' => 'integer',
            'stage_after' => 'integer',
            'answered_at' => 'immutable_datetime',
            'answered_on' => 'immutable_date',
        ];
    }
}
