<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Validation\ValidationException;
use Toolreport\Core\DataTransferObjects\LayoutResult;
use Toolreport\Core\Exceptions\InvalidLayoutException;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Layout\LayoutEngine;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Pdf\EngineSelector;
use Toolreport\Core\Pdf\PdfGenerator;

class PdfRenderingService
{
    public function __construct(
        private readonly LayoutEngine $layoutEngine,
        private readonly PdfGenerator $pdfGenerator,
        private readonly DatasourceExecutionService $datasourceExecutionService,
        private readonly EngineSelector $engineSelector,
        private readonly TemplateVarService $templateVarService,
    ) {}

    /**
     * Resolve data for rendering — execute datasources with resolved variables,
     * then merge env_vars as a base layer for template interpolation.
     *
     * The $explicitData parameter contains the resolved env_vars from the controller.
     * Datasources are ALWAYS executed if configured (they fetch the actual data).
     * The resolved vars are used for:
     *  1. URL/header/auth token interpolation in datasource HTTP requests
     *  2. Base layer for template content interpolation ({{var}} in text elements)
     *
     * @param  array<string, mixed>  $explicitData  Resolved env_vars from controller
     * @param  array<string, mixed>  $fullConfig
     * @return array<string, mixed>
     */
    public function resolveData(array $explicitData, PdfTemplate $template, array $fullConfig): array
    {
        $templateConfig = $template->config ?? [];
        $datasources = $templateConfig['datasources'] ?? [];
        $bands = $fullConfig['page']['bands'] ?? [];

        // Execute datasources with resolved vars for URL/header resolution
        $datasourceData = [];
        if (!empty($datasources)) {
            if (!empty($bands)) {
                $datasourceData = $this->datasourceExecutionService->executeForRendering($datasources, $explicitData);
            } else {
                $datasourceData = $this->datasourceExecutionService->execute($datasources, $explicitData);
            }
        }

        // Merge: resolved vars (base) + datasource response (override)
        // This ensures env_vars are available for {{var}} interpolation
        // while datasource data takes precedence for overlapping keys.
        return array_merge($explicitData, $datasourceData);
    }

    /**
     * Render a single template into a PDF document.
     *
     * Delegates rendering to the appropriate engine via EngineSelector.
     *
     * @param  array<string, mixed>  $data
     * @throws PdfGenerationException
     */
    public function renderTemplate(PdfTemplate $template, array $data, string $title): PdfDocument
    {
        $fullConfig = $template->getFullConfig();
        $resolvedData = $this->resolveData($data, $template, $fullConfig);

        $engine = $template->engine ?? 'dompdf';

        if ($engine === 'pdf-engine') {
            $pdfBinary = $this->engineSelector->render($template, $resolvedData);

            return $this->pdfGenerator->saveBinary($template, $pdfBinary, $title, $resolvedData);
        }

        // Default dompdf path — existing flow
        $layout = $this->layoutEngine->render($fullConfig, $resolvedData, $title);

        return $this->pdfGenerator->generateFromLayout($template, $layout, $resolvedData);
    }

    /**
     * Validate that a composition's pages are ready for rendering.
     *
     * @throws ValidationException
     */
    public function validateCompositionPages(ReportComposition $composition): void
    {
        $composition->loadMissing('pages.template');

        if ($composition->pages->isEmpty()) {
            throw ValidationException::withMessages([
                'composition' => ['La composición no tiene páginas. Agregue al menos una página antes de generar.'],
            ]);
        }

        if ($composition->pages->count() > 50) {
            throw ValidationException::withMessages([
                'pages' => ['La composición supera el límite máximo de 50 páginas.'],
            ]);
        }

        foreach ($composition->pages as $page) {
            if ($page->template === null) {
                throw ValidationException::withMessages([
                    'pages' => ["La plantilla para la página en la posición {$page->sort_order} ya no existe."],
                ]);
            }

            if (! $page->template->is_active) {
                $name = $page->template->name;
                throw ValidationException::withMessages([
                    'pages' => ["La plantilla '{$name}' está inactiva y no puede utilizarse para generar."],
                ]);
            }
        }
    }

    /**
     * Render all pages of a composition into a single combined PDF document.
     *
     * Each composition page is rendered individually — the engine used depends on
     * each page's template configuration. All pages must use the same engine;
     * mixed-engine compositions are not yet supported.
     *
     * @param  array<string, mixed>  $data  Explicit data passed by the user
     * @param  string|null  $title  Optional document/layout title; falls back to each page template name
     * @return PdfDocument
     * @throws ValidationException
     * @throws PdfGenerationException
     */
    public function renderComposition(ReportComposition $composition, array $data, ?string $title = null): PdfDocument
    {
        $this->validateCompositionPages($composition);

        // Determine if all pages use the same engine
        $allDomPdf = $composition->pages->every(
            fn ($page) => ($page->template->engine ?? 'dompdf') === 'dompdf',
        );

        $allPdfEngine = $composition->pages->every(
            fn ($page) => ($page->template->engine ?? 'dompdf') === 'pdf-engine',
        );

        if (!$allDomPdf && !$allPdfEngine) {
            throw ValidationException::withMessages([
                'composition' => ['No se admiten motores mixtos en una composición. Todas las páginas deben usar el mismo motor.'],
            ]);
        }

        if ($allPdfEngine) {
            return $this->renderCompositionWithPdfEngine($composition, $data, $title);
        }

        return $this->renderCompositionWithDomPdf($composition, $data, $title);
    }

    /**
     * Render a composition using the default DomPDF engine for all pages.
     *
     * @param  array<string, mixed>  $data
     * @return PdfDocument
     * @throws ValidationException
     */
    private function renderCompositionWithDomPdf(ReportComposition $composition, array $data, ?string $title = null): PdfDocument
    {
        /** @var LayoutResult[] $layoutResults */
        $layoutResults = [];

        foreach ($composition->pages as $page) {
            if ($page->template === null) {
                continue;
            }

            $fullConfig = $page->template->getFullConfig();
            $resolvedData = $this->resolveData($data, $page->template, $fullConfig);
            $pageTitle = $title ?? $page->template->name;

            try {
                $layoutResults[] = $this->layoutEngine->render($fullConfig, $resolvedData, $pageTitle);
            } catch (InvalidLayoutException $e) {
                throw ValidationException::withMessages([
                    'pages' => [
                        "La plantilla '{$page->template->name}' no cabe en la página de la composición: " .
                        $e->getMessage(),
                    ],
                ]);
            }
        }

        if (empty($layoutResults)) {
            throw ValidationException::withMessages([
                'composition' => ['No se pudieron renderizar las páginas de la composición.'],
            ]);
        }

        return $this->pdfGenerator->generateFromComposition($composition, $layoutResults, $data);
    }

    /**
     * Render a composition using the pdf-engine for all pages.
     *
     * Each page is compiled through the pdf-engine; the resulting PDF binaries
     * are concatenated into a single document.
     *
     * @param  array<string, mixed>  $data
     * @return PdfDocument
     * @throws PdfGenerationException
     */
    private function renderCompositionWithPdfEngine(ReportComposition $composition, array $data, ?string $title = null): PdfDocument
    {
        $pageBinaries = [];
        $firstTemplate = null;

        foreach ($composition->pages as $page) {
            if ($page->template === null) {
                continue;
            }

            if ($firstTemplate === null) {
                $firstTemplate = $page->template;
            }

            $resolvedData = $this->resolveData($data, $page->template, $page->template->getFullConfig());
            $pageData = $page->data ?? [];

            $pageBinaries[] = $this->engineSelector->renderPage(
                $page->template,
                $pageData,
                $resolvedData,
            );
        }

        if (empty($pageBinaries)) {
            throw ValidationException::withMessages([
                'composition' => ['No se pudieron renderizar las páginas de la composición.'],
            ]);
        }

        $documentTitle = $title ?? $composition->pages->first()?->template?->name ?? 'Composition';

        // Concatenate raw PDF binaries — the pdf-engine produces page-at-a-time PDFs
        // that can be concatenated at the binary level. Each renderPage() call
        // produces a complete single-page PDF via TCPDF.
        $combinedBinary = implode('', $pageBinaries);

        return $this->pdfGenerator->saveBinary(
            $firstTemplate,
            $combinedBinary,
            $documentTitle,
            $data,
        );
    }
}
