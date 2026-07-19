<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Toolreport\Core\Models\PdfTemplate;

class DatasourceProxyService
{
    public function __construct(
        private readonly FieldDiscoveryService $fieldDiscoveryService,
        private readonly TemplateVarService $templateVarService,
    ) {}

    /**
     * Test a datasource by proxying an HTTP request and discovering fields.
     *
     * If a template is provided, env_vars are resolved in URL/headers/auth
     * before making the HTTP request.
     *
     * @param  array{url: string, method: string, timeout: int, auth?: array{type: string, token?: string}, headers?: array<string, string>, id?: string}  $datasource
     * @return array{success: bool, error?: string, status?: int, fields?: array, response_preview?: string}
     */
    public function test(array $datasource, ?PdfTemplate $template = null): array
    {
        $url = $datasource['url'];

        // Resolve env_vars if template is provided
        $resolvedVars = [];
        if ($template) {
            $resolvedVars = $this->templateVarService->mergeVariables($template, []);
        }

        if (!empty($resolvedVars)) {
            $url = $this->templateVarService->resolve($url, $resolvedVars);
        }

        // SSRF Protection: reject URLs pointing to private/internal networks
        $ssrfError = $this->checkSsrf($url);
        if ($ssrfError !== null) {
            return [
                'success' => false,
                'error' => $ssrfError,
            ];
        }

        try {
            // Build HTTP client
            $httpClient = Http::timeout($datasource['timeout'])
                ->withOptions([
                    'verify' => true,
                    'connect_timeout' => $datasource['timeout'],
                ]);

            // Apply authentication (with resolved vars)
            if (isset($datasource['auth']) && $datasource['auth']['type'] === 'bearer') {
                $token = $datasource['auth']['token'] ?? '';
                if (!empty($resolvedVars)) {
                    $token = $this->templateVarService->resolve($token, $resolvedVars);
                }
                $httpClient = $httpClient->withToken($token);
            }

            // Apply custom headers (with resolved vars)
            $headers = $datasource['headers'] ?? [];
            if (!empty($resolvedVars) && !empty($headers)) {
                $headers = $this->templateVarService->resolveArray($headers, $resolvedVars);
            }
            if (!empty($headers)) {
                $httpClient = $httpClient->withHeaders($headers);
            }

            // Execute request
            $method = strtolower($datasource['method']);
            $response = $httpClient->$method($url);

            if ($response->failed()) {
                $statusCode = $response->status();

                $errorMessage = match (true) {
                    $statusCode >= 500 => "Remote server error (HTTP {$statusCode})",
                    $statusCode >= 400 => "Remote server returned HTTP {$statusCode}",
                    default => "Request failed with HTTP {$statusCode}",
                };

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'status' => $statusCode,
                ];
            }

            // Try to parse JSON
            $body = $response->body();
            $decoded = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Response is not valid JSON',
                    'status' => $response->status(),
                ];
            }

            // Discover fields — link each field back to the datasource
            $fields = $this->fieldDiscoveryService->discover(
                is_array($decoded) ? $decoded : [$decoded],
                $datasource['id'] ?? '',
            );

            // Truncate response preview to 500 chars
            $preview = mb_substr($body, 0, 500);

            return [
                'success' => true,
                'status' => $response->status(),
                'fields' => $fields,
                'response_preview' => $preview,
            ];

        } catch (ConnectionException $e) {
            return [
                'success' => false,
                'error' => 'Connection failed: ' . $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check a URL for SSRF-susceptible targets (private IP ranges).
     *
     * Returns an error message string if the URL is blocked, or null if allowed.
     */
    private function checkSsrf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        if ($host === false || $host === null || $host === '') {
            return 'Invalid URL: could not parse host.';
        }

        // Check well-known private hostnames
        $privateHosts = [
            'localhost',
            '127.0.0.1',
            '::1',
            '0.0.0.0',
        ];

        if (in_array(strtolower($host), $privateHosts, true)) {
            return 'SSRF protection: requests to localhost/internal hosts are not allowed.';
        }

        // Check if host is an IP address
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($host)) {
                return 'SSRF protection: requests to private IP ranges are not allowed.';
            }
        }

        // Try to resolve the hostname and check resolved IPs
        $resolvedIps = gethostbynamel($host);

        if ($resolvedIps !== false && count($resolvedIps) > 0) {
            foreach ($resolvedIps as $ip) {
                if ($this->isPrivateIp($ip)) {
                    return 'SSRF protection: the hostname resolves to a private IP address.';
                }
            }
        }

        return null;
    }

    /**
     * Check if an IP address falls within private/reserved ranges.
     */
    private function isPrivateIp(string $ip): bool
    {
        // Check IPv4 private ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            // 10.0.0.0/8
            if ((int) $parts[0] === 10) {
                return true;
            }

            // 172.16.0.0/12
            if ((int) $parts[0] === 172 && (int) $parts[1] >= 16 && (int) $parts[1] <= 31) {
                return true;
            }

            // 192.168.0.0/16
            if ((int) $parts[0] === 192 && (int) $parts[1] === 168) {
                return true;
            }

            // 127.0.0.0/8
            if ((int) $parts[0] === 127) {
                return true;
            }
        }

        // Check IPv6 loopback
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $normalized = strtolower($ip);

            // ::1 (IPv6 loopback)
            if ($normalized === '::1') {
                return true;
            }

            // Check if it's in the fc00::/7 range (Unique Local Address)
            if (str_starts_with($normalized, 'fc') || str_starts_with($normalized, 'fd')) {
                return true;
            }
        }

        return false;
    }
}
