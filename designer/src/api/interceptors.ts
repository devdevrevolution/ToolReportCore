// ──────────────────────────────────────────────
// Toolreport Designer — Axios Interceptor Layer
// ──────────────────────────────────────────────

import type {
    AxiosInstance,
    AxiosError,
    InternalAxiosRequestConfig,
    AxiosResponse,
} from 'axios'
import type { ApiError } from './types'

// ── Auth Interceptor ───────────────────────────

/**
 * Request interceptor that injects `Authorization: Bearer {token}` header.
 *
 * The `getToken` function is called at request time so the token can
 * rotate without re-creating the client instance.
 *
 * @param client - Axios instance to attach to
 * @param getToken - Lazily-resolved token getter
 */
export function applyAuthInterceptor(
    client: AxiosInstance,
    getToken: () => string | undefined,
): void {
    client.interceptors.request.use(
        (config: InternalAxiosRequestConfig) => {
            const token = getToken()

            if (token) {
                config.headers.set('Authorization', `Bearer ${token}`)
            }

            return config
        },
        (error: AxiosError) => Promise.reject(error),
    )
}

// ── Error Interceptor ──────────────────────────

/**
 * Response error interceptor that normalises every rejection into a
 * typed `ApiError`.  Must be registered **after** the auth interceptor
 * so it runs last in the chain.
 *
 * @param client - Axios instance to attach to
 */
export function applyErrorInterceptor(client: AxiosInstance): void {
    client.interceptors.response.use(
        (response: AxiosResponse) => response,
        (error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>) => {
            const apiError: ApiError = normalizeError(error)
            return Promise.reject(apiError)
        },
    )
}

// ── Logging Interceptor ────────────────────────

/**
 * Request / response interceptors that log API calls to the console in
 * development mode (`import.meta.env.DEV`).
 *
 * This is **opt-in** — call it explicitly when you need tracing.
 *
 * @param client - Axios instance to attach to
 */
export function applyLoggingInterceptor(client: AxiosInstance): void {
    // ── Request logger
    client.interceptors.request.use(
        (config: InternalAxiosRequestConfig) => {
            if (import.meta.env.DEV) {
                console.log(
                    `[API] ${config.method?.toUpperCase() ?? '???'} ${config.url ?? '???'}`,
                )
            }
            return config
        },
        (error: AxiosError) => Promise.reject(error),
    )

    // ── Response logger
    client.interceptors.response.use(
        (response: AxiosResponse) => {
            if (import.meta.env.DEV) {
                const { config, status } = response
                console.log(
                    `[API] ${config.method?.toUpperCase() ?? '???'} ${config.url ?? '???'} → ${status}`,
                )
            }
            return response
        },
        (error: AxiosError) => Promise.reject(error),
    )
}

// ── Convenience ────────────────────────────────

/**
 * Applies the auth + error interceptors in the correct order.
 *
 * Does **not** apply logging — callers opt in via {@link applyLoggingInterceptor}.
 *
 * @param client - Axios instance to attach to
 * @param config - Resolved API configuration
 */
export function applyInterceptors(
    client: AxiosInstance,
    config: { authToken?: string },
): void {
    applyAuthInterceptor(client, () => config.authToken)
    applyErrorInterceptor(client)
}

// ── Internal helpers ───────────────────────────

function normalizeError(
    error: AxiosError<{ message?: string; errors?: Record<string, string[]> }>,
): ApiError {
    if (error.response) {
        return {
            status: error.response.status,
            message: error.response.data?.message ?? error.message,
            errors: error.response.data?.errors,
        }
    }

    if (error.request) {
        // The request was made but no response was received
        return {
            status: 0,
            message: 'Network error',
        }
    }

    return {
        status: 0,
        message: 'Unknown error',
    }
}
