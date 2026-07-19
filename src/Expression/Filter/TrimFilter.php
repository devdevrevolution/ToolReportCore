<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Trim whitespace from both ends of a string.
 *
 * Usage: {{ value | trim }}
 *
 * Example: {{ name | trim }} → "John" from "  John  "
 */
class TrimFilter implements FilterInterface
{
    public function name(): string
    {
        return 'trim';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        return trim((string) $value);
    }
}