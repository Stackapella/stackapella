<?php

declare(strict_types=1);

namespace App\Support\Contract;

use RuntimeException;
use stdClass;

/**
 * Checks a JSON value against a schema in the contract's small JSON Schema subset
 * (contract/api.md, "JSON Schema: the subset we use"). No library: about as much code as the
 * subset needs, and the same rules as the app's web/js/schema.js.
 *
 * Values are decoded with objects as stdClass (json_decode($json, false)), so {} and [] stay
 * different. Schemas are decoded as arrays.
 */
final class SchemaChecker
{
    private const KEYWORDS = ['type', 'properties', 'required', 'additionalProperties', 'items', 'enum', 'format', '$ref', '$defs', '$id', 'title', 'description'];

    private const TYPES = ['object', 'array', 'string', 'integer', 'number', 'boolean', 'null'];

    /** @var array<string, array<string, mixed>> */
    private array $files = [];

    public function __construct(private readonly string $schemaDirectory) {}

    /**
     * @return list<string> what's wrong, each with its path ($.data.role); empty when it passes
     */
    public function check(mixed $value, string $schemaFile): array
    {
        return $this->checkAgainst($value, $this->file($schemaFile), $schemaFile, '$');
    }

    /**
     * Problems in the schema itself: keywords outside the subset, unknown types, broken refs.
     *
     * @return list<string>
     */
    public function lint(string $schemaFile): array
    {
        $problems = [];
        $this->lintNode($this->file($schemaFile), $schemaFile, '#', $problems);

        return $problems;
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return list<string>
     */
    private function checkAgainst(mixed $value, array $schema, string $file, string $path): array
    {
        if (isset($schema['$ref'])) {
            [$refFile, $refSchema] = $this->resolve((string) $schema['$ref'], $file);

            return $this->checkAgainst($value, $refSchema, $refFile, $path);
        }

        $errors = [];

        if (isset($schema['type'])) {
            $types = (array) $schema['type'];

            if (! in_array($this->typeOf($value), $types, true) && ! ($this->typeOf($value) === 'integer' && in_array('number', $types, true))) {
                return ["{$path}: expected ".implode(' or ', $types).', got '.$this->typeOf($value)];
            }
        }

        if (isset($schema['enum']) && ! in_array($value, (array) $schema['enum'], true)) {
            $errors[] = "{$path}: ".json_encode($value).' is not one of '.json_encode($schema['enum']);
        }

        if (isset($schema['format']) && is_string($value)) {
            $errors = [...$errors, ...$this->checkFormat($value, (string) $schema['format'], $path)];
        }

        if ($value instanceof stdClass) {
            $fields = get_object_vars($value);
            /** @var array<string, array<string, mixed>> $properties */
            $properties = (array) ($schema['properties'] ?? []);

            foreach ((array) ($schema['required'] ?? []) as $required) {
                if (! array_key_exists((string) $required, $fields)) {
                    $errors[] = "{$path}: missing the required field \"{$required}\"";
                }
            }

            foreach ($fields as $name => $fieldValue) {
                if (isset($properties[$name])) {
                    $errors = [...$errors, ...$this->checkAgainst($fieldValue, $properties[$name], $file, "{$path}.{$name}")];
                } elseif (($schema['additionalProperties'] ?? true) === false) {
                    $errors[] = "{$path}: the field \"{$name}\" isn't in the schema";
                }
            }
        }

        if (is_array($value) && isset($schema['items'])) {
            /** @var array<string, mixed> $items */
            $items = $schema['items'];
            foreach ($value as $index => $item) {
                $errors = [...$errors, ...$this->checkAgainst($item, $items, $file, "{$path}[{$index}]")];
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function checkFormat(string $value, string $format, string $path): array
    {
        $ok = match ($format) {
            // ISO 8601 with a time and a time zone (Z or an offset).
            'date-time' => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/', $value) === 1 && strtotime($value) !== false,
            'uri' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => throw new RuntimeException("Unknown format [{$format}]."),
        };

        return $ok ? [] : ["{$path}: \"{$value}\" is not a {$format}"];
    }

    private function typeOf(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_float($value) => fmod($value, 1.0) === 0.0 ? 'integer' : 'number',
            is_string($value) => 'string',
            $value instanceof stdClass => 'object',
            is_array($value) => 'array',
            default => get_debug_type($value),
        };
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function resolve(string $ref, string $fromFile): array
    {
        [$refFile, $pointer] = str_contains($ref, '#') ? explode('#', $ref, 2) : [$ref, ''];
        $refFile = $refFile === '' ? $fromFile : $refFile;
        $schema = $this->file($refFile);

        if ($pointer !== '') {
            foreach (array_filter(explode('/', $pointer)) as $part) {
                if (! isset($schema[$part]) || ! is_array($schema[$part])) {
                    throw new RuntimeException("The \$ref [{$ref}] in {$fromFile} points at nothing.");
                }
                $schema = $schema[$part];
            }
        }

        /** @var array<string, mixed> $schema */
        return [$refFile, $schema];
    }

    /**
     * @return array<string, mixed>
     */
    private function file(string $name): array
    {
        if (isset($this->files[$name])) {
            return $this->files[$name];
        }

        $path = $this->schemaDirectory.'/'.$name;
        $schema = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($schema)) {
            throw new RuntimeException("The schema [{$name}] is missing or isn't valid JSON.");
        }

        /** @var array<string, mixed> $schema */
        return $this->files[$name] = $schema;
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $problems
     */
    private function lintNode(array $node, string $file, string $at, array &$problems): void
    {
        foreach (array_keys($node) as $keyword) {
            if (! in_array($keyword, self::KEYWORDS, true)) {
                $problems[] = "{$file} {$at}: \"{$keyword}\" isn't in the contract's JSON Schema subset";
            }
        }

        foreach ((array) ($node['type'] ?? []) as $type) {
            if (! in_array($type, self::TYPES, true)) {
                $problems[] = "{$file} {$at}: unknown type \"{$type}\"";
            }
        }

        if (isset($node['additionalProperties']) && $node['additionalProperties'] !== false) {
            $problems[] = "{$file} {$at}: additionalProperties may only be false";
        }

        if (isset($node['format']) && ! in_array($node['format'], ['date-time', 'uri'], true)) {
            $problems[] = "{$file} {$at}: unknown format \"{$node['format']}\"";
        }

        if (isset($node['$ref'])) {
            try {
                $this->resolve((string) $node['$ref'], $file);
            } catch (RuntimeException $e) {
                $problems[] = $e->getMessage();
            }
        }

        foreach (['properties', '$defs'] as $group) {
            foreach ((array) ($node[$group] ?? []) as $name => $child) {
                if (is_array($child)) {
                    $this->lintNode($child, $file, "{$at}/{$group}/{$name}", $problems);
                }
            }
        }

        if (isset($node['items']) && is_array($node['items'])) {
            $this->lintNode($node['items'], $file, "{$at}/items", $problems);
        }
    }
}
