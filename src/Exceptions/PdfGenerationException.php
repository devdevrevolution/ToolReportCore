<?php

declare(strict_types=1);

namespace Toolreport\Core\Exceptions;

use Exception;

class PdfGenerationException extends Exception
{
    public static function renderFailed(string $message): self
    {
        return new self("PDF generation failed during rendering: {$message}.");
    }

    public static function generationError(string $message): self
    {
        return new self("PDF generation failed: {$message}.");
    }

    public static function storageError(string $message): self
    {
        return new self("PDF generation failed: storage error: {$message}.");
    }
}
