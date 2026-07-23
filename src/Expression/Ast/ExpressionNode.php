<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression\Ast;

use Toolreport\Core\Expression\Evaluator;

/**
 * Abstract base class for all AST nodes in the expression tree.
 */
abstract class ExpressionNode
{
    /**
     * Accept an evaluator visitor for double dispatch.
     */
    abstract public function accept(Evaluator $evaluator): mixed;
}
