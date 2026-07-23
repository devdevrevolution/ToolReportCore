<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Date;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * FORMAT_DATE: Format a date value using PHP date() format.
 *
 * Usage: FORMAT_DATE(value, format)
 */
class FormatDateFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'FORMAT_DATE';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null) {
            return '';
        }

        $format = (string) ($params[0] ?? 'Y-m-d');

        if (is_numeric($value)) {
            return date($format, (int) $value);
        }

        $timestamp = strtotime((string) $value);

        if ($timestamp === false) {
            return (string) $value;
        }

        return date($format, $timestamp);
    }
}
