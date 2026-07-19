<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Convert a string to lowercase.
 *
 * Usage: {{ value | lower }}
 *
 * Example: {{ name | lower }} → "john doe"
 */
class LowerFilter implements FilterInterface
{
    public function name(): string
    {
        return 'lower';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return strtolower((string) $value);
    }
}