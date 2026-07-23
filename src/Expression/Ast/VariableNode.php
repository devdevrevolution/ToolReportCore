<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * AST node representing a variable reference.
 *
 * The path may contain dots, brackets, and array notation:
 *   - "name"
 *   - "address.city"
 *   - "[].cognome"
 *   - "orders[].total"
 */
class VariableNode extends ExpressionNode
{
    public function __construct(
        public string $path,
    ) {}

    public function accept(Evaluator $evaluator): mixed
    {
        return $evaluator->visitVariable($this);
    }
}
