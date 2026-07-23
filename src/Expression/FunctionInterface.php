<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * Contract for expression functions (new Excel-style API).
 *
 * Functions receive:
 *   - $value: the primary (first) argument, already resolved
 *   - $params: additional parameters, already resolved
 *   - $resolve: callback for resolving variables on demand (e.g., SUM(items[].total))
 */
interface FunctionInterface
{
    /**
     * The function name used in expressions (e.g. "SUM", "UPPER").
     */
    public function name(): string;

    /**
     * Apply this function to the given value with optional parameters.
     *
     * @param mixed $value The primary value (first argument)
     * @param array<int, mixed> $params Additional parameters
     * @param callable $resolve Variable resolution callback: fn(string $key): mixed
     * @return mixed The result
     */
    public function apply(mixed $value, array $params, callable $resolve): mixed;
}
