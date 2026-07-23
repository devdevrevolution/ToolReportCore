<?php

declare(strict_types=1);

namespace Toolreport\Core\Expression;

/**
 * Value object representing a single token from the expression tokenizer.
 */
readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $position,
    ) {}
}
