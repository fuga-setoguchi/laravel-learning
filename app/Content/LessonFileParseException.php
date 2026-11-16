<?php

declare(strict_types=1);

namespace App\Content;

use RuntimeException;

/** レッスンファイルの front matter を解析できない */
final class LessonFileParseException extends RuntimeException {}
