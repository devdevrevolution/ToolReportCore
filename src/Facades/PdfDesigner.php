<?php

declare(strict_types=1);

namespace Toolreport\Core\Facades;

use Illuminate\Support\Facades\Facade;
use Toolreport\Core\DataTransferObjects\LayoutResult;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Services\PdfRenderingService;

/**
 * @method static PdfDocument renderTemplate(PdfTemplate $template, array $data, string $title)
 * @method static PdfDocument renderComposition(ReportComposition $composition, array $data, ?string $title = null)
 * @method static void validateCompositionPages(ReportComposition $composition)
 * @method static array resolveData(array $data, PdfTemplate $template, array $fullConfig)
 *
 * @see \Toolreport\Core\Services\PdfRenderingService
 */
class PdfDesigner extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PdfRenderingService::class;
    }
}
