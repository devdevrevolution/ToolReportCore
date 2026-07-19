<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Toolreport\Core\Http\Requests\StoreTemplateVarRequest;
use Toolreport\Core\Http\Requests\UpdateTemplateVarRequest;
use Toolreport\Core\Http\Resources\TemplateVarResource;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\TemplateVar;
use Toolreport\Core\Services\TemplateVarService;

class TemplateVarController extends Controller
{
    public function __construct(
        private readonly TemplateVarService $templateVarService,
    ) {}

    /**
     * List all template_vars for a template.
     */
    public function index(PdfTemplate $pdfTemplate): JsonResponse
    {
        $vars = $this->templateVarService->fetchForTemplate($pdfTemplate);

        return response()->json([
            'data' => TemplateVarResource::collection($vars),
        ]);
    }

    /**
     * Create a new template_var for a template.
     */
    public function store(StoreTemplateVarRequest $request, PdfTemplate $pdfTemplate): JsonResponse
    {
        $var = $pdfTemplate->templateVars()->create($request->validated());

        return response()->json([
            'data' => new TemplateVarResource($var),
            'message' => 'Variable created successfully.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing template_var.
     */
    public function update(UpdateTemplateVarRequest $request, PdfTemplate $pdfTemplate, TemplateVar $templateVar): JsonResponse
    {
        // Ensure the template_var belongs to this template
        if ($templateVar->pdf_template_id !== $pdfTemplate->id) {
            return response()->json([
                'message' => 'Variable not found for this template.',
            ], Response::HTTP_NOT_FOUND);
        }

        $validated = $request->validated();

        // If value is masked (***), don't overwrite the existing value
        if (($validated['value'] ?? null) === '***') {
            unset($validated['value']);
        }

        $templateVar->update($validated);

        return response()->json([
            'data' => new TemplateVarResource($templateVar->fresh()),
            'message' => 'Variable updated successfully.',
        ]);
    }

    /**
     * Delete a template_var.
     */
    public function destroy(PdfTemplate $pdfTemplate, TemplateVar $templateVar): JsonResponse
    {
        if ($templateVar->pdf_template_id !== $pdfTemplate->id) {
            return response()->json([
                'message' => 'Variable not found for this template.',
            ], Response::HTTP_NOT_FOUND);
        }

        $templateVar->delete();

        return response()->json([
            'message' => 'Variable deleted successfully.',
        ]);
    }
}
