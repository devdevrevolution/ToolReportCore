<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

use Toolreport\Core\Expression\Filter\FilterInterface;

/**
 * Wraps a legacy FilterInterface as a FunctionInterface.
 *
 * This allows existing filters to be used in the new expression pipeline
 * without re-implementing their logic.
 */
class LegacyFilterAdapter implements FunctionInterface
{
    public function __construct(
        private FilterInterface $filter,
    ) {}

    public function name(): string
    {
        return $this->filter->name();
    }

    public function apply(mixed $value, array $params, callable $resolve): mixed
    {
        return $this->filter->apply($value, $params);
    }
}
