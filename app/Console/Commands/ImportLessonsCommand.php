<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\ContentImporter;
use App\Content\ContentLoader;
use App\Content\ContentValidationException;
use App\Content\ImportCounts;
use App\Content\ImportResult;
use App\Content\LessonFileValidator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * CMD-01 教材の取り込み（docs/commands/CMD-01_lessons-import.md）。
 * コマンドは入出力だけを担い、処理は App\Content のクラスに任せる。
 */
#[Signature('lessons:import {--path= : 教材フォルダ（既定は content/）} {--dry-run : 検証と件数の集計だけを行い、DBに書き込まない}')]
#[Description('教材のMarkdownを検証し、DBに取り込む')]
class ImportLessonsCommand extends Command
{
    public function handle(ContentLoader $loader, LessonFileValidator $validator, ContentImporter $importer): int
    {
        $path = $this->option('path');
        $path = is_string($path) && $path !== '' ? $path : config()->string('learning.content_path');

        try {
            $content = $validator->validate($loader->load($path));
        } catch (ContentValidationException $e) {
            $this->reportErrors($e);

            return self::FAILURE;
        }

        $result = $importer->import($content, (bool) $this->option('dry-run'));
        $this->reportResult($result);

        return self::SUCCESS;
    }

    private function reportErrors(ContentValidationException $e): void
    {
        $this->components->error("教材の検証で {$e->count()} 件のエラーがありました。DBは変更していません。");

        foreach ($e->errors as $file => $messages) {
            $this->line("  {$file}");
            foreach ($messages as $message) {
                $this->line("    {$message}");
            }
        }
    }

    private function reportResult(ImportResult $result): void
    {
        $this->info($result->dryRun
            ? '検証に成功しました（--dry-run のため、DBは変更していません）。'
            : '教材を取り込みました。');

        $this->line("  章:       追加 {$result->chapters->added} / 更新 {$result->chapters->updated} / 引退 {$result->chapters->retired} / 復活 {$result->chapters->restored}");
        $this->line('  レッスン: '.$this->formatCounts($result->lessons));
        $this->line('  問題:     '.$this->formatCounts($result->questions));

        foreach ($result->warnings as $warning) {
            $this->components->warn($warning);
        }
    }

    private function formatCounts(ImportCounts $counts): string
    {
        return "追加 {$counts->added} / 更新 {$counts->updated} / 変更なし {$counts->unchanged} / 引退 {$counts->retired} / 復活 {$counts->restored}";
    }
}
