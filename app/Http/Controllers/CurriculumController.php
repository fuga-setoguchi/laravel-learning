<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Chapter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\View\View;

/**
 * SCR-02 カリキュラム（docs/screens/SCR-02_curriculum.md）。
 * 章・レッスン・完了状態・未登録の問題数を、Eager Loading と withCount で取得する（N+1にしない）。
 */
class CurriculumController extends Controller
{
    public function __invoke(): View
    {
        $chapters = Chapter::query()
            ->active()
            ->orderBy('id')
            ->with(['lessons' => static function (HasMany $lessons): void {
                $lessons->active()
                    ->inCurriculumOrder()
                    ->with('progress')
                    // 教材に後から追加され、まだ復習に登録されていない有効な問題の数
                    ->withCount(['questions as unregistered_count' => static function (Builder $questions): void {
                        $questions->whereNull('retired_at')->whereDoesntHave('reviewState');
                    }]);
            }])
            ->get();

        return view('curriculum', ['chapters' => $chapters]);
    }
}
