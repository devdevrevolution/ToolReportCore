// ──────────────────────────────────────────────
// Toolreport Designer — Singleton API Client Composable
// ──────────────────────────────────────────────

import { createApiClient } from '@/api/client'
import type { ApiClient, ApiConfig } from '@/api/types'

let clientInstance: ApiClient | null = null

/**
 * Returns the singleton API client instance.
 *
 * Must be called with `config` on the very first invocation, or after
 * having called `provideApiConfig()` beforehand.  Subsequent calls
 * (including calls from other composables) will reuse the same client.
 *
 * @throws If no client has been initialised yet.
 */
export function useApi(config?: ApiConfig): ApiClient {
    if (!clientInstance && config) {
        clientInstance = createApiClient(config)
    }

    if (!clientInstance) {
        throw new Error(
            '[Toolreport Designer] useApi() called without config. ' +
                'Provide config on first call or use provideApiConfig().',
        )
    }

    return clientInstance
}

/**
 * Initialise (or replace) the singleton API client.
 *
 * Call this once when bootstrapping the designer — e.g. inside the
 * host application's boot process or the designer mount function.
 */
export function provideApiConfig(config: ApiConfig): ApiClient {
    clientInstance = createApiClient(config)
    return clientInstance
}
