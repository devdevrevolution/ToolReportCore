<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Formatting;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * PARSE_NUMBER: Convert formatted number string to numeric value.
 *
 * Handles Argentine/Latin American format:
 * - Dot (.) as thousands separator → removed
 * - Comma (,) as decimal separator → replaced with dot
 *
 * Usage: PARSE_NUMBER("45.000.000") → 45000000
 *        PARSE_NUMBER("1.234,56")   → 1234.56
 */
class ParseNumberFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'PARSE_NUMBER';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return self::parseFormattedNumber($value) ?? '';
    }

    /**
     * Parse a formatted number string to float.
     *
     * Handles:
     * - "45.000.000" → 45000000 (dots as thousands)
     * - "45.000.001,23" → 45000001.23 (comma as decimal)
     * - "1.234,56" → 1234.56
     * - "999" → 999
     * - "100,5" → 100.5
     */
    public static function parseFormattedNumber(string $value): ?float
    {
        // Empty or whitespace only
        if (trim($value) === '') {
            return null;
        }

        // Latin American format: has comma — dots are thousands, comma is decimal
        if (str_contains($value, ',')) {
            $cleaned = str_replace('.', '', $value);
            $cleaned = str_replace(',', '.', $cleaned);

            return is_numeric($cleaned) ? (float) $cleaned : null;
        }

        // Has dot but no comma: determine if thousands or decimal
        if (str_contains($value, '.')) {
            $dotPos = strrpos($value, '.');
            $afterDot = substr($value, $dotPos + 1);

            // Multiple dots → all are thousands separators (e.g., "45.000.000")
            // Single dot with exactly 3 digits after → thousands separator (e.g., "530.000" → 530000)
            $isThousands = substr_count($value, '.') > 1
                || strlen($afterDot) === 3;

            if ($isThousands) {
                $cleaned = str_replace('.', '', $value);
                return is_numeric($cleaned) ? (float) $cleaned : null;
            }

            // Single dot with 1-2 or 4+ digits after → decimal (e.g., "100.5")
            return is_numeric($value) ? (float) $value : null;
        }

        // No dot, no comma — plain integer or already numeric
        return is_numeric($value) ? (float) $value : null;
    }
}
