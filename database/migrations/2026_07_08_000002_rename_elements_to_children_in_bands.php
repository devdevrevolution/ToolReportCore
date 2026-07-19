<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rename `elements` → `children` inside `page.bands[*]` of every pdf_template.
 *
 * The DOMPDF engine previously stored band-level elements under the key
 * `elements`; to normalise the storage shape across both engines (DOMPDF
 * and PDF-Engine both now use `children`), this migration rewrites the
 * JSON in place. A symmetric fallback is kept in the frontend
 * `loadTemplate` so templates that skip this migration still load.
 */
return new class () extends Migration {
    public function up(): void
    {
        $templates = DB::table('pdf_templates')->get(['id', 'page']);

        foreach ($templates as $template) {
            $page = $template->page;

            if ($page === null) {
                continue;
            }

            // `page` is stored as a JSON string in some drivers; normalise to array.
            if (is_string($page)) {
                $page = json_decode($page, true);
                if (!is_array($page)) {
                    continue;
                }
            }

            $bands = $page['bands'] ?? null;
            if (!is_array($bands)) {
                continue;
            }

            $changed = false;
            foreach ($bands as &$band) {
                if (!is_array($band)) {
                    continue;
                }

                // Skip if `children` already exists (forward-compatible).
                if (array_key_exists('children', $band)) {
                    continue;
                }

                // Move `elements` → `children` when present.
                if (array_key_exists('elements', $band)) {
                    $band['children'] = $band['elements'];
                    unset($band['elements']);
                    $changed = true;
                }
            }
            unset($band);

            if ($changed) {
                DB::table('pdf_templates')
                    ->where('id', $template->id)
                    ->update(['page' => json_encode($page)]);
            }
        }
    }

    public function down(): void
    {
        $templates = DB::table('pdf_templates')->get(['id', 'page']);

        foreach ($templates as $template) {
            $page = $template->page;

            if ($page === null) {
                continue;
            }

            if (is_string($page)) {
                $page = json_decode($page, true);
                if (!is_array($page)) {
                    continue;
                }
            }

            $bands = $page['bands'] ?? null;
            if (!is_array($bands)) {
                continue;
            }

            // Only revert bands that look like DOMPDF children (list of designer
            // element dicts with a `type` and `x`/`y`). PDF-Engine CompositeRoot
            // entries also have `node` — we leave those untouched on rollback.
            $changed = false;
            foreach ($bands as &$band) {
                if (!is_array($band)) {
                    continue;
                }
                if (!array_key_exists('children', $band)) {
                    continue;
                }
                $children = $band['children'];
                if (!is_array($children)) {
                    continue;
                }

                $isDomPdfSet = true;
                foreach ($children as $child) {
                    if (!is_array($child)) {
                        $isDomPdfSet = false;
                        break;
                    }
                    if (isset($child['node'])) {
                        // CompositeRoot shape — leave alone.
                        $isDomPdfSet = false;
                        break;
                    }
                }

                if ($isDomPdfSet) {
                    $band['elements'] = $band['children'];
                    unset($band['children']);
                    $changed = true;
                }
            }
            unset($band);

            if ($changed) {
                DB::table('pdf_templates')
                    ->where('id', $template->id)
                    ->update(['page' => json_encode($page)]);
            }
        }
    }
};