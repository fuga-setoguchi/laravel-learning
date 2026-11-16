<?php

declare(strict_types=1);

namespace App\Review;

/**
 * 候補の中から次の1問を選ぶ。直前に回答した問題と同じ章は、できる範囲で避ける
 * （交互練習。docs/05_review-logic.md 4.2）。DBに依存しない。
 */
final class NextQuestionPicker
{
    /**
     * @param  list<Candidate>  $candidates  並び順（基本）に並んだ候補
     * @param  int|null  $lastChapter  直前に回答した問題の章（今日の回答がなければ null）
     */
    public function pick(array $candidates, ?int $lastChapter): ?Candidate
    {
        if ($candidates === []) {
            return null;
        }

        if ($lastChapter !== null) {
            foreach ($candidates as $candidate) {
                if ($candidate->chapter !== $lastChapter) {
                    return $candidate;
                }
            }
        }

        // 直前の回答がない、またはすべて同じ章なら先頭
        return $candidates[0];
    }
}
