<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Toolreport\Core\Http\Requests\StorePdfTemplateRequest;
use Toolreport\Core\Http\Requests\UpdatePdfTemplateRequest;
use Toolreport\Core\Http\Resources\PdfTemplateResource;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Services\PdfTemplateService;

class PdfTemplateController extends Controller
{
    public function __construct(
        private readonly PdfTemplateService $templateService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $templates = $this->templateService->list(
            $request->only(['per_page', 'active'])
        );

        return response()->json([
            'data' => PdfTemplateResource::collection($templates),
            'meta' => [
                'current_page' => $templates->currentPage(),
                'last_page' => $templates->lastPage(),
                'per_page' => $templates->perPage(),
                'total' => $templates->total(),
            ],
        ]);
    }

    public function store(StorePdfTemplateRequest $request): JsonResponse
    {
        $template = $this->templateService->create($request->validated());

        return response()->json([
            'data' => new PdfTemplateResource($template),
            'message' => 'Template created successfully.',
        ], Response::HTTP_CREATED);
    }

    public function show(PdfTemplate $pdfTemplate): JsonResponse
    {
        $template = $this->templateService->show($pdfTemplate);

        return response()->json([
            'data' => new PdfTemplateResource($template),
        ]);
    }

    public function update(UpdatePdfTemplateRequest $request, PdfTemplate $pdfTemplate): JsonResponse
    {
        $template = $this->templateService->update($pdfTemplate, $request->validated());

        return response()->json([
            'data' => new PdfTemplateResource($template),
            'message' => 'Template updated successfully.',
        ]);
    }

    public function destroy(PdfTemplate $pdfTemplate): JsonResponse
    {
        $this->templateService->delete($pdfTemplate);

        return response()->json([
            'message' => 'Template deleted successfully.',
        ]);
    }

    public function duplicate(PdfTemplate $pdfTemplate): JsonResponse
    {
        $duplicate = $this->templateService->duplicate($pdfTemplate);

        return response()->json([
            'data' => new PdfTemplateResource($duplicate),
            'message' => 'Template duplicated successfully.',
        ], Response::HTTP_CREATED);
    }
}
