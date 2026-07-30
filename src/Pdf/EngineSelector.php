<?php

declare(strict_types=1);

namespace Toolreport\Core\Pdf;

use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Modules\PdfEngine\Engine\ReportCompiler;

class EngineSelector
{
    private ReportCompiler $report_compiler;

    public function __construct(
        ReportCompiler $report_compiler,
    ) {
        $this->report_compiler = $report_compiler;
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
        return $this->renderWithPdfEngine($template, $data);
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
        return $this->renderPageWithPdfEngine($template, $page_data, $data);
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

}
