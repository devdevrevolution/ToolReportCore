// ──────────────────────────────────────────────
// Toolreport Designer — TemplateVars Pinia Store
// Manages template variables for the current template.
// ──────────────────────────────────────────────

import { defineStore } from 'pinia'
import type {
    TemplateVar,
    CreateTemplateVarPayload,
    UpdateTemplateVarPayload,
    ApiClient,
} from '@/api/types'

export interface TemplateVarsState {
    /** Template vars for the current template */
    items: TemplateVar[]
    /** Loading state for fetch operations */
    isLoading: boolean
    /** Last error message */
    error: string | null
}

export const useTemplateVarsStore = defineStore('store/templateVars', {
    state: (): TemplateVarsState => ({
        items: [],
        isLoading: false,
        error: null,
    }),

    getters: {
        /** Public vars (can be sent by client) */
        publicVars: (state): TemplateVar[] => {
            return state.items.filter(v => v.visibility === 'public')
        },

        /** Private vars (server-only, secrets) */
        privateVars: (state): TemplateVar[] => {
            return state.items.filter(v => v.visibility === 'private')
        },

        /** Required public vars that have no default value */
        missingRequired: (state): TemplateVar[] => {
            return state.items.filter(
                v => v.visibility === 'public' && v.is_required && (!v.value || v.value.trim() === ''),
            )
        },

        /** Get template var by name */
        getByName:
            (state) =>
            (name: string): TemplateVar | undefined => {
                return state.items.find(v => v.name === name)
            },
    },

    actions: {
        /**
         * Fetch all template vars for the given template.
         * @param api - The API client instance
         * @param templateId - The template ID to load vars for
         */
        async fetch(api: ApiClient, templateId: number): Promise<void> {
            this.isLoading = true
            this.error = null
            try {
                this.items = await api.getTemplateVars(templateId)
            } catch (e: unknown) {
                const message = e instanceof Error ? e.message : 'Failed to load template vars'
                this.error = message
                throw e
            } finally {
                this.isLoading = false
            }
        },

        /**
         * Create a new template var for the given template.
         */
        async create(
            api: ApiClient,
            templateId: number,
            payload: CreateTemplateVarPayload,
        ): Promise<TemplateVar> {
            this.error = null
            try {
                const created = await api.createTemplateVar(templateId, payload)
                this.items.push(created)
                return created
            } catch (e: unknown) {
                const message = e instanceof Error ? e.message : 'Failed to create template var'
                this.error = message
                throw e
            }
        },

        /**
         * Update an existing template var.
         */
        async update(
            api: ApiClient,
            templateId: number,
            templateVarId: number,
            payload: UpdateTemplateVarPayload,
        ): Promise<TemplateVar> {
            this.error = null
            try {
                const updated = await api.updateTemplateVar(templateId, templateVarId, payload)
                const idx = this.items.findIndex(v => v.id === templateVarId)
                if (idx !== -1) {
                    this.items[idx] = updated
                }
                return updated
            } catch (e: unknown) {
                const message = e instanceof Error ? e.message : 'Failed to update template var'
                this.error = message
                throw e
            }
        },

        /**
         * Delete a template var.
         */
        async remove(
            api: ApiClient,
            templateId: number,
            templateVarId: number,
        ): Promise<void> {
            this.error = null
            try {
                await api.deleteTemplateVar(templateId, templateVarId)
                this.items = this.items.filter(v => v.id !== templateVarId)
            } catch (e: unknown) {
                const message = e instanceof Error ? e.message : 'Failed to delete template var'
                this.error = message
                throw e
            }
        },

        /**
         * Clear all state (e.g. when navigating away from template).
         */
        reset(): void {
            this.items = []
            this.isLoading = false
            this.error = null
        },
    },
})
