<?php

declare(strict_types=1);

namespace App\Content;

use League\CommonMark\Extension\FrontMatter\Data\SymfonyYamlFrontMatterParser;
use League\CommonMark\Extension\FrontMatter\Exception\InvalidFrontMatterException;
use League\CommonMark\Extension\FrontMatter\FrontMatterParser;

/**
 * レッスンファイルを front matter（YAML）と本文に分ける（docs/04_content-format.md 3.1）。
 */
final class LessonFileParser
{
    /**
     * @return array{frontMatter: array<array-key, mixed>, body: string}
     *
     * @throws LessonFileParseException front matter がない・YAMLの構文エラー・連想配列でない場合
     */
    public function parse(string $contents): array
    {
        // CRLF を LF にそろえる（改行コードの違いで結果を変えない）
        $contents = str_replace("\r\n", "\n", $contents);

        try {
            $document = (new FrontMatterParser(new SymfonyYamlFrontMatterParser))->parse($contents);
        } catch (InvalidFrontMatterException $e) {
            throw new LessonFileParseException(__('content.front_matter_invalid', ['reason' => $e->getMessage()]), previous: $e);
        }

        $frontMatter = $document->getFrontMatter();
        if ($frontMatter === null) {
            throw new LessonFileParseException(__('content.front_matter_missing'));
        }
        if (! is_array($frontMatter) || array_is_list($frontMatter)) {
            throw new LessonFileParseException(__('content.front_matter_not_mapping'));
        }

        return [
            'frontMatter' => $frontMatter,
            // 本文の先頭の空行は除く
            'body' => ltrim($document->getContent(), "\n"),
        ];
    }
}
