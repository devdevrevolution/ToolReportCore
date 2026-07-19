<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Format a date string using PHP date() format.
 *
 * Usage: {{ value | date(format) }}
 *
 * Examples:
 *   {{ created_at | date("d/m/Y") }}    → "13/06/2026"
 *   {{ created_at | date("Y-m-d") }}     → "2026-06-13"
 *   {{ created_at | date("d M Y") }}     → "13 Jun 2026"
 *
 * The input value can be a UNIX timestamp (int) or a date string (e.g. "2026-06-13").
 */
class DateFormatFilter implements FilterInterface
{
    public function name(): string
    {
        return 'date';
    }

    public function apply(mixed $value, array $params = []): mixed
    {
        if ($value === null) {
            return null;
        }

        $format = (string) ($params[0] ?? 'Y-m-d');

        // If value is a numeric timestamp
        if (is_numeric($value)) {
            return date($format, (int) $value);
        }

        // If value is a date string, convert to timestamp first
        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return (string) $value;
        }

        return date($format, $timestamp);
    }
}