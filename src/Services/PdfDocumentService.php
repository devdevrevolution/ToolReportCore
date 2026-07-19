<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Pdf\PdfGenerator;

class PdfDocumentService
{
    public function __construct(
        private readonly PdfGenerator $pdfGenerator,
    ) {}

    /**
     * List documents for a template, most recent first.
     */
    public function listForTemplate(PdfTemplate $template): LengthAwarePaginator
    {
        return $template->documents()
            ->orderBy('created_at', 'desc')
            ->paginate();
    }

    /**
     * Show a single document (no transforms needed).
     */
    public function show(PdfDocument $document): PdfDocument
    {
        return $document;
    }

    /**
     * Download a generated PDF document.
     *
     * @return Response|JsonResponse
     */
    public function download(PdfDocument $document): Response|JsonResponse
    {
        try {
            return $this->pdfGenerator->download($document);
        } catch (PdfGenerationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'download_failed',
            ], Response::HTTP_NOT_FOUND);
        }
    }
}
