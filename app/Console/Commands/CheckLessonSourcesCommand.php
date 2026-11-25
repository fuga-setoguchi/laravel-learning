<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\ContentLoader;
use App\Content\ContentValidationException;
use App\Content\LessonFileValidator;
use App\Content\SourceCheckResult;
use App\Content\SourceLinkChecker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * CMD-02 出典リンクの実在確認（docs/commands/CMD-02_lessons-check-sources.md）。
 * コマンドは入出力だけを担い、処理は App\Content のクラスに任せる。DBは使わない。
 */
#[Signature('lessons:check-sources {--path= : 教材フォルダ（既定は content/）}')]
#[Description('教材の出典URLのページとアンカーが実在するかを確かめる')]
class CheckLessonSourcesCommand extends Command
{
    public function handle(ContentLoader $loader, LessonFileValidator $validator, SourceLinkChecker $checker): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : config()->string('learning.content_path');

        try {
            $loaded = $loader->load($path);
            $content = $validator->validate($loaded);
        } catch (ContentValidationException $e) {
            $this->reportValidationErrors($e);

            return self::FAILURE;
        }

        $displayPaths = [];
        foreach ($loaded->lessonFiles as $file) {
            $displayPaths[$file->expectedId] = $file->displayPath;
        }

        $result = $checker->check($content, $displayPaths);
        $this->reportResult($result);

        return $result->errors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function reportValidationErrors(ContentValidationException $e): void
    {
        $this->components->error("教材の検証で {$e->count()} 件のエラーがありました。");

        foreach ($e->errors as $file => $messages) {
            $this->line("  {$file}");
            foreach ($messages as $message) {
                $this->line("    {$message}");
            }
        }
    }

    private function reportResult(SourceCheckResult $result): void
    {
        if ($result->errors !== []) {
            $this->components->error("出典の確認で {$result->errorCount()} 件のエラーがありました。");

            foreach ($result->errors as $file => $messages) {
                $this->line("  {$file}");
                foreach ($messages as $message) {
                    $this->line("    {$message}");
                }
            }
        } else {
            $this->info('出典を確認しました。');
            $this->line("  ファイル: {$result->fileCount} 件 / URL: {$result->urlCount} 件 / 対象外: {$result->skippedCount} 件");
        }

        if ($result->warnings !== []) {
            $this->components->warn('確認できなかった URL が '.count($result->warnings).' 件あります（一時的な問題の可能性があります）。');

            foreach ($result->warnings as $warning) {
                $this->line("  {$warning}");
            }
        }
    }
}
