<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\QuestionChoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** 選択肢（docs/03_database.md question_choices） */
class QuestionChoice extends Model
{
    /** @use HasFactory<QuestionChoiceFactory> */
    use HasFactory;

    /** 取り込みで作り直す行のため、作成・更新日時を持たない */
    public $timestamps = false;

    protected $fillable = ['question_id', 'key', 'body', 'is_correct'];

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
            'is_correct' => 'boolean',
        ];
    }
}
