<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Convert a string to uppercase.
 *
 * Usage: {{ value | upper }}
 *
 * Example: {{ name | upper }} → "JOHN DOE"
 */
class UpperFilter implements FilterInterface
{
    public function name(): string
    {
        return 'upper';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return strtoupper((string) $value);
    }
}