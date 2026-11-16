<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SourceKind;
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
     * 出典（レッスンの内容の根拠）
     *
     * @return HasMany<LessonSource, $this>
     */
    public function sources(): HasMany
    {
        return $this->hasMany(LessonSource::class)->where('kind', SourceKind::Source)->orderBy('position');
    }

    /**
     * 補足資料（経緯や背景）
     *
     * @return HasMany<LessonSource, $this>
     */
    public function references(): HasMany
    {
        return $this->hasMany(LessonSource::class)->where('kind', SourceKind::Reference)->orderBy('position');
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
     * 次に受講するレッスン: 有効で未完了のうち、章番号・順番が最も前のもの
     */
    public static function nextToStudy(): ?self
    {
        return self::query()->active()->whereDoesntHave('progress')->inCurriculumOrder()->first();
    }

    /**
     * ルートモデル結合（/lessons/{lesson}）では、引退したレッスンを見つからない扱い（404）にする
     */
    public function resolveRouteBinding($value, $field = null): ?self
    {
        return self::query()->active()->where($field ?? $this->getRouteKeyName(), $value)->first();
    }

    /** 画面に出す番号（例: 3-2） */
    public function number(): string
    {
        return "{$this->chapter_id}-{$this->position}";
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

    /**
     * カリキュラムの順（章番号・順番の昇順）
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function inCurriculumOrder(Builder $query): void
    {
        $query->orderBy('chapter_id')->orderBy('position');
    }

    protected function casts(): array
    {
        return [
            'pre_question' => PreQuestion::class,
            'retired_at' => 'immutable_datetime',
        ];
    }
}
