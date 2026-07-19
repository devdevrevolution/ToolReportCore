<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Http\Requests\GeneratePdfRequest;
use Toolreport\Core\Http\Resources\PdfDocumentResource;
use Toolreport\Core\Models\PdfDocument;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Services\PdfDocumentService;
use Toolreport\Core\Services\PdfRenderingService;
use Toolreport\Core\Services\TemplateVarService;

class PdfDocumentController extends Controller
{
    public function __construct(
        private readonly PdfRenderingService $pdfRenderingService,
        private readonly PdfDocumentService $documentService,
        private readonly TemplateVarService $templateVarService,
    ) {}

    /**
     * Generate a PDF from a template.
     *
     * Validates client-provided variables against the template's env_vars,
     * merges private (server) + public (client) variables, then renders
     * with the resolved variable context.
     */
    public function generate(GeneratePdfRequest $request, PdfTemplate $pdfTemplate): JsonResponse
    {
        try {
            $clientData = $request->input('data', []);

            // 1. Validate client data against template's template_vars (required checks)
            $validationErrors = $this->templateVarService->validateClientData($pdfTemplate, $clientData);

            if ($validationErrors !== null) {
                throw ValidationException::withMessages($validationErrors);
            }

            // 2. Merge private vars (server) + public vars (client || default)
            $resolvedVars = $this->templateVarService->mergeVariables($pdfTemplate, $clientData);

            // 3. Render with resolved variables
            $document = $this->pdfRenderingService->renderTemplate(
                $pdfTemplate,
                $resolvedVars,
                $request->input('title', $pdfTemplate->name),
            );

            return response()->json([
                'data' => new PdfDocumentResource($document),
                'message' => 'PDF generated successfully.',
            ], Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (PdfGenerationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'pdf_generation_failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List documents for a template.
     */
    public function index(PdfTemplate $pdfTemplate): JsonResponse
    {
        $documents = $this->documentService->listForTemplate($pdfTemplate);

        return response()->json([
            'data' => PdfDocumentResource::collection($documents),
            'meta' => [
                'current_page' => $documents->currentPage(),
                'last_page' => $documents->lastPage(),
                'total' => $documents->total(),
            ],
        ]);
    }

    /**
     * Download a generated PDF.
     */
    public function download(PdfDocument $pdfDocument): Response|JsonResponse
    {
        return $this->documentService->download($pdfDocument);
    }

    /**
     * Show document details.
     */
    public function show(PdfDocument $pdfDocument): JsonResponse
    {
        return response()->json([
            'data' => new PdfDocumentResource($pdfDocument),
        ]);
    }
}
