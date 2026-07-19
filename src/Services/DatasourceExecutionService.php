<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class DatasourceExecutionService
{
    public function __construct(
        private readonly TemplateVarService $templateVarService,
    ) {}

    /**
     * Execute one or more datasources and return a merged data array.
     *
     * Each datasource response is navigated to its collection path (if set)
     * and the first item is extracted so that `{{ name }}` resolves correctly
     * for fields dropped on a detail band.
     *
     * @param  array<int, array{
     *     id?: string,
     *     url: string,
     *     method?: string,
     *     headers?: array<string, string>,
     *     auth?: array{type: string, token?: string},
     *     timeout?: int,
     *     collectionPath?: string|null,
     * }>  $datasources
     * @param  array<string, string>  $resolvedVars  Resolved env_vars for {{var}} interpolation
     * @return array<string, mixed>  Merged data for the LayoutEngine
     */
    public function execute(array $datasources, array $resolvedVars = []): array
    {
        $merged = [];

        foreach ($datasources as $ds) {
            $data = $this->executeSingle($ds, $resolvedVars);

            if ($data === null) {
                continue;
            }

            $collectionPath = $ds['collectionPath'] ?? null;

            if ($collectionPath) {
                $nested = $this->navigateTo($data, $collectionPath);

                if (is_array($nested)) {
                    $item = $this->isIndexedArray($nested) ? ($nested[0] ?? []) : $nested;

                    if (is_array($item)) {
                        $merged = array_merge($merged, $item);
                    }
                }
            } elseif (is_array($data)) {
                $first = $this->isIndexedArray($data) ? ($data[0] ?? $data) : $data;

                if (is_array($first)) {
                    $merged = array_merge($merged, $first);
                }
            }
        }

        return $merged;
    }

    /**
     * Execute datasources preserving collection arrays for band-based rendering.
     *
     * Unlike execute(), this method preserves the full response structure so that
     * resolveCollection() can navigate to the correct nested collection.
     * The collectionPath in each datasource config tells us WHERE in the response
     * to find the iteration data, but we still merge the full response to keep
     * all keys accessible for variable interpolation.
     *
     * Key differences from execute():
     * - Full response structure is preserved (not just the navigated subset)
     * - Collection arrays remain as arrays (no first-item merge)
     * - Key conflicts use last-wins semantics (same as execute())
     *
     * @param  array<int, array{
     *     id?: string,
     *     url: string,
     *     method?: string,
     *     headers?: array<string, string>,
     *     auth?: array{type: string, token?: string},
     *     timeout?: int,
     *     collectionPath?: string|null,
     * }>  $datasources
     * @param  array<string, string>  $resolvedVars  Resolved env_vars for {{var}} interpolation
     * @return array<string, mixed>  Structured data preserving collections for band iteration
     */
    public function executeForRendering(array $datasources, array $resolvedVars = []): array
    {
        $merged = [];

        foreach ($datasources as $ds) {
            $data = $this->executeSingle($ds, $resolvedVars);

            if ($data === null) {
                continue;
            }

            // Always merge the full response to preserve all keys
            // for variable interpolation ({{ client.name }}, etc.)
            // and for resolveCollection() to navigate nested paths.
            $merged = array_replace($merged, $data);
        }

        return $merged;
    }

    /**
     * Execute a single datasource HTTP request.
     *
     * Resolves {{var}} placeholders in URL, headers, and auth token
     * using the provided resolved variables map.
     *
     * @param  array{url: string, method?: string, headers?: array<string, string>, auth?: array{type: string, token?: string}, timeout?: int}  $ds
     * @param  array<string, string>  $resolvedVars
     * @return array<mixed>|null
     */
    private function executeSingle(array $ds, array $resolvedVars = []): ?array
    {
        $url = $ds['url'] ?? '';

        if ($url === '') {
            return null;
        }

        // Resolve {{var}} placeholders in URL
        if (!empty($resolvedVars)) {
            $url = $this->templateVarService->resolve($url, $resolvedVars);
        }

        try {
            $method = strtolower($ds['method'] ?? 'GET');
            $timeout = $ds['timeout'] ?? 30;

            $httpClient = Http::timeout($timeout)
                ->withOptions([
                    'verify' => true,
                    'connect_timeout' => $timeout,
                ]);

            // Resolve {{var}} in auth token
            if (isset($ds['auth']) && ($ds['auth']['type'] ?? '') === 'bearer') {
                $token = $ds['auth']['token'] ?? '';
                if (!empty($resolvedVars)) {
                    $token = $this->templateVarService->resolve($token, $resolvedVars);
                }
                $httpClient = $httpClient->withToken($token);
            }

            // Resolve {{var}} in headers
            if (isset($ds['headers']) && is_array($ds['headers'])) {
                if (!empty($resolvedVars)) {
                    $ds['headers'] = $this->templateVarService->resolveArray($ds['headers'], $resolvedVars);
                }
                $httpClient = $httpClient->withHeaders($ds['headers']);
            }

            $response = $httpClient->$method($url);

            if ($response->failed()) {
                return null;
            }

            $decoded = json_decode($response->body(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }

            return is_array($decoded) ? $decoded : [$decoded];
        } catch (ConnectionException) {
            return null;
        }
    }

    /**
     * Navigate a dot-notation path into an array.
     *
     * @param array<mixed> $data
     * @return array<mixed>|null
     */
    private function navigateTo(array $data, string $path): ?array
    {
        $segments = explode('.', $path);
        $current = $data;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return is_array($current) ? $current : [$current];
    }

    /**
     * Check if an array is a sequential (indexed) array.
     *
     * @param array<mixed> $arr
     */
    private function isIndexedArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
