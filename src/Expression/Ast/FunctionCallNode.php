<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing a function call expression.
 *
 * Examples:
 *   SUM(prices)
 *   ROUND(price, 2)
 *   UPPER(name)
 */
class FunctionCallNode extends ExpressionNode
{
    public function __construct(
        public string $name,
        /** @var list<ExpressionNode> */
        public array $args,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitFunctionCall($this);
    }
}
