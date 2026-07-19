<?php

declare(strict_types=1);

namespace Toolreport\Core\Tests\Modules\PdfEngine;

use Toolreport\Core\Modules\PdfEngine\Contracts\Component;

trait CreatesMockComponents
{
    /**
     * Create a mock Component with controlled dimensions.
     *
     * @return Component& \PHPUnit\Framework\MockObject\MockObject
     */
    protected function mockComponent(float $width, float $height): Component
    {
        $component = $this->createMock(Component::class);
        $component->method('getDimensions')->willReturn(['w' => $width, 'h' => $height]);

        return $component;
    }
}
