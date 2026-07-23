<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing a pipe filter chain (backward compat).
 *
 * Example: "name | trim | upper" produces:
 *   FilterChainNode(VariableNode("name"), [FilterApplication("trim"), FilterApplication("upper")])
 */
class FilterChainNode extends ExpressionNode
{
    public function __construct(
        public ExpressionNode $base,
        /** @var list<FilterApplication> */
        public array $filters,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitFilterChain($this);
    }
}
