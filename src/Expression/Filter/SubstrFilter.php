<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Extract a substring from a value.
 *
 * Usage: {{ value | substr(start, length) }}
 *
 * Examples:
 *   {{ description | substr(0, 50) }}   → First 50 characters
 *   {{ code | substr(3) }}               → From position 3 to end
 */
class SubstrFilter implements FilterInterface
{
    public function name(): string
    {
        return 'substr';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;
        $start = (int) ($params[0] ?? 0);
        $length = isset($params[1]) ? (int) $params[1] : null;

        if ($length !== null) {
            return mb_substr($string, $start, $length);
        }

        return mb_substr($string, $start);
    }
}