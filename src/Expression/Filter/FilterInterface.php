<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Filter;

/**
 * Contract for expression filters used in variable interpolation.
 *
 * Filters transform resolved variable values before rendering.
 * They are chained via pipe syntax: {{ variable | filter1 | filter2(arg) }}
 */
interface FilterInterface
{
    /**
     * The filter name used in expressions (e.g. "currency", "number", "upper").
     */
    public function name(): string;

    /**
     * Apply this filter to the given value with optional parameters.
     *
     * @param mixed $value The resolved variable value (may be null)
     * @param array<int, mixed> $params Parameters passed to the filter in parentheses
     * @return mixed The transformed value
     */
    public function apply(mixed $value, array $params = []): mixed;
}