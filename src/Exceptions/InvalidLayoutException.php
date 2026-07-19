<?php

declare(strict_types=1);

namespace Toolreport\Core\Exceptions;

use Exception;

class InvalidLayoutException extends Exception
{
    public static function missingField(string $field): self
    {
        return new self("Layout validation failed: missing required field '{$field}'.");
    }

    public static function invalidElementType(string $type): self
    {
        return new self("Layout validation failed: unsupported element type '{$type}'.");
    }

    public static function invalidStructure(string $message): self
    {
        return new self("Layout validation failed: {$message}.");
    }

    /**
     * Create an exception for when band heights exceed the printable area.
     */
    public static function bandHeightOverflow(float $total, float $printable, float $overflow): self
    {
        return new self("Band heights exceed printable area: {$total}mm total vs {$printable}mm available (overflow: {$overflow}mm). Reduce band heights or increase page size.");
    }

    /**
     * Create an exception for when a band has a missing or invalid height.
     */
    public static function invalidBandHeight(int $index): self
    {
        return new self("Invalid band configuration: band at index {$index} has missing or invalid height. Each band must have a positive numeric 'height' property.");
    }
}