<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ChapterFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** 章（docs/03_database.md chapters） */
class Chapter extends Model
{
    /** @use HasFactory<ChapterFactory> */
    use HasFactory;

    /** 章番号をそのまま主キーにする */
    public $incrementing = false;

    protected $fillable = ['id', 'title', 'retired_at'];

    /**
     * @return HasMany<Lesson, $this>
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    /**
     * 引退していない章（chapters.yaml にある章）
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
            'retired_at' => 'immutable_datetime',
        ];
    }
}
