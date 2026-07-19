<?php

declare(strict_types=1);

namespace Toolreport\Core\Modules\PdfEngine\Exceptions;

class UnknownComponentException extends \Exception
{
    private string $component_type;

    public function __construct(string $type)
    {
        $this->component_type = $type;
        parent::__construct("Unknown component type: '{$type}'");
    }

    public function getType(): string
    {
        return $this->component_type;
    }
}
