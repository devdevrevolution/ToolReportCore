<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Function\Math;

use Toolreport\Core\Expression\FunctionInterface;
use Toolreport\Core\Expression\Trait\ParsesFormattedNumber;

/**
 * SUM: Flatten arrays and sum all numeric values.
 *
 * Automatically parses formatted number strings (e.g., "45.000.000" → 45000000).
 *
 * Usage: SUM(values[]) or SUM(a, b, c)
 */
class SumFunction implements FunctionInterface
{
    use ParsesFormattedNumber;

    public function name(): string
    {
        return 'SUM';
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        $numbers = $this->collectNumbers($value, $params);

        if ($numbers === []) {
            return 0;
        }

        return array_sum($numbers);
    }

    /**
     * @return list<float>
     */
    private function collectNumbers(mixed $value, array $params): array
    {
        $numbers = [];

        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_array($item)) {
                    foreach ($item as $v) {
                        $parsed = $this->parseToFloat($v);
                        if ($parsed !== null) {
                            $numbers[] = $parsed;
                        }
                    }
                } else {
                    $parsed = $this->parseToFloat($item);
                    if ($parsed !== null) {
                        $numbers[] = $parsed;
                    }
                }
            }
        } else {
            $parsed = $this->parseToFloat($value);
            if ($parsed !== null) {
                $numbers[] = $parsed;
            }
        }

        foreach ($params as $param) {
            if (is_array($param)) {
                foreach ($param as $item) {
                    $parsed = $this->parseToFloat($item);
                    if ($parsed !== null) {
                        $numbers[] = $parsed;
                    }
                }
            } else {
                $parsed = $this->parseToFloat($param);
                if ($parsed !== null) {
                    $numbers[] = $parsed;
                }
            }
        }

        return $numbers;
    }
}
