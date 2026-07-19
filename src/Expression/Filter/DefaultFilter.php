<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Provide a fallback value when the variable is null or empty string.
 *
 * Usage: {{ value | default(fallback) }}
 *
 * Examples:
 *   {{ phone | default("N/A") }}       → "N/A" when phone is null
 *   {{ phone | default("N/A") }}       → "555-1234" when phone has a value
 *   {{ name | default("Sin nombre") }} → "Sin nombre" when name is ""
 */
class DefaultFilter implements FilterInterface
{
    public function name(): string
    {
        return 'default';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        $fallback = $params[0] ?? '';

        if ($value === null || $value === '') {
            return $fallback;
        }

        return $value;
    }
}