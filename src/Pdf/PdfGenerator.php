<?php

declare(strict_types=1);

namespace Toolreport\Core\Pdf;

use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Toolreport\Core\DataTransferObjects\LayoutResult;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\ReportComposition;

class PdfGenerator
{
    /**
     * Generate raw PDF binary from a LayoutResult without persisting to storage.
     *
     * @param LayoutResult $layout The rendered layout.
     * @return string Raw PDF binary content.
     * @throws PdfGenerationException
     */
    public function generateBinary(LayoutResult $layout): string
    {
        try {
            $pdf = DomPdf::loadHtml($layout->html);
            [$paper, $orientation] = $layout->pageDimensions();
            $pdf->setPaper($paper, $orientation);

            $dompdfOptions = config('pdf-designer.dompdf.options', []);
            $pdf->setOptions($dompdfOptions);

            return $pdf->output();
        } catch (\Exception $e) {
            throw PdfGenerationException::domPdfError($e->getMessage());
        }
    }

    /**
     * Save a pre-generated PDF binary as a PdfDocument to storage.
     *
     * Creates the PdfDocument record, persists the binary to the configured
     * disk, and returns the document with status 'done'.
     *
     * @param PdfTemplate $template The template model.
     * @param string $pdfBinary The raw PDF binary content.
     * @param string $title The document title.
     * @param array $data The data used for generation.
     * @return PdfDocument
     * @throws PdfGenerationException
     */
    public function saveBinary(PdfTemplate $template, string $pdfBinary, string $title, array $data = []): PdfDocument
    {
        $disk = config('pdf-designer.storage.disk', 'local');
        $storagePath = config('pdf-designer.storage.path', 'pdf-documents');

        $document = new PdfDocument([
            'pdf_template_id' => $template->id,
            'title' => $title,
            'data' => $data,
            'status' => 'generating',
        ]);
        $document->save();

        try {
            $filename = sprintf(
                '%s/%s_%s.pdf',
                $storagePath,
                \Illuminate\Support\Str::slug($template->name),
                $document->id
            );

            Storage::disk($disk)->put($filename, $pdfBinary);

            $document->file_path = $filename;
            $document->file_size = strlen($pdfBinary);
            $document->status = 'done';
            $document->generated_at = now();
            $document->save();

            return $document;

        } catch (\Exception $e) {
            $document->status = 'failed';
            $document->error_message = $e->getMessage();
            $document->save();

            throw PdfGenerationException::domPdfError($e->getMessage());
        }
    }

    /**
     * Generate a PDF from a LayoutResult and save it to storage.
     *
     * @param PdfTemplate $template The template model.
     * @param LayoutResult $layout The rendered layout.
     * @param array $data The data used for generation.
     * @return PdfDocument
     * @throws PdfGenerationException
     */
    public function generateFromLayout(PdfTemplate $template, LayoutResult $layout, array $data = []): PdfDocument
    {
        try {
            $pdfContent = $this->generateBinary($layout);
        } catch (PdfGenerationException $e) {
            // Create a failed document record to match the original contract
            $document = new PdfDocument([
                'pdf_template_id' => $template->id,
                'title' => $layout->title,
                'data' => $data,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
            $document->save();

            throw $e;
        }

        return $this->saveBinary($template, $pdfContent, $layout->title, $data);
    }

    /**
     * Generate a PDF from raw HTML directly.
     *
     * @param string $html The HTML content.
     * @param string $paperSize Paper size (e.g., 'a4', 'letter').
     * @param string $orientation 'portrait' or 'landscape'.
     * @return Response
     * @throws PdfGenerationException
     */
    public function generateFromHtml(string $html, string $paperSize = 'a4', string $orientation = 'portrait'): Response
    {
        try {
            $pdf = DomPdf::loadHtml($html);
            $pdf->setPaper($paperSize, $orientation);

            $dompdfOptions = config('pdf-designer.dompdf.options', []);
            $pdf->setOptions($dompdfOptions);

            return $pdf->download('document.pdf');

        } catch (\Exception $e) {
            throw PdfGenerationException::domPdfError($e->getMessage());
        }
    }

    /**
     * Generate a PDF from a composition's rendered layout results.
     *
     * Combines multiple LayoutResult HTML fragments into a single PDF
     * without blank intermediate pages. Each page's body content is
     * extracted and placed in its own page-break section within one
     * HTML document, keeping styles and margins correct per page.
     *
     * @param ReportComposition $composition The composition model.
     * @param LayoutResult[] $layoutResults Array of rendered layout results.
     * @param array $data The data used for generation.
     * @return PdfDocument
     * @throws PdfGenerationException
     */
    public function generateFromComposition(ReportComposition $composition, array $layoutResults, array $data = []): PdfDocument
    {
        $disk = config('pdf-designer.storage.disk', 'local');
        $storagePath = config('pdf-designer.storage.path', 'pdf-documents');

        $document = new PdfDocument([
            'pdf_template_id' => null,
            'report_composition_id' => $composition->id,
            'title' => $layoutResults[0]->title ?? 'Composition',
            'data' => $data,
            'status' => 'generating',
        ]);
        $document->save();

        try {
            // Build a single HTML document with each page's body content
            // separated by page-break-after:always to avoid blank pages.
            // We extract only <body> content from each LayoutResult to avoid
            // nesting multiple <html>/<head>/<body> documents inside one another.
            $combinedHtml = $this->buildCompositionHtml($layoutResults);

            $firstLayout = $layoutResults[0];
            [$paper, $orientation] = $firstLayout->pageDimensions();

            $pdf = DomPdf::loadHtml($combinedHtml);
            $pdf->setPaper($paper, $orientation);

            $dompdfOptions = config('pdf-designer.dompdf.options', []);
            $pdf->setOptions($dompdfOptions);

            $pdfContent = $pdf->output();

            $filename = sprintf(
                '%s/%s_%s.pdf',
                $storagePath,
                \Illuminate\Support\Str::slug($composition->slug),
                $document->id
            );

            Storage::disk($disk)->put($filename, $pdfContent);

            $document->file_path = $filename;
            $document->file_size = strlen($pdfContent);
            $document->status = 'done';
            $document->generated_at = now();
            $document->save();

            return $document;

        } catch (\Exception $e) {
            $document->status = 'failed';
            $document->error_message = $e->getMessage();
            $document->save();

            throw PdfGenerationException::domPdfError($e->getMessage());
        }
    }

    /**
     * Build a single HTML document from multiple layout results.
     *
     * Extracts <body> content from each layout and combines them with
     * page-break-after between sections. Styles are merged from all pages;
     * when pages have different dimensions/margins, each section gets
     * its own scoped body-like wrapper.
     */
    private function buildCompositionHtml(array $layoutResults): string
    {
        $allStyles = [];
        $pendingSections = [];

        foreach ($layoutResults as $layoutResult) {
            // Strip body CSS rules — margins/width se aplican por sección via
            // .pdf-composition-page, no al <body> del documento combinado.
            $cleanStyle = preg_replace('/body\s*\{[^}]*\}/s', '', $layoutResult->headStyle());
            $allStyles[] = $cleanStyle;
            $body = $layoutResult->bodyContent();

            // Skip pages with no rendered content — they'd create blank PDF pages
            if (trim($body) === '' && $layoutResult->elementCount === 0) {
                continue;
            }

            $margins = $layoutResult->margins();
            $pageWidth = $layoutResult->page['width'] ?? 210;
            $usableWidth = $pageWidth - ($margins['left'] ?? 10) - ($margins['right'] ?? 10);

            $pendingSections[] = [
                'body' => $body,
                'usableWidth' => $usableWidth,
                'marginTop' => (float) ($margins['top'] ?? 10),
                'marginRight' => (float) ($margins['right'] ?? 10),
                'marginBottom' => (float) ($margins['bottom'] ?? 10),
                'marginLeft' => (float) ($margins['left'] ?? 10),
            ];
        }

        $lastSectionIndex = count($pendingSections) - 1;
        $htmlSections = [];

        foreach ($pendingSections as $index => $section) {
            $pageBreak = $index < $lastSectionIndex
                ? ' page-break-after: always;'
                : '';

            $htmlSections[] = '<div class="pdf-composition-page" style="width: '
                . $section['usableWidth'] . 'mm; margin: '
                . $section['marginTop'] . 'mm ' . $section['marginRight'] . 'mm '
                . $section['marginBottom'] . 'mm ' . $section['marginLeft'] . 'mm;'
                . $pageBreak . '">' . "\n"
                . $section['body'] . "\n"
                . '</div>';
        }

        $mergedStyles = implode("\n", array_unique($allStyles));
        $bodyHtml = implode("\n", $htmlSections);

        // Body global: solo tipografía, SIN width/margin (los maneja cada .pdf-composition-page)
        $compositionBodyCss = <<<CSS
body {
    font-family: 'DejaVu Sans', sans-serif;
    font-size: 10pt;
    line-height: 1.4;
    color: #000;
    margin: 0;
    padding: 0;
}
CSS;

        return "<!DOCTYPE html>\n<html>\n<head>\n"
            . "<meta charset=\"utf-8\">\n"
            . "<title>Composition</title>\n"
            . "<style>\n" . $compositionBodyCss . "\n" . $mergedStyles . "\n</style>\n"
            . "</head>\n<body>\n"
            . $bodyHtml . "\n"
            . "</body>\n</html>";
    }

    /**
     * Stream the PDF content for a given document.
     *
     * @param PdfDocument $document
     * @return Response
     * @throws PdfGenerationException
     */
    public function download(PdfDocument $document): Response
    {
        if (!$document->isAvailable()) {
            throw PdfGenerationException::renderFailed(
                "Document #{$document->id} is not available (status: {$document->status})."
            );
        }

        $disk = config('pdf-designer.storage.disk', 'local');

        if (!Storage::disk($disk)->exists($document->file_path)) {
            throw PdfGenerationException::storageError(
                "File not found at path: {$document->file_path}"
            );
        }

        $content = Storage::disk($disk)->get($document->file_path);

        return response()->make($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$document->title}.pdf\"",
        ]);
    }
}
