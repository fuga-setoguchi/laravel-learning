<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewStateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 問題ごとの復習状態（学習記録。docs/03_database.md review_states） */
class ReviewState extends Model
{
    /** @use HasFactory<ReviewStateFactory> */
    use HasFactory;

    protected $primaryKey = 'question_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['question_id', 'stage', 'next_review_on', 'last_reviewed_at'];

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
            'stage' => 'integer',
            'next_review_on' => 'immutable_date',
            'last_reviewed_at' => 'immutable_datetime',
        ];
    }
}
