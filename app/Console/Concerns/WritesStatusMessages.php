<?php

declare(strict_types=1);

namespace App\Console\Concerns;

use Symfony\Component\Console\Formatter\OutputFormatter;

/**
 * 「ERROR」「WARN」の見出し付きでメッセージを表示する。
 * Laravel の $this->components は、文末が「.」「?」「!」「:」でないとピリオドを足すため、
 * 「。」で終わる日本語のメッセージが「。.」になる。見た目を揃えつつ、文はそのまま表示する。
 */
trait WritesStatusMessages
{
    protected function errorMessage(string $message): void
    {
        $this->statusMessage('ERROR', 'fg=white;bg=red', $message);
    }

    protected function warnMessage(string $message): void
    {
        $this->statusMessage('WARN', 'fg=black;bg=yellow', $message);
    }

    private function statusMessage(string $title, string $style, string $message): void
    {
        $this->newLine();
        $this->line("  <{$style}> {$title} </> ".OutputFormatter::escape($message));
        $this->newLine();
    }
}
