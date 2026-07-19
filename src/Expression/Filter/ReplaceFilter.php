<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Replace all occurrences of a search string with a replacement string.
 *
 * Usage: {{ value | replace(search, replace) }}
 *
 * Examples:
 *   {{ text | replace("_", " ") }}       → Replaces underscores with spaces
 *   {{ code | replace("-", "") }}        → Removes dashes
 */
class ReplaceFilter implements FilterInterface
{
    public function name(): string
    {
        return 'replace';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $search = (string) ($params[0] ?? '');
        $replace = (string) ($params[1] ?? '');

        return str_replace($search, $replace, (string) $value);
    }
}