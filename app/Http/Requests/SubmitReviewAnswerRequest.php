<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\SubmittedAnswer;
use App\Enums\ReviewMode;
use App\Enums\ReviewResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 復習の回答の形式だけを検証する（docs/screens/SCR-04_review.md 3章）。
 * 「今その問題に回答してよいか」はDBの状態に依存するため、ここではなく RecordReviewAnswer で判定する。
 */
class SubmitReviewAnswerRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'string', 'exists:questions,id'],
            'mode' => ['required', Rule::enum(ReviewMode::class)],
            'last_log_id' => ['required', 'integer', 'min:0'],
            'rating' => ['nullable', Rule::in([ReviewResult::Remembered->value, ReviewResult::Vague->value, ReviewResult::Forgot->value])],
            'choice_key' => ['nullable', Rule::in(['a', 'b', 'c', 'd', 'e', 'f'])],
        ];
    }

    public function toAnswer(): SubmittedAnswer
    {
        return new SubmittedAnswer(
            questionId: $this->string('question_id')->toString(),
            mode: $this->enum('mode', ReviewMode::class) ?? ReviewMode::Scheduled,
            lastLogId: $this->integer('last_log_id'),
            rating: $this->filled('rating') ? $this->string('rating')->toString() : null,
            choiceKey: $this->filled('choice_key') ? $this->string('choice_key')->toString() : null,
        );
    }
}
