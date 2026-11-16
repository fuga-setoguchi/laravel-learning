<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Data\ContentSet;
use App\Content\Data\LessonFile;
use App\Content\Data\LoadedContent;
use App\Content\Data\ParsedChapter;
use App\Content\Data\ParsedLesson;
use App\Content\Data\ParsedQuestion;
use App\Content\Data\ParsedSource;
use App\Enums\QuestionType;
use App\Rules\LaravelDocsUrl;
use App\ValueObjects\Choice;
use App\ValueObjects\PreQuestion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * 教材を検証し、検証済みの ContentSet に変換する（docs/commands/CMD-01_lessons-import.md 3章）。
 * 最初のエラーで止めず、すべてのファイルのエラーを集めてから例外にする。
 */
final class LessonFileValidator
{
    private const CODE_LANGUAGES = ['php', 'blade', 'bash', 'json', 'yaml', 'sql'];

    private const CHOICE_KEYS = ['a', 'b', 'c', 'd', 'e', 'f'];

    /** 表示用のファイルパスごとのエラー */
    private MessageBag $errors;

    public function __construct(private readonly LessonFileParser $parser) {}

    /**
     * @throws ContentValidationException
     */
    public function validate(LoadedContent $content): ContentSet
    {
        $this->errors = new MessageBag;

        $chapters = $this->validateChapters($content);

        /** @var array<string, array{file: LessonFile, data: array<array-key, mixed>}> $validLessons */
        $validLessons = [];
        foreach ($content->lessonFiles as $file) {
            $data = $this->validateLessonFile($file, $chapters);
            if ($data !== null) {
                $validLessons[$file->displayPath] = ['file' => $file, 'data' => $data];
            }
        }

        if ($this->errors->isNotEmpty()) {
            throw new ContentValidationException(array_map(array_values(...), $this->errors->toArray()));
        }

        $lessons = array_values(array_map(
            fn (array $lesson): ParsedLesson => $this->toParsedLesson($lesson['data']),
            $validLessons,
        ));

        return new ContentSet(
            chapters: array_values($chapters ?? []),
            lessons: $lessons,
            warnings: $this->warnings($validLessons),
        );
    }

    /**
     * @return array<int, ParsedChapter>|null 章番号 => 章。chapters.yaml にエラーがあれば null
     */
    private function validateChapters(LoadedContent $content): ?array
    {
        try {
            $data = Yaml::parse($content->chaptersYaml);
        } catch (ParseException $e) {
            $this->addError($content->chaptersDisplayPath, self::text('content.chapters_yaml_invalid', ['reason' => $e->getMessage()]));

            return null;
        }

        $validator = Validator::make(is_array($data) ? $data : [], [
            'chapters' => ['required', 'list', 'min:1'],
            'chapters.*' => ['array'],
            'chapters.*.number' => ['required', 'integer', 'between:1,99', 'distinct'],
            'chapters.*.title' => ['required', 'string', 'max:100'],
        ], $this->messages(''));

        if ($this->collectErrors($content->chaptersDisplayPath, $validator)) {
            return null;
        }

        $chapters = [];
        foreach (TypedArray::listOfArrays($validator->validated(), 'chapters') as $chapter) {
            $number = TypedArray::int($chapter, 'number');
            $chapters[$number] = new ParsedChapter($number, TypedArray::string($chapter, 'title'));
        }

        return $chapters;
    }

    /**
     * @param  array<int, ParsedChapter>|null  $chapters
     * @return array<array-key, mixed>|null 検証済みの front matter と本文（body）。エラーがあれば null
     */
    private function validateLessonFile(LessonFile $file, ?array $chapters): ?array
    {
        try {
            $parsed = $this->parser->parse($file->contents);
        } catch (LessonFileParseException $e) {
            $this->addError($file->displayPath, $e->getMessage());

            return null;
        }

        $data = [...$parsed['frontMatter'], 'body' => $parsed['body']];
        $validator = Validator::make($data, $this->lessonRules($file, $data), $this->messages($file->expectedId));

        $validator->after(function (ValidatorInstance $validator) use ($file, $data, $chapters): void {
            $this->validateFilePosition($validator, $file, $data);

            $chapter = $data['chapter'] ?? null;
            if ($chapters !== null && is_int($chapter) && ! isset($chapters[$chapter])) {
                $validator->errors()->add('chapter', self::text('content.chapter_not_defined', ['chapter' => $chapter]));
            }
        });

        return $this->collectErrors($file->displayPath, $validator) ? null : $data;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, list<mixed>>
     */
    private function lessonRules(LessonFile $file, array $data): array
    {
        $laravelVersion = $data['laravel_version'] ?? null;
        $validVersion = is_string($laravelVersion) && preg_match('/^\d+\.x$/', $laravelVersion) === 1 ? $laravelVersion : null;
        $lessonId = preg_quote($file->expectedId, '/');

        $rules = [
            'id' => ['required', 'string', 'regex:/^ch\d{2}-l\d{2}$/'],
            'chapter' => ['required', 'integer'],
            'order' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:100'],
            'laravel_version' => ['required', 'string', 'regex:/^\d+\.x$/'],
            'body' => ['required', 'string'],

            'sources' => ['required', 'list', 'min:1'],
            'sources.*' => ['array'],
            'sources.*.title' => ['required', 'string', 'max:100'],
            'sources.*.url' => ['required', 'string', new LaravelDocsUrl($validVersion)],

            'pre_question' => ['required', 'array'],
            'pre_question.prompt' => ['required', 'string'],
            ...$this->choiceRules('pre_question', $data['pre_question'] ?? null),
            'pre_question.explanation' => ['required', 'string'],

            'recall_cards' => ['required', 'list', 'min:1'],
            'recall_cards.*' => ['array'],
            'recall_cards.*.id' => ['required', 'string', "regex:/^{$lessonId}-c[1-9]\\d*$/", 'distinct'],
            'recall_cards.*.prompt' => ['required', 'string'],
            'recall_cards.*.answer' => ['required', 'string'],

            'questions' => ['required', 'list', 'min:1'],
            'questions.*' => ['array'],
            'questions.*.id' => ['required', 'string', "regex:/^{$lessonId}-q[1-9]\\d*$/", 'distinct'],
            'questions.*.type' => ['required', 'string', Rule::in([QuestionType::Choice->value, QuestionType::CodeReview->value])],
            'questions.*.prompt' => ['required', 'string'],
            'questions.*.code_language' => ['sometimes', 'string', Rule::in(self::CODE_LANGUAGES)],
            'questions.*.explanation' => ['required', 'string'],
        ];

        // 選択肢の key の重複・正解の key・コードの有無は、問題ごとに規則を作る
        // （questions.*.choices.*.key に distinct を付けると、すべての問題の選択肢をまたいで比べてしまうため）
        $questions = $data['questions'] ?? null;
        foreach (is_array($questions) ? $questions : [] as $index => $question) {
            $question = is_array($question) ? $question : [];
            $rules = [...$rules, ...$this->choiceRules("questions.{$index}", $question)];
            $rules["questions.{$index}.code"] = ($question['type'] ?? null) === QuestionType::CodeReview->value
                ? ['required', 'string']
                : ['prohibited'];
        }

        return $rules;
    }

    /**
     * choices・answer の規則（事前の問い・選択式・AIコードレビュー問題で共通）
     *
     * @return array<string, list<mixed>>
     */
    private function choiceRules(string $prefix, mixed $item): array
    {
        $choices = is_array($item) && is_array($item['choices'] ?? null) ? $item['choices'] : [];
        $keys = array_values(array_filter(
            array_map(static fn (mixed $choice): mixed => is_array($choice) ? ($choice['key'] ?? null) : null, $choices),
            is_string(...),
        ));

        return [
            "{$prefix}.choices" => ['required', 'list', 'between:2,6'],
            "{$prefix}.choices.*" => ['array'],
            "{$prefix}.choices.*.key" => ['required', 'string', Rule::in(self::CHOICE_KEYS), 'distinct'],
            "{$prefix}.choices.*.body" => ['required', 'string'],
            "{$prefix}.answer" => ['required', 'string', Rule::in($keys)],
        ];
    }

    /**
     * id・chapter・order がファイルの位置（chNN/lNN.md）と一致するか
     *
     * @param  array<array-key, mixed>  $data
     */
    private function validateFilePosition(ValidatorInstance $validator, LessonFile $file, array $data): void
    {
        $id = $data['id'] ?? null;
        if (is_string($id) && $id !== $file->expectedId) {
            $validator->errors()->add('id', self::text('content.id_mismatch', ['expected' => $file->expectedId, 'actual' => $id]));
        }

        $chapter = $data['chapter'] ?? null;
        if (is_int($chapter) && $chapter !== $file->chapter) {
            $validator->errors()->add('chapter', self::text('content.chapter_mismatch', ['expected' => $file->chapter, 'actual' => $chapter]));
        }

        $order = $data['order'] ?? null;
        if (is_int($order) && $order !== $file->position) {
            $validator->errors()->add('order', self::text('content.order_mismatch', ['expected' => $file->position, 'actual' => $order]));
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private function toParsedLesson(array $data): ParsedLesson
    {
        $preQuestion = TypedArray::array($data, 'pre_question');
        $position = 0;

        $questions = [];
        foreach (TypedArray::listOfArrays($data, 'recall_cards') as $card) {
            $questions[] = new ParsedQuestion(
                id: TypedArray::string($card, 'id'),
                type: QuestionType::Recall,
                position: ++$position,
                prompt: TypedArray::string($card, 'prompt'),
                code: null,
                codeLanguage: null,
                answer: TypedArray::string($card, 'answer'),
                explanation: null,
                choices: [],
                correctKey: null,
            );
        }

        foreach (TypedArray::listOfArrays($data, 'questions') as $question) {
            $type = QuestionType::from(TypedArray::string($question, 'type'));
            $questions[] = new ParsedQuestion(
                id: TypedArray::string($question, 'id'),
                type: $type,
                position: ++$position,
                prompt: TypedArray::string($question, 'prompt'),
                code: TypedArray::nullableString($question, 'code'),
                codeLanguage: $type === QuestionType::CodeReview ? (TypedArray::nullableString($question, 'code_language') ?? 'php') : null,
                answer: null,
                explanation: TypedArray::string($question, 'explanation'),
                choices: $this->toChoices($question),
                correctKey: TypedArray::string($question, 'answer'),
            );
        }

        return new ParsedLesson(
            id: TypedArray::string($data, 'id'),
            chapter: TypedArray::int($data, 'chapter'),
            position: TypedArray::int($data, 'order'),
            title: TypedArray::string($data, 'title'),
            body: TypedArray::string($data, 'body'),
            laravelVersion: TypedArray::string($data, 'laravel_version'),
            preQuestion: new PreQuestion(
                prompt: TypedArray::string($preQuestion, 'prompt'),
                choices: $this->toChoices($preQuestion),
                answer: TypedArray::string($preQuestion, 'answer'),
                explanation: TypedArray::string($preQuestion, 'explanation'),
            ),
            sources: array_map(
                static fn (array $source): ParsedSource => new ParsedSource(TypedArray::string($source, 'title'), TypedArray::string($source, 'url')),
                TypedArray::listOfArrays($data, 'sources'),
            ),
            questions: $questions,
        );
    }

    /**
     * @param  array<array-key, mixed>  $item
     * @return list<Choice>
     */
    private function toChoices(array $item): array
    {
        return array_map(
            static fn (array $choice): Choice => new Choice(TypedArray::string($choice, 'key'), TypedArray::string($choice, 'body')),
            TypedArray::listOfArrays($item, 'choices'),
        );
    }

    /**
     * エラーではないが、教材生成ガイドの推奨から外れるもの
     *
     * @param  array<string, array{file: LessonFile, data: array<array-key, mixed>}>  $lessons
     * @return list<string>
     */
    private function warnings(array $lessons): array
    {
        $warnings = [];
        $versions = [];

        foreach ($lessons as $displayPath => $lesson) {
            $recallCount = count(TypedArray::listOfArrays($lesson['data'], 'recall_cards'));
            if ($recallCount < 3 || $recallCount > 5) {
                $warnings[] = self::text('content.warnings.recall_cards_count', ['file' => $displayPath, 'count' => $recallCount]);
            }

            $questions = TypedArray::listOfArrays($lesson['data'], 'questions');
            if (count($questions) > 2) {
                $warnings[] = self::text('content.warnings.questions_count', ['file' => $displayPath, 'count' => count($questions)]);
            }

            $types = array_column($questions, 'type');
            if (! in_array(QuestionType::CodeReview->value, $types, true)) {
                $warnings[] = self::text('content.warnings.no_code_review', ['file' => $displayPath]);
            }

            $versions[] = TypedArray::string($lesson['data'], 'laravel_version');
        }

        $versions = array_values(array_unique($versions));
        if (count($versions) > 1) {
            sort($versions);
            $warnings[] = self::text('content.warnings.mixed_versions', ['versions' => implode(', ', $versions)]);
        }

        return $warnings;
    }

    /**
     * @return bool エラーがあれば true
     */
    private function collectErrors(string $displayPath, ValidatorInstance $validator): bool
    {
        if (! $validator->fails()) {
            return false;
        }

        foreach ($validator->errors()->messages() as $path => $messages) {
            foreach ($messages as $message) {
                $this->addError($displayPath, "{$path}: {$message}");
            }
        }

        return true;
    }

    private function addError(string $displayPath, string $message): void
    {
        $this->errors->add($displayPath, $message);
    }

    /**
     * Validator に渡すメッセージ（lang/ja/content.php）
     *
     * @return array<string, string>
     */
    private function messages(string $lessonId): array
    {
        $messages = [];
        foreach (['required', 'string', 'integer', 'array', 'list', 'min', 'max', 'between', 'distinct', 'prohibited', 'in'] as $rule) {
            $messages[$rule] = self::text("content.rules.{$rule}");
        }

        return [
            ...$messages,
            'id.regex' => self::attributeText('id_regex'),
            'laravel_version.regex' => self::attributeText('laravel_version_regex'),
            'pre_question.answer.in' => self::attributeText('answer_in'),
            'questions.*.answer.in' => self::attributeText('answer_in'),
            'pre_question.choices.*.key.in' => self::attributeText('key_in'),
            'questions.*.choices.*.key.in' => self::attributeText('key_in'),
            'questions.*.type.in' => self::attributeText('type_in'),
            'questions.*.code_language.in' => self::attributeText('code_language_in'),
            'recall_cards.*.id.regex' => self::attributeText('recall_card_id_regex', ['lesson' => $lessonId]),
            'questions.*.id.regex' => self::attributeText('question_id_regex', ['lesson' => $lessonId]),
            'chapters.*.number.between' => self::attributeText('number_between'),
        ];
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private static function attributeText(string $key, array $replace = []): string
    {
        return self::text("content.attributes.{$key}", $replace);
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private static function text(string $key, array $replace = []): string
    {
        $text = __($key, $replace);

        return is_string($text) ? $text : $key;
    }
}
