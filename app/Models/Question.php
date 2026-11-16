<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionType;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** 問題（想起カード・選択式・AIコードレビュー問題。docs/03_database.md questions） */
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory;

    /** 問題の固定id（例: ch03-l02-c1）を主キーにする */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'lesson_id',
        'type',
        'position',
        'prompt',
        'code',
        'code_language',
        'answer',
        'explanation',
        'retired_at',
    ];

    /**
     * @return BelongsTo<Lesson, $this>
     */
    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /**
     * @return HasMany<QuestionChoice, $this>
     */
    public function choices(): HasMany
    {
        return $this->hasMany(QuestionChoice::class)->orderBy('key');
    }

    /**
     * @return HasOne<ReviewState, $this>
     */
    public function reviewState(): HasOne
    {
        return $this->hasOne(ReviewState::class);
    }

    /**
     * @return HasMany<ReviewLog, $this>
     */
    public function reviewLogs(): HasMany
    {
        return $this->hasMany(ReviewLog::class);
    }

    /**
     * 問題とレッスンの両方が引退していない問題
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('retired_at')
            ->whereHas('lesson', static function (Builder $lesson): void {
                $lesson->whereNull('retired_at');
            });
    }

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'retired_at' => 'immutable_datetime',
        ];
    }
}
