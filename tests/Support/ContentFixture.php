<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * 正常な教材（tests/Fixtures/content/valid）を一時フォルダにコピーし、テストごとに書き換える。
 */
final class ContentFixture
{
    private function __construct(public readonly string $path) {}

    public static function create(): self
    {
        $path = sys_get_temp_dir().'/laravel-learning-content-'.bin2hex(random_bytes(6));
        File::copyDirectory(base_path('tests/Fixtures/content/valid'), $path);

        return new self($path);
    }

    /**
     * レッスンファイルの front matter を書き換える（本文はそのまま）
     *
     * @param  Closure(array<string, mixed>): array<string, mixed>  $edit
     */
    public function editLesson(string $file, Closure $edit): self
    {
        [$frontMatter, $body] = $this->split($file);
        $this->writeLesson($file, $edit($frontMatter), $body);

        return $this;
    }

    public function setBody(string $file, string $body): self
    {
        [$frontMatter] = $this->split($file);
        $this->writeLesson($file, $frontMatter, $body);

        return $this;
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $edit
     */
    public function editChapters(Closure $edit): self
    {
        $data = Yaml::parseFile("{$this->path}/chapters.yaml");

        return $this->write('chapters.yaml', Yaml::dump($edit($data), 10, 2));
    }

    public function write(string $file, string $contents): self
    {
        File::ensureDirectoryExists(dirname("{$this->path}/{$file}"));
        File::put("{$this->path}/{$file}", $contents);

        return $this;
    }

    public function read(string $file): string
    {
        return File::get("{$this->path}/{$file}");
    }

    public function delete(string $file): self
    {
        File::delete("{$this->path}/{$file}");

        return $this;
    }

    public function cleanup(): void
    {
        File::deleteDirectory($this->path);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function split(string $file): array
    {
        if (preg_match('/\A---\n(.*?)\n---\n(.*)\z/s', $this->read($file), $matches) !== 1) {
            throw new RuntimeException("{$file} に front matter がありません。");
        }

        return [Yaml::parse($matches[1]), $matches[2]];
    }

    /**
     * @param  array<string, mixed>  $frontMatter
     */
    private function writeLesson(string $file, array $frontMatter, string $body): void
    {
        $yaml = Yaml::dump($frontMatter, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        $this->write($file, "---\n{$yaml}---\n{$body}");
    }
}
