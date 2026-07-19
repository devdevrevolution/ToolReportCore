<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout;

/**
 * Trait for ElementRendererInterface implementations that need access
 * to the LayoutEngine for recursive child rendering.
 *
 * Provides a standard setLayoutEngine() implementation and stores the
 * engine reference in $this->layoutEngine.
 */
trait HasLayoutEngine
{
    protected ?LayoutEngine $layoutEngine = null;

    public function setLayoutEngine(?LayoutEngine $engine): void
    {
        $this->layoutEngine = $engine;
    }
}
