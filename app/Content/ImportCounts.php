<?php

declare(strict_types=1);

namespace App\Content;

/** 取り込みの件数（章・レッスン・問題ごと） */
final class ImportCounts
{
    public int $added = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $retired = 0;

    public int $restored = 0;

    public function count(ImportStatus $status): void
    {
        match ($status) {
            ImportStatus::Added => $this->added++,
            ImportStatus::Updated => $this->updated++,
            ImportStatus::Unchanged => $this->unchanged++,
            ImportStatus::Restored => $this->restored++,
        };
    }
}
