<?php

declare(strict_types=1);

namespace Toolreport\Core\Layout;

use Toolreport\Core\Exceptions\InvalidLayoutException;

interface ElementRendererInterface
{
    /**
     * Render a single element to positioned HTML.
     *
     * @param array $element   The element config from the designer JSON.
     * @param array $data      Variable data for interpolation (global context).
     * @param array $page      Page configuration (width, height, margins).
     * @param array $localData Local data context for detail band repetition (local-first resolution).
     * @return string The rendered HTML string.
     * @throws InvalidLayoutException
     */
    public function render(array $element, array $data, array $page, array $localData = []): string;

    /**
     * The element type this renderer handles.
     *
     * @return string e.g., 'text', 'image', 'table'
     */
    public function type(): string;
}