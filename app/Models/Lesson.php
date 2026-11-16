<?php

declare(strict_types=1);

namespace App\Models;

use App\ValueObjects\PreQuestion;
use Database\Factories\LessonFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** レッスン（docs/03_database.md lessons） */
class Lesson extends Model
{
    /** @use HasFactory<LessonFactory> */
    use HasFactory;

    /** 教材の固定id（例: ch03-l02）を主キーにする */
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'chapter_id',
        'position',
        'title',
        'body',
        'laravel_version',
        'pre_question',
        'retired_at',
    ];

    /**
     * @return BelongsTo<Chapter, $this>
     */
    public function chapter(): BelongsTo
    {
        return $this->belongsTo(Chapter::class);
    }

    /**
     * @return HasMany<LessonSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(LessonSource::class)->orderBy('position');
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * @return HasOne<LessonProgress, $this>
     */
    public function progress(): HasOne
    {
        return $this->hasOne(LessonProgress::class);
    }

    /**
     * 引退していないレッスン
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('retired_at');
    }

    protected function casts(): array
    {
        return [
            'pre_question' => PreQuestion::class,
            'retired_at' => 'immutable_datetime',
        ];
    }
}
