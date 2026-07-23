<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Date;

use Toolreport\Core\Expression\FunctionInterface;

/**
 * DATE_ADD: Add interval to a date.
 *
 * Usage: DATE_ADD(value, interval, unit)
 * Unit: 'day', 'month', 'year', 'hour', 'minute', 'second'
 */
class DateAddFunction implements FunctionInterface
{
    public function name(): string
    {
        return 'DATE_ADD';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        if ($value === null || !isset($params[0]) || !is_numeric($params[0])) {
            return '';
        }

        $interval = (int) $params[0];
        $unit = (string) ($params[1] ?? 'day');

        if (is_numeric($value)) {
            $timestamp = (int) $value;
        } else {
            $timestamp = strtotime((string) $value);
            if ($timestamp === false) {
                return (string) $value;
            }
        }

        $modifier = sprintf('+%d %s', $interval, $unit);

        return date('Y-m-d', strtotime($modifier, $timestamp));
    }
}
