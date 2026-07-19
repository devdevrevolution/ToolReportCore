<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

use Illuminate\Support\Collection;
use Toolreport\Core\Models\PdfTemplate;
use Toolreport\Core\Models\TemplateVar;

class TemplateVarService
{
    /**
     * Fetch all template_vars for a template.
     */
    public function fetchForTemplate(PdfTemplate $template): Collection
    {
        return $template->templateVars()->orderBy('name')->get();
    }

    /**
     * Resolve {{var}} placeholders in a string using resolved vars map.
     *
     * Unresolved placeholders are left as-is (never silently removed).
     * Variable names match: word chars only, e.g. {{api_token}}, {{company_id}}.
     *
     * @param  array<string, string>  $resolvedVars
     */
    public function resolve(string $text, array $resolvedVars): string
    {
        if (empty($resolvedVars) || $text === '') {
            return $text;
        }

        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            function (array $matches) use ($resolvedVars): string {
                $varName = $matches[1];

                return $resolvedVars[$varName] ?? $matches[0];
            },
            $text,
        ) ?? $text;
    }

    /**
     * Resolve {{var}} placeholders in an array values (for headers, etc.).
     *
     * Keys are preserved as-is; only string values are resolved.
     *
     * @param  array<string, string>  $arr
     * @param  array<string, string>  $resolvedVars
     * @return array<string, string>
     */
    public function resolveArray(array $arr, array $resolvedVars): array
    {
        if (empty($resolvedVars) || $arr === []) {
            return $arr;
        }

        $result = [];
        foreach ($arr as $key => $value) {
            $result[$key] = $this->resolve((string) $value, $resolvedVars);
        }

        return $result;
    }

    /**
     * Validate client data against template's template_vars.
     *
     * Rules:
     *  - Required public vars MUST be present in client data (or have a default)
     *  - Client keys that match private vars are silently ignored (no error)
     *  - Unknown client keys are silently ignored (forward-compatible)
     *
     * @param  array<string, mixed>  $clientData
     * @return array<string, string[]>|null  Validation errors keyed by field, or null if valid
     */
    public function validateClientData(PdfTemplate $template, array $clientData): ?array
    {
        $templateVars = $this->fetchForTemplate($template);
        $errors = [];

        $requiredPublic = $templateVars->filter(
            fn (TemplateVar $v) => $v->visibility === 'public' && $v->is_required,
        );

        foreach ($requiredPublic as $templateVar) {
            $key = $templateVar->name;

            // If client sent it, it's valid
            if (array_key_exists($key, $clientData)) {
                continue;
            }

            // If the template_var has a default value, it's also fine
            if ($templateVar->value !== null && $templateVar->value !== '') {
                continue;
            }

            // Missing required variable with no default
            $description = $templateVar->description ? " ({$templateVar->description})" : '';
            $errors["data.{$key}"] = [
                "The {$key} variable is required{$description}.",
            ];
        }

        return empty($errors) ? null : $errors;
    }

    /**
     * Merge private vars (server-only) + public vars (client overrides or defaults).
     *
     * Private vars ALWAYS come from the template_vars table.
     * Public vars: client value > template_vars default.
     *
     * @param  array<string, mixed>  $clientData
     * @return array<string, string>
     */
    public function mergeVariables(PdfTemplate $template, array $clientData): array
    {
        $templateVars = $this->fetchForTemplate($template);

        $resolved = [];

        // 1. Private variables: always from server, client cannot override
        foreach ($templateVars->filter(fn (TemplateVar $v) => $v->visibility === 'private') as $templateVar) {
            $resolved[$templateVar->name] = $templateVar->value;
        }

        // 2. Public variables: client value if provided, else default from template_vars
        foreach ($templateVars->filter(fn (TemplateVar $v) => $v->visibility === 'public') as $templateVar) {
            $clientValue = $clientData[$templateVar->name] ?? null;

            if ($clientValue !== null) {
                // Client sent a value — use it (it's a public var, override is allowed)
                $resolved[$templateVar->name] = (string) $clientValue;
            } elseif ($templateVar->value !== null && $templateVar->value !== '') {
                // Client didn't send it — use default from template_vars
                $resolved[$templateVar->name] = $templateVar->value;
            }
            // If no client value AND no default, skip (required check already done in validateClientData)
        }

        return $resolved;
    }
}
