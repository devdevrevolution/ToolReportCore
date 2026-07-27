<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * Token types for the expression tokenizer.
 */
enum TokenType: string
{
    case FUNCTION = 'FUNCTION';
    case VARIABLE = 'VARIABLE';
    case NUMBER = 'NUMBER';
    case STRING = 'STRING';
    case ARRAY = 'ARRAY';
    case LPAREN = 'LPAREN';
    case RPAREN = 'RPAREN';
    case COMMA = 'COMMA';
    case OPERATOR = 'OPERATOR';
    case EOF = 'EOF';
}
