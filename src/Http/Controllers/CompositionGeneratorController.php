<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Toolreport\Core\Exceptions\InvalidLayoutException;
use Toolreport\Core\Exceptions\PdfGenerationException;
use Toolreport\Core\Http\Requests\GenerateCompositionRequest;
use Toolreport\Core\Http\Resources\PdfDocumentResource;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Services\PdfRenderingService;
use Toolreport\Core\Services\TemplateVarService;

class CompositionGeneratorController extends Controller
{
    public function __construct(
        private readonly PdfRenderingService $pdfRenderingService,
        private readonly TemplateVarService $templateVarService,
    ) {}

    public function generate(GenerateCompositionRequest $request, ReportComposition $composition): JsonResponse
    {
        try {
            $composition->load('pages.template');

            $clientData = $request->input('data', []);

            // Collect all template vars from all templates in the composition and validate + merge
            $resolvedData = $clientData;
            $allTemplateVars = [];

            foreach ($composition->pages as $page) {
                if ($page->template === null) {
                    continue;
                }

                $errors = $this->templateVarService->validateClientData($page->template, $clientData);
                if (!empty($errors)) {
                    throw ValidationException::withMessages([
                        'data' => collect($errors)->mapWithKeys(fn ($msg, $key) => ["{$key}" => [$msg]])->all(),
                    ]);
                }

                $merged = $this->templateVarService->mergeVariables($page->template, $clientData);
                $allTemplateVars = array_merge($allTemplateVars, $merged);
            }

            $resolvedData = $allTemplateVars;

            $document = $this->pdfRenderingService->renderComposition(
                $composition,
                $resolvedData,
                $request->input('title'),
            );

            return response()->json([
                'data' => new PdfDocumentResource($document),
                'message' => 'PDF generado exitosamente.',
            ], Response::HTTP_CREATED);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (InvalidLayoutException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors' => ['layout' => [$e->getMessage()]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);

        } catch (PdfGenerationException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'error' => 'pdf_generation_failed',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
