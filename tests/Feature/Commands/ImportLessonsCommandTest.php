<?php

declare(strict_types=1);

use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonSource;
use App\Models\Question;
use App\Models\QuestionChoice;
use App\Models\ReviewLog;
use App\Models\ReviewState;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\ContentFixture;

// docs/tests/CMD-01_test.md

beforeEach(function (): void {
    $this->content = ContentFixture::create();
});

afterEach(function (): void {
    $this->content->cleanup();
});

/**
 * @param  array<string, mixed>  $options
 * @return array{code: int, output: string}
 */
function importLessons(string $path, array $options = []): array
{
    $code = Artisan::call('lessons:import', ['--path' => $path, ...$options]);

    return ['code' => $code, 'output' => Artisan::output()];
}

/**
 * 教材の行数（学習記録は含まない）
 *
 * @return array<string, int>
 */
function contentRowCounts(): array
{
    return [
        'chapters' => Chapter::count(),
        'lessons' => Lesson::count(),
        'sources' => LessonSource::count(),
        'questions' => Question::count(),
        'choices' => QuestionChoice::count(),
    ];
}

/** 学習記録をまとめて取り出す（変わっていないことの比較用） */
function learningRecords(): array
{
    return [
        'progress' => LessonProgress::orderBy('lesson_id')->get()->toArray(),
        'states' => ReviewState::orderBy('question_id')->get()->toArray(),
        'logs' => ReviewLog::orderBy('id')->get()->toArray(),
    ];
}

// ---------------------------------------------------------------- 1. 正常

it('CMD-01-T01 空のDBに正常な教材を取り込むと、教材どおりに作られる', function (): void {
    $result = importLessons($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('教材を取り込みました')
        ->toContain('レッスン: 追加 3 / 更新 0 / 変更なし 0 / 引退 0 / 復活 0')
        ->toContain('問題:     追加 15 / 更新 0 / 変更なし 0 / 引退 0 / 復活 0')
        ->not->toContain('WARN');

    // 3レッスン × (想起カード3 + 選択式1 + AIコードレビュー1)。選択肢は 3 + 2 = 5 / レッスン
    expect(contentRowCounts())->toBe(['chapters' => 2, 'lessons' => 3, 'sources' => 3, 'questions' => 15, 'choices' => 15]);

    $lesson = Lesson::with(['sources', 'questions.choices'])->findOrFail('ch01-l02');
    expect($lesson->chapter_id)->toBe(1)
        ->and($lesson->position)->toBe(2)
        ->and($lesson->title)->toBe('リクエストのライフサイクル')
        ->and($lesson->laravel_version)->toBe('13.x')
        ->and($lesson->body)->toStartWith('## 概念')
        ->and($lesson->pre_question->answer)->toBe('b')
        ->and($lesson->sources->firstOrFail()->url)->toBe('https://laravel.com/docs/13.x/lifecycle');

    expect($lesson->questions->pluck('id')->all())->toBe(['ch01-l02-c1', 'ch01-l02-c2', 'ch01-l02-c3', 'ch01-l02-q1', 'ch01-l02-q2'])
        ->and($lesson->questions->pluck('position')->all())->toBe([1, 2, 3, 4, 5]);

    $codeReview = $lesson->questions->firstWhere('id', 'ch01-l02-q2');
    expect($codeReview?->type)->toBe(QuestionType::CodeReview)
        ->and($codeReview?->code_language)->toBe('php')
        ->and($codeReview?->code)->toContain('new PaymentService()')
        ->and($codeReview?->choices->where('is_correct', true)->pluck('key')->all())->toBe(['a']);
});

it('CMD-01-T02 同じ教材を2回取り込むと、2回目は「変更なし」だけになる', function (): void {
    importLessons($this->content->path);
    $before = contentRowCounts();

    $result = importLessons($this->content->path);

    expect($result['output'])->toContain('レッスン: 追加 0 / 更新 0 / 変更なし 3 / 引退 0 / 復活 0')
        ->toContain('問題:     追加 0 / 更新 0 / 変更なし 15 / 引退 0 / 復活 0')
        ->and(contentRowCounts())->toBe($before);
});

it('CMD-01-T03 修正して取り込み直すと反映され、選択肢だけ・出典だけの修正も「更新」に数えられる', function (): void {
    importLessons($this->content->path);

    $this->content
        // 本文だけ
        ->setBody('ch01/l01.md', "\n## 概念\n\n書き換えた本文\n")
        // 出典だけ
        ->editLesson('ch01/l02.md', function (array $lesson): array {
            $lesson['sources'][0]['title'] = 'Lifecycle（改）';

            return $lesson;
        })
        // 問題文と、別の問題の選択肢だけ
        ->editLesson('ch02/l01.md', function (array $lesson): array {
            $lesson['recall_cards'][0]['prompt'] = '書き換えた問い';
            $lesson['questions'][0]['choices'][1]['body'] = '書き換えた選択肢';

            return $lesson;
        });

    $result = importLessons($this->content->path);

    expect($result['output'])->toContain('レッスン: 追加 0 / 更新 2 / 変更なし 1')
        ->toContain('問題:     追加 0 / 更新 2 / 変更なし 13');
    expect(Lesson::findOrFail('ch01-l01')->body)->toContain('書き換えた本文')
        ->and(LessonSource::where('lesson_id', 'ch01-l02')->value('title'))->toBe('Lifecycle（改）')
        ->and(Question::findOrFail('ch02-l01-c1')->prompt)->toBe('書き換えた問い')
        ->and(QuestionChoice::where(['question_id' => 'ch02-l01-q1', 'key' => 'b'])->value('body'))->toBe('書き換えた選択肢');
});

it('CMD-01-T04 学習記録がある状態で取り込み直しても、学習記録は1件も変わらない', function (): void {
    importLessons($this->content->path);
    LessonProgress::factory()->create(['lesson_id' => 'ch01-l01']);
    ReviewState::factory()->stage(3)->create(['question_id' => 'ch01-l01-c1']);
    ReviewLog::factory()->create(['question_id' => 'ch01-l01-c1']);
    $before = learningRecords();

    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['title'] = '書き換えたタイトル';
        $lesson['recall_cards'][0]['answer'] = '書き換えた答え';

        return $lesson;
    });
    importLessons($this->content->path);

    expect(learningRecords())->toBe($before);
});

it('CMD-01-T05/T06 問題を削除すると引退し、戻すと以前の段階のまま復活する', function (): void {
    importLessons($this->content->path);
    ReviewState::factory()->stage(4)->create(['question_id' => 'ch01-l01-c2']);
    $original = $this->content->read('ch01/l01.md');

    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        array_splice($lesson['recall_cards'], 1, 1);

        return $lesson;
    });
    $removed = importLessons($this->content->path);

    expect($removed['output'])->toContain('引退 1 / 復活 0')
        ->and(Question::findOrFail('ch01-l01-c2')->retired_at)->not->toBeNull()
        ->and(ReviewState::findOrFail('ch01-l01-c2')->stage)->toBe(4);

    $this->content->write('ch01/l01.md', $original);
    $restored = importLessons($this->content->path);

    // 戻した問題より後ろの3問は、順番（position）が元に戻るため「更新」になる
    expect($restored['output'])->toContain('問題:     追加 0 / 更新 3 / 変更なし 11 / 引退 0 / 復活 1')
        ->and(Question::findOrFail('ch01-l01-c2')->retired_at)->toBeNull()
        ->and(ReviewState::findOrFail('ch01-l01-c2')->stage)->toBe(4);
});

it('CMD-01-T07/T07b レッスンファイルを削除すると問題ごと引退し、戻すと学習記録を保ったまま復活する', function (): void {
    importLessons($this->content->path);
    LessonProgress::factory()->create(['lesson_id' => 'ch01-l02']);
    ReviewState::factory()->stage(2)->create(['question_id' => 'ch01-l02-q1']);
    $before = learningRecords();
    $original = $this->content->read('ch01/l02.md');

    $this->content->delete('ch01/l02.md');
    $removed = importLessons($this->content->path);

    expect($removed['output'])->toContain('レッスン: 追加 0 / 更新 0 / 変更なし 2 / 引退 1')
        ->toContain('問題:     追加 0 / 更新 0 / 変更なし 10 / 引退 5')
        ->and(Lesson::findOrFail('ch01-l02')->retired_at)->not->toBeNull()
        ->and(Question::where('lesson_id', 'ch01-l02')->whereNull('retired_at')->count())->toBe(0);

    $this->content->write('ch01/l02.md', $original);
    $restored = importLessons($this->content->path);

    expect($restored['output'])->toContain('レッスン: 追加 0 / 更新 0 / 変更なし 2 / 引退 0 / 復活 1')
        ->toContain('問題:     追加 0 / 更新 0 / 変更なし 10 / 引退 0 / 復活 5')
        ->and(Lesson::findOrFail('ch01-l02')->retired_at)->toBeNull()
        ->and(learningRecords())->toBe($before);
});

it('CMD-01-T07c chapters.yaml から章を消すと引退し、戻すと引退が解除される', function (): void {
    importLessons($this->content->path);
    $chapters = $this->content->read('chapters.yaml');

    $this->content->delete('ch02/l01.md')->editChapters(function (array $data): array {
        array_splice($data['chapters'], 1, 1);

        return $data;
    });
    $removed = importLessons($this->content->path);

    expect($removed['code'])->toBe(0)
        ->and($removed['output'])->toContain('章:       追加 0 / 更新 0 / 引退 1 / 復活 0')
        ->and(Chapter::findOrFail(2)->retired_at)->not->toBeNull();

    $this->content->write('chapters.yaml', $chapters);
    $restored = importLessons($this->content->path);

    expect($restored['output'])->toContain('章:       追加 0 / 更新 0 / 引退 0 / 復活 1')
        ->and(Chapter::findOrFail(2)->retired_at)->toBeNull();
});

it('CMD-01-T08 問題の順番を入れ替えると、position が記載順に更新され、idと学習記録は変わらない', function (): void {
    importLessons($this->content->path);
    ReviewState::factory()->stage(3)->create(['question_id' => 'ch01-l01-c3']);
    $before = learningRecords();

    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['recall_cards'] = array_reverse($lesson['recall_cards']);

        return $lesson;
    });
    importLessons($this->content->path);

    expect(Question::where('lesson_id', 'ch01-l01')->orderBy('position')->pluck('id')->all())
        ->toBe(['ch01-l01-c3', 'ch01-l01-c2', 'ch01-l01-c1', 'ch01-l01-q1', 'ch01-l01-q2'])
        ->and(learningRecords())->toBe($before);
});

it('CMD-01-T09 choice を code_review に変更できる', function (): void {
    importLessons($this->content->path);

    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['questions'][0]['type'] = 'code_review';
        $lesson['questions'][0]['code'] = "echo 'hello';\n";

        return $lesson;
    });
    $result = importLessons($this->content->path);

    expect($result['code'])->toBe(0)
        ->and(Question::findOrFail('ch01-l01-q1')->type)->toBe(QuestionType::CodeReview);
});

it('CMD-01-T10 --dry-run は件数を表示するが、DBを変更しない', function (): void {
    $result = importLessons($this->content->path, ['--dry-run' => true]);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('--dry-run のため、DBは変更していません')
        ->toContain('レッスン: 追加 3')
        ->and(contentRowCounts())->toBe(['chapters' => 0, 'lessons' => 0, 'sources' => 0, 'questions' => 0, 'choices' => 0]);
});

it('CMD-01-T11 推奨から外れる教材は、警告を出して取り込む', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        array_splice($lesson['recall_cards'], 2);
        array_splice($lesson['questions'], 1);

        return $lesson;
    });
    $result = importLessons($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('ch01/l01.md: recall_cards が 2 件です（推奨 3〜5 件）')
        ->toContain('ch01/l01.md: AIコードレビュー問題（code_review）がありません')
        ->and(Lesson::count())->toBe(3);
});

it('CMD-01-T12 教材の形式に合わないファイルは無視する', function (): void {
    $this->content->write('README.md', '# 教材')->write('ch01/notes.txt', 'メモ')->write('ch01/draft.md', '下書き');

    expect(importLessons($this->content->path)['code'])->toBe(0)
        ->and(Lesson::count())->toBe(3);
});

it('CMD-01-T13 出典URLにアンカーが付いていても取り込める', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['sources'][0]['url'] = 'https://laravel.com/docs/13.x/container#binding';

        return $lesson;
    });

    expect(importLessons($this->content->path)['code'])->toBe(0);
});

it('CMD-01-T37 レッスンごとに laravel_version が異なると警告を出して取り込む', function (): void {
    $this->content->editLesson('ch02/l01.md', function (array $lesson): array {
        $lesson['laravel_version'] = '12.x';
        $lesson['sources'][0]['url'] = 'https://laravel.com/docs/12.x/configuration';

        return $lesson;
    });
    $result = importLessons($this->content->path);

    expect($result['code'])->toBe(0)
        ->and($result['output'])->toContain('laravel_version がレッスンによって異なります（12.x, 13.x）');
});

// ---------------------------------------------------------------- 2. 検証エラー

/**
 * 教材を書き換えて取り込み、検証エラーになること・DBが変わらないことを確かめる
 *
 * @param  list<string>  $expected  出力に含まれるべき文字列
 */
function expectValidationError(ContentFixture $content, array $expected): void
{
    $result = importLessons($content->path);

    expect($result['code'])->toBe(1)
        ->and($result['output'])->toContain('DBは変更していません');
    foreach ($expected as $text) {
        expect($result['output'])->toContain($text);
    }
    expect(contentRowCounts())->toBe(['chapters' => 0, 'lessons' => 0, 'sources' => 0, 'questions' => 0, 'choices' => 0]);
}

it('CMD-01-T14 front matter がない・YAMLの構文エラーはエラーになる', function (string $contents, string $message): void {
    $this->content->write('ch01/l01.md', $contents);

    expectValidationError($this->content, ['ch01/l01.md', $message]);
})->with([
    'front matter なし' => ["## 本文だけ\n", 'front matter（先頭の --- で囲まれたYAML）がありません'],
    'YAMLの構文エラー' => ["---\nid: [ch01-l01\n---\n本文\n", 'front matter のYAMLを解析できません'],
    '連想配列でない' => ["---\n- a\n- b\n---\n本文\n", '「項目名: 値」の形式で書いてください'],
]);

it('CMD-01-T15 必須項目が欠けているとエラーになる', function (string $path): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($path): array {
        data_forget($lesson, $path);

        return $lesson;
    });

    expectValidationError($this->content, ["{$path}: 必須です。"]);
})->with([
    'id', 'chapter', 'order', 'title', 'laravel_version', 'sources', 'pre_question', 'recall_cards', 'questions',
    'recall_cards.0.id', 'questions.0.id', 'sources.0.url', 'pre_question.answer',
]);

it('CMD-01-T15 本文が空だとエラーになる', function (): void {
    $this->content->setBody('ch01/l01.md', "\n  \n");

    expectValidationError($this->content, ['body: 必須です。']);
});

it('CMD-01-T16 sources が空の配列だとエラーになる', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['sources'] = [];

        return $lesson;
    });

    expectValidationError($this->content, ['sources: 必須です。']);
});

it('CMD-01-T17/T18/T19 出典URLが公式ドキュメントの対象バージョンのページでないとエラーになる', function (string $url, string $message): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($url): array {
        $lesson['sources'][0]['url'] = $url;

        return $lesson;
    });

    expectValidationError($this->content, ["sources.0.url: {$message}"]);
})->with([
    'http' => ['http://laravel.com/docs/13.x/container', 'https://laravel.com/docs/ で始まる'],
    '別ドメイン' => ['https://example.com/docs/13.x/container', 'https://laravel.com/docs/ で始まる'],
    'ドメインの偽装' => ['https://laravel.com.evil.example/docs/13.x/container', 'https://laravel.com/docs/ で始まる'],
    'バージョン不一致' => ['https://laravel.com/docs/12.x/container', 'URLのバージョン（12.x）が laravel_version（13.x）と一致しません'],
    'ページ名なし' => ['https://laravel.com/docs/13.x/', 'ページ名がありません'],
]);

it('CMD-01-T20 laravel_version が「13.x」の形式の文字列でないとエラーになる', function (mixed $version): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($version): array {
        $lesson['laravel_version'] = $version;

        return $lesson;
    });

    expectValidationError($this->content, ['laravel_version:']);
})->with(['小数（引用符なしの 13.0）' => [13.0], '整数' => [13], '形式違い' => ['13']]);

it('CMD-01-T21 id・chapter・order がファイルの位置と一致しないとエラーになる', function (string $key, mixed $value, string $message): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($key, $value): array {
        $lesson[$key] = $value;

        return $lesson;
    });

    expectValidationError($this->content, ["{$key}: {$message}"]);
})->with([
    'id' => ['id', 'ch01-l05', 'ファイルの位置（ch01-l01）と一致しません（ch01-l05）'],
    'chapter' => ['chapter', 2, 'id の章番号（1）と一致しません（2）'],
    'order' => ['order', 3, 'id の順番（1）と一致しません（3）'],
]);

it('CMD-01-T22/T29 問題idの接頭辞が違うとエラーになる', function (string $group, string $id): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($group, $id): array {
        $lesson[$group][0]['id'] = $id;

        return $lesson;
    });

    expectValidationError($this->content, ["{$group}.0.id:"]);
})->with([
    '別のレッスンのid' => ['recall_cards', 'ch01-l02-c1'],
    '想起カードに -q' => ['recall_cards', 'ch01-l01-q9'],
    '想起カードのidを questions に移した' => ['questions', 'ch01-l01-c1'],
]);

it('CMD-01-T23 正解・選択肢の記号・件数が不正だとエラーになる', function (Closure $edit, string $expected): void {
    $this->content->editLesson('ch01/l01.md', $edit);

    expectValidationError($this->content, [$expected]);
})->with([
    'answer が choices にない' => [function (array $lesson): array {
        $lesson['questions'][0]['answer'] = 'e';

        return $lesson;
    }, 'questions.0.answer: choices に存在しない key（e）です。'],
    'key の重複' => [function (array $lesson): array {
        $lesson['pre_question']['choices'][1]['key'] = 'a';

        return $lesson;
    }, 'pre_question.choices.1.key: 値（a）が重複しています。'],
    '選択肢が1件' => [function (array $lesson): array {
        $lesson['questions'][0]['choices'] = [['key' => 'a', 'body' => '1つだけ']];
        $lesson['questions'][0]['answer'] = 'a';

        return $lesson;
    }, 'questions.0.choices: 2〜6 件にしてください。'],
    '選択肢が7件' => [function (array $lesson): array {
        $lesson['questions'][0]['choices'] = array_map(fn (string $key): array => ['key' => $key, 'body' => $key], ['a', 'b', 'c', 'd', 'e', 'f', 'g']);
        $lesson['questions'][0]['answer'] = 'a';

        return $lesson;
    }, 'questions.0.choices: 2〜6 件にしてください。'],
]);

it('CMD-01-T24 code_review に code がない・choice に code があるとエラーになる', function (int $index, ?string $code, string $message): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($index, $code): array {
        $code === null ? data_forget($lesson, "questions.{$index}.code") : $lesson['questions'][$index]['code'] = $code;

        return $lesson;
    });

    expectValidationError($this->content, ["questions.{$index}.code: {$message}"]);
})->with([
    'code_review に code なし' => [1, null, '必須です。'],
    'choice に code あり' => [0, 'echo 1;', 'この項目は書かないでください。'],
]);

it('CMD-01-T25 同じファイルの中で問題idが重複するとエラーになる', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['recall_cards'][1]['id'] = 'ch01-l01-c1';

        return $lesson;
    });

    expectValidationError($this->content, ['recall_cards.1.id: 値（ch01-l01-c1）が重複しています。']);
});

it('CMD-01-T26 chapters.yaml の番号の重複・タイトルの欠落はエラーになる', function (Closure $edit, string $expected): void {
    $this->content->editChapters($edit);

    expectValidationError($this->content, ['chapters.yaml', $expected]);
})->with([
    '番号の重複' => [function (array $data): array {
        $data['chapters'][1]['number'] = 1;

        return $data;
    }, 'chapters.1.number: 値（1）が重複しています。'],
    'タイトルなし' => [function (array $data): array {
        unset($data['chapters'][0]['title']);

        return $data;
    }, 'chapters.0.title: 必須です。'],
]);

it('CMD-01-T27 chapters.yaml にない章のレッスンはエラーになる', function (): void {
    $this->content->editChapters(function (array $data): array {
        array_splice($data['chapters'], 1, 1);

        return $data;
    });

    expectValidationError($this->content, ['ch02/l01.md', 'chapter: chapters.yaml に章 2 がありません。']);
});

it('CMD-01-T28 教材フォルダ・chapters.yaml がないとエラーになる', function (): void {
    $missing = importLessons($this->content->path.'/missing');
    expect($missing['code'])->toBe(1)->and($missing['output'])->toContain('教材フォルダがありません。');

    $this->content->delete('chapters.yaml');
    $noChapters = importLessons($this->content->path);
    expect($noChapters['code'])->toBe(1)->and($noChapters['output'])->toContain('chapters.yaml がありません。');
});

it('CMD-01-T30 2つのファイルのエラーが一度に表示される', function (): void {
    $this->content
        ->editLesson('ch01/l01.md', function (array $lesson): array {
            unset($lesson['title']);

            return $lesson;
        })
        ->editLesson('ch02/l01.md', function (array $lesson): array {
            $lesson['questions'][0]['answer'] = 'z';

            return $lesson;
        });

    expectValidationError($this->content, [
        '教材の検証で 2 件のエラーがありました',
        'ch01/l01.md', 'title: 必須です。',
        'ch02/l01.md', 'questions.0.answer: choices に存在しない key（z）です。',
    ]);
});

it('CMD-01-T31/T35 1ファイルでもエラーがあれば、--dry-run でも、正常なファイルも取り込まれない', function (array $options): void {
    $this->content->editLesson('ch02/l01.md', function (array $lesson): array {
        unset($lesson['title']);

        return $lesson;
    });

    $result = importLessons($this->content->path, $options);

    expect($result['code'])->toBe(1)
        ->and(contentRowCounts())->toBe(['chapters' => 0, 'lessons' => 0, 'sources' => 0, 'questions' => 0, 'choices' => 0]);
})->with(['取り込み' => [[]], '--dry-run' => [['--dry-run' => true]]]);

it('CMD-01-T36 code_language が許可されていない値だとエラーになる', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['questions'][1]['code_language'] = 'ruby';

        return $lesson;
    });

    expectValidationError($this->content, ['questions.1.code_language: php / blade / bash / json / yaml / sql のいずれかにしてください（ruby）。']);
});

// ---------------------------------------------------------------- 補足資料（references）

it('CMD-01-T38 補足資料に他のバージョンのドキュメント・公式ブログを書ける', function (): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['references'] = [
            ['title' => 'Laravel 11 リリースノート', 'url' => 'https://laravel.com/docs/11.x/releases#laravel-11'],
            ['title' => '公式ブログ', 'url' => 'https://blog.laravel.com/laravel-11-now-available'],
            ['title' => '公式ブログ（laravel.com）', 'url' => 'https://laravel.com/blog/laravel-12-is-now-released'],
        ];

        return $lesson;
    });

    expect(importLessons($this->content->path)['code'])->toBe(0);

    $lesson = Lesson::with(['sources', 'references'])->findOrFail('ch01-l01');
    expect($lesson->sources->pluck('url')->all())->toBe(['https://laravel.com/docs/13.x/releases'])
        ->and($lesson->references->pluck('url')->all())->toBe([
            'https://laravel.com/docs/11.x/releases#laravel-11',
            'https://blog.laravel.com/laravel-11-now-available',
            'https://laravel.com/blog/laravel-12-is-now-released',
        ])
        ->and($lesson->references->pluck('position')->all())->toBe([1, 2, 3])
        ->and($lesson->sources->firstOrFail()->position)->toBe(1);
});

it('CMD-01-T39 補足資料は任意で、空の配列でも取り込める', function (): void {
    $this->content->editLesson('ch01/l02.md', function (array $lesson): array {
        $lesson['references'] = [];

        return $lesson;
    });

    expect(importLessons($this->content->path)['code'])->toBe(0)
        ->and(LessonSource::where('kind', 'reference')->count())->toBe(0);
});

it('CMD-01-T40 補足資料のURLが公式の資料でないとエラーになる', function (string $url): void {
    $this->content->editLesson('ch01/l01.md', function (array $lesson) use ($url): array {
        $lesson['references'] = [['title' => '資料', 'url' => $url]];

        return $lesson;
    });

    expectValidationError($this->content, ['references.0.url: Laravel の公式の資料']);
})->with([
    'http' => ['http://laravel.com/docs/11.x/releases'],
    '別ドメイン' => ['https://example.com/laravel-11'],
    'ドメインの偽装' => ['https://laravel.com.evil.example/docs/11.x/releases'],
    'ページ名なし' => ['https://laravel.com/docs/11.x/'],
    'ドキュメント・ブログ以外' => ['https://laravel.com/careers'],
]);

it('CMD-01-T41 補足資料だけを修正すると、レッスンが「更新」に数えられ、出典は変わらない', function (): void {
    importLessons($this->content->path);
    $sourcesBefore = LessonSource::where('kind', 'source')->orderBy('id')->get()->toArray();

    $this->content->editLesson('ch01/l01.md', function (array $lesson): array {
        $lesson['references'] = [['title' => 'Laravel 11 リリースノート', 'url' => 'https://laravel.com/docs/11.x/releases']];

        return $lesson;
    });
    $result = importLessons($this->content->path);

    expect($result['output'])->toContain('レッスン: 追加 0 / 更新 1 / 変更なし 2')
        ->and(LessonSource::where('kind', 'source')->orderBy('id')->get()->toArray())->toBe($sourcesBefore)
        ->and(LessonSource::where('kind', 'reference')->count())->toBe(1);
});
