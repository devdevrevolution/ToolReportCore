<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Toolreport\Core\Http\Requests\StoreCompositionRequest;
use Toolreport\Core\Http\Requests\UpdateCompositionRequest;
use Toolreport\Core\Http\Resources\CompositionResource;
use Toolreport\Core\Models\ReportComposition;
use Toolreport\Core\Services\ReportCompositionService;

class CompositionController extends Controller
{
    public function __construct(
        private readonly ReportCompositionService $compositionService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $compositions = $this->compositionService->list(
            $request->only(['per_page', 'sort', 'order', 'search'])
        );

        return response()->json([
            'data' => CompositionResource::collection($compositions),
            'meta' => [
                'current_page' => $compositions->currentPage(),
                'last_page' => $compositions->lastPage(),
                'per_page' => $compositions->perPage(),
                'total' => $compositions->total(),
            ],
        ]);
    }

    public function store(StoreCompositionRequest $request): JsonResponse
    {
        $composition = $this->compositionService->create(
            $request->safe()->except('pages'),
            $request->input('pages', []),
        );

        return response()->json([
            'data' => new CompositionResource($composition),
            'message' => 'Composición creada exitosamente.',
        ], Response::HTTP_CREATED);
    }

    public function show(ReportComposition $composition): JsonResponse
    {
        $composition->load('pages.template');

        return response()->json([
            'data' => new CompositionResource($composition),
        ]);
    }

    public function update(UpdateCompositionRequest $request, ReportComposition $composition): JsonResponse
    {
        $composition = $this->compositionService->update(
            $composition,
            $request->safe()->except('pages'),
            $request->has('pages') ? $request->input('pages') : null,
        );

        return response()->json([
            'data' => new CompositionResource($composition),
            'message' => 'Composición actualizada exitosamente.',
        ]);
    }

    public function destroy(ReportComposition $composition): JsonResponse
    {
        $this->compositionService->delete($composition);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
