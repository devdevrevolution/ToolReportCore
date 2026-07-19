<?php

declare(strict_types=1);

namespace Toolreport\Core\Services;

class FieldDiscoveryService
{
    private const MAX_FIELDS = 1000;

    /**
     * Recursively introspect a decoded JSON array to discover fields.
     *
     * @param  array<mixed>  $data  The JSON-decoded response body
     * @param  string  $datasourceId  ID linking back to the source DatasourceConfig
     * @param  int  $maxDepth  Maximum recursion depth (default: 5)
     * @return array<int, array{name: string, path: string, type: string, level: int, datasourceId: string}>
     */
    public function discover(array $data, string $datasourceId, int $maxDepth = 5): array
    {
        $fields = [];

        // Handle root-level indexed array (e.g., [{"id": 1}, ...])
        if ($this->isIndexedArray($data) && !empty($data) && is_array($data[0])) {
            $this->discoverInternal($data[0], '[]', 0, $datasourceId, $maxDepth, $fields);

            return $fields;
        }

        $this->discoverInternal($data, '', 0, $datasourceId, $maxDepth, $fields);

        return $fields;
    }

    /**
     * @param  array<mixed>  $data
     * @param  array<int, array{name: string, path: string, type: string, level: int, datasourceId: string}>  $fields
     */
    private function discoverInternal(
        array $data,
        string $prefix,
        int $level,
        string $datasourceId,
        int $maxDepth,
        array &$fields,
    ): void {
        if ($level > $maxDepth) {
            return;
        }

        foreach ($data as $key => $value) {
            if (count($fields) >= self::MAX_FIELDS) {
                return;
            }

            $fieldPath = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $fieldName = $this->toHumanReadable((string) $key);
            $type = gettype($value);

            if ($type === 'array') {
                // Array of objects: introspect first element
                if ($this->isIndexedArray($value) && !empty($value) && is_array($value[0])) {
                    $fields[] = [
                        'name' => $fieldName,
                        'path' => $fieldPath,
                        'type' => 'array',
                        'level' => $level,
                        'datasourceId' => $datasourceId,
                    ];

                    $childPrefix = $fieldPath . '[]';

                    if ($level + 1 <= $maxDepth) {
                        $this->discoverInternal($value[0], $childPrefix, $level + 1, $datasourceId, $maxDepth, $fields);
                    }
                } elseif ($this->isIndexedArray($value) && !empty($value) && !is_array($value[0])) {
                    // Primitive array — don't recurse
                    $fields[] = [
                        'name' => $fieldName,
                        'path' => $fieldPath,
                        'type' => 'array',
                        'level' => $level,
                        'datasourceId' => $datasourceId,
                    ];
                } elseif ($this->isIndexedArray($value) && empty($value)) {
                    // Empty array
                    $fields[] = [
                        'name' => $fieldName,
                        'path' => $fieldPath,
                        'type' => 'array',
                        'level' => $level,
                        'datasourceId' => $datasourceId,
                    ];
                } else {
                    // Associative array (object)
                    $fields[] = [
                        'name' => $fieldName,
                        'path' => $fieldPath,
                        'type' => 'object',
                        'level' => $level,
                        'datasourceId' => $datasourceId,
                    ];

                    if ($level + 1 <= $maxDepth) {
                        $this->discoverInternal($value, $fieldPath, $level + 1, $datasourceId, $maxDepth, $fields);
                    }
                }
            } else {
                // Primitive: string, number, boolean, null
                $fields[] = [
                    'name' => $fieldName,
                    'path' => $fieldPath,
                    'type' => $this->normalizeType($type),
                    'level' => $level,
                    'datasourceId' => $datasourceId,
                ];
            }
        }
    }

    /**
     * Convert a JSON key to a human-readable display name.
     * - snake_case → "Snake Case"
     * - kebab-case → "Kebab Case"
     * - simple → "Simple"
     */
    private function toHumanReadable(string $key): string
    {
        // Replace underscores and hyphens with spaces
        $name = str_replace(['_', '-'], ' ', $key);

        // Title case each word
        return ucwords($name);
    }

    /**
     * Normalize PHP's gettype() return to JSON type names.
     */
    private function normalizeType(string $type): string
    {
        return match ($type) {
            'integer', 'double' => 'number',
            'boolean' => 'boolean',
            'NULL' => 'null',
            default => $type,
        };
    }

    /**
     * Check if an array is a sequential (indexed) array, not associative.
     *
     * @param  array<mixed>  $arr
     */
    private function isIndexedArray(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }

        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
