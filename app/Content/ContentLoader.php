<?php

declare(strict_types=1);

namespace App\Content;

use App\Content\Data\LessonFile;
use App\Content\Data\LoadedContent;

/**
 * 教材フォルダから chapters.yaml と chNN/lNN.md を集める。それ以外のファイルは無視する
 * （docs/04_content-format.md 1章）。
 */
final class ContentLoader
{
    private const CHAPTERS_FILE = 'chapters.yaml';

    /**
     * @throws ContentValidationException 教材フォルダ・chapters.yaml がない場合
     */
    public function load(string $path): LoadedContent
    {
        $path = rtrim($path, '/');
        $displayRoot = $this->displayPath($path);

        if (! is_dir($path)) {
            throw ContentValidationException::single($displayRoot, __('content.folder_missing'));
        }

        $chaptersPath = "{$path}/".self::CHAPTERS_FILE;
        if (! is_file($chaptersPath)) {
            throw ContentValidationException::single(
                $this->displayPath($chaptersPath),
                __('content.chapters_missing'),
            );
        }

        $lessonFiles = [];
        foreach ($this->sortedEntries($path) as $chapterDir) {
            if (preg_match('/^ch(\d{2})$/', $chapterDir, $chapterMatch) !== 1 || ! is_dir("{$path}/{$chapterDir}")) {
                continue;
            }

            foreach ($this->sortedEntries("{$path}/{$chapterDir}") as $fileName) {
                $filePath = "{$path}/{$chapterDir}/{$fileName}";
                if (preg_match('/^l(\d{2})\.md$/', $fileName, $lessonMatch) !== 1 || ! is_file($filePath)) {
                    continue;
                }

                $lessonFiles[] = new LessonFile(
                    displayPath: $this->displayPath($filePath),
                    expectedId: sprintf('ch%s-l%s', $chapterMatch[1], $lessonMatch[1]),
                    chapter: (int) $chapterMatch[1],
                    position: (int) $lessonMatch[1],
                    contents: $this->read($filePath),
                );
            }
        }

        return new LoadedContent(
            chaptersDisplayPath: $this->displayPath($chaptersPath),
            chaptersYaml: $this->read($chaptersPath),
            lessonFiles: $lessonFiles,
        );
    }

    /**
     * @return list<string>
     */
    private function sortedEntries(string $directory): array
    {
        $entries = scandir($directory);
        if ($entries === false) {
            return [];
        }

        return array_values(array_filter($entries, static fn (string $entry): bool => ! str_starts_with($entry, '.')));
    }

    private function read(string $filePath): string
    {
        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw ContentValidationException::single($this->displayPath($filePath), __('content.unreadable'));
        }

        return $contents;
    }

    /** エラー表示用に、プロジェクトのルートからの相対パスにする */
    private function displayPath(string $path): string
    {
        $base = base_path().'/';

        return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
    }
}
