<?php

declare(strict_types=1);

namespace Toolreport\Core\Pdf;

use Toolreport\Core\Layout\LayoutEngine;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Modules\PdfEngine\Engine\ReportCompiler;

class EngineSelector
{
    private ReportCompiler $report_compiler;
    private PdfGenerator $pdf_generator;
    private LayoutEngine $layout_engine;

    public function __construct(
        ReportCompiler $report_compiler,
        PdfGenerator $pdf_generator,
        LayoutEngine $layout_engine,
    ) {
        $this->report_compiler = $report_compiler;
        $this->pdf_generator = $pdf_generator;
        $this->layout_engine = $layout_engine;
    }

    /**
     * Dispatch rendering to the correct engine based on template config.
     *
     * @param PdfTemplate $template
     * @param array $data
     * @return string PDF binary content
     */
    public function render(PdfTemplate $template, array $data = []): string
    {
        $engine = $template->engine ?? 'dompdf';

        return match ($engine) {
            'pdf-engine' => $this->renderWithPdfEngine($template, $data),
            default => $this->renderWithDomPdf($template, $data),
        };
    }

    /**
     * Render a composition page with a specific engine.
     *
     * @param PdfTemplate $template
     * @param array $page_data
     * @param array $data
     * @return string PDF binary content
     */
    public function renderPage(PdfTemplate $template, array $page_data, array $data = []): string
    {
        $engine = $template->engine ?? 'dompdf';

        return match ($engine) {
            'pdf-engine' => $this->renderPageWithPdfEngine($template, $page_data, $data),
            default => $this->renderPageWithDomPdf($template, $page_data, $data),
        };
    }

    private function renderWithPdfEngine(PdfTemplate $template, array $data): string
    {
        $config = $template->getFullConfig();

        $page_config = [
            'page' => $config['page'] ?? [],
        ];

        return $this->report_compiler->compile($page_config, $data);
    }

    private function renderPageWithPdfEngine(PdfTemplate $template, array $page_data, array $data): string
    {
        $template_config = $template->config ?? [];
        $page_config = [
            'page' => $page_data['page'] ?? $page_data,
        ];

        return $this->report_compiler->compile($page_config, $data);
    }

    private function renderWithDomPdf(PdfTemplate $template, array $data): string
    {
        $full_config = $template->getFullConfig();
        $layout_result = $this->layout_engine->render(
            $full_config,
            $data,
            $template->name ?? 'Document',
        );

        return $this->pdf_generator->generateBinary($layout_result);
    }

    private function renderPageWithDomPdf(PdfTemplate $template, array $page_data, array $data): string
    {
        $full_config = $template->getFullConfig();

        // Merge page-specific data into the template configuration
        $merged_page = $full_config['page'] ?? [];
        foreach ($page_data as $key => $value) {
            $merged_page[$key] = $value;
        }
        $full_config['page'] = $merged_page;

        $layout_result = $this->layout_engine->render(
            $full_config,
            $data,
            $template->name ?? 'Page',
        );

        return $this->pdf_generator->generateBinary($layout_result);
    }
}
