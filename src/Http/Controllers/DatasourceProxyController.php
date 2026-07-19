<?php

declare(strict_types=1);

namespace Toolreport\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Toolreport\Core\Http\Requests\DatasourceTestRequest;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Services\DatasourceProxyService;

class DatasourceProxyController extends Controller
{
    public function __construct(
        private readonly DatasourceProxyService $proxyService,
    ) {}

    /**
     * Proxies an external HTTP request to test a datasource configuration,
     * then discovers fields from the JSON response.
     *
     * Passes the template so env_vars are resolved in URL/headers/auth
     * before making the test HTTP request.
     */
    public function test(DatasourceTestRequest $request, PdfTemplate $pdfTemplate): JsonResponse
    {
        $datasource = $request->input('datasource');
        $result = $this->proxyService->test($datasource, $pdfTemplate);

        return response()->json($result, Response::HTTP_OK);
    }
}
