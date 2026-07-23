<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing a binary operation (currently only + for concatenation).
 */
class BinaryOpNode extends ExpressionNode
{
    public function __construct(
        public ExpressionNode $left,
        public string $operator,
        public ExpressionNode $right,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitBinaryOp($this);
    }
}
