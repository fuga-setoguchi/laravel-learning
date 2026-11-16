<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 補足資料（references）のURLが、Laravel の公式の資料であること（docs/04_content-format.md 3.2「補足資料」）。
 * 出典（LaravelDocsUrl）と違い、ドキュメントのバージョンは問わない。公式ブログも許可する。
 */
final readonly class LaravelOfficialUrl implements ValidationRule
{
    /** ドキュメント: バージョン（13.x / master）の後にページ名があること */
    private const string DOCS_PATTERN = '#^https://laravel\.com/docs/(\d+\.x|master)/[^\s/\#?]\S*$#';

    /** 公式ブログ: 記事のパスがあること */
    private const string BLOG_PATTERN = '#^https://(laravel\.com/blog|blog\.laravel\.com)/[^\s/\#?]\S*$#';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || (preg_match(self::DOCS_PATTERN, $value) !== 1 && preg_match(self::BLOG_PATTERN, $value) !== 1)) {
            $fail(__('content.official_url'));
        }
    }
}
