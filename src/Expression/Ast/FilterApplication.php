<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

/**
 * Value object representing a single filter application within a filter chain.
 *
 * Example: In "name | upper", this represents the "upper" filter with no params.
 * In "price | currency('$')", this represents the "currency" filter with param ['$'].
 */
readonly class FilterApplication
{
    public function __construct(
        public string $name,
        /** @var list<ExpressionNode> */
        public array $params,
    ) {}
}
