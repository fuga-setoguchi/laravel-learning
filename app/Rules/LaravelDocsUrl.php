<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 出典URLが、レッスンの対象バージョンの公式ドキュメントのページであること
 * （docs/04_content-format.md 3.2「出典」）。
 */
final readonly class LaravelDocsUrl implements ValidationRule
{
    private const PREFIX = 'https://laravel.com/docs/';

    /**
     * @param  string|null  $laravelVersion  レッスンの laravel_version。形式が不正なら null（バージョンの照合を省く）
     */
    public function __construct(private ?string $laravelVersion) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_starts_with($value, self::PREFIX)) {
            $fail(__('content.docs_url.prefix'));

            return;
        }

        // 例: https://laravel.com/docs/13.x/container#binding → ["13.x", "container#binding"]
        [$version, $page] = array_pad(explode('/', substr($value, strlen(self::PREFIX)), 2), 2, '');

        if ($this->laravelVersion !== null && $version !== $this->laravelVersion) {
            $fail(__('content.docs_url.version', ['actual' => $version, 'expected' => $this->laravelVersion]));

            return;
        }

        if (preg_match('/^[^\s\/#?]\S*$/', $page) !== 1) {
            $fail(__('content.docs_url.page'));
        }
    }
}
