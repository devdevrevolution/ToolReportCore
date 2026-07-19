<?php

declare(strict_types=1);

namespace Toolreport\Core\Exceptions;

use Exception;

class TemplateNotFoundException extends Exception
{
    public static function withId(int $id): self
    {
        return new self("PDF template with ID {$id} was not found.");
    }
}
