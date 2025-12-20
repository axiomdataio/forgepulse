<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionNamedType;

/**
 * Action Schema Service
 *
 * Discovers action/service method signatures using PHP reflection
 * to provide schema information for UI and API consumers.
 */
class ActionSchema
{
    /**
     * Get schema information for an action class and method.
     *
     * Priority:
     * 1. Database (allows runtime overrides and external services)
     * 2. Code-based schema (ProvidesSchema interface)
     * 3. Reflection (fallback)
     *
     * @param  string  $class  The action class name
     * @param  string|null  $method  The method name (null for auto-detect)
     * @return array<string, mixed> Schema information
     *
     * @throws \ReflectionException
     */
    public function getSchema(string $class, ?string $method = null): array
    {
        // Priority 1: Check database first
        $dbSchema = $this->getSchemaFromDatabase($class, $method);
        if ($dbSchema) {
            return $dbSchema;
        }

        // Priority 2: Check if class provides its own schema
        if (class_exists($class) && method_exists($class, 'schema')) {
            $schema = $class::schema();

            return $this->normalizeSchema($class, $schema);
        }

        // Priority 3: Use reflection
        if (! class_exists($class)) {
            throw new \RuntimeException("Class not found: {$class}");
        }

        $reflection = new ReflectionClass($class);

        // Auto-detect method if not provided
        if ($method === null) {
            $method = $this->detectMethod($reflection);
        }

        if (! $reflection->hasMethod($method)) {
            throw new \RuntimeException("Method {$method} not found in {$class}");
        }

        $methodReflection = $reflection->getMethod($method);

        // Get metadata from attributes
        $classMetadata = $this->getClassMetadata($reflection);
        $methodMetadata = $this->getMethodMetadata($methodReflection);

        return [
            'class' => $class,
            'method' => $method,
            'name' => $methodMetadata['name'] ?? $classMetadata['name'] ?? $this->generateName($class, $method),
            'description' => $methodMetadata['description'] ?? $classMetadata['description'] ?? $this->getDescription($methodReflection),
            'category' => $classMetadata['category'] ?? 'general',
            'tags' => array_merge($classMetadata['tags'] ?? [], $methodMetadata['tags'] ?? []),
            'recommended_timeout' => $classMetadata['timeout'] ?? null,
            'parameters' => $this->getParametersSchema($methodReflection),
            'return_type' => $this->getReturnTypeSchema($methodReflection),
            'output_fields' => $this->getOutputFields($methodReflection),
            'source' => 'reflection',
        ];
    }

    /**
     * Get schema from database.
     *
     * @param  string  $class  The action class name
     * @param  string|null  $method  The method name
     * @return array<string, mixed>|null Schema or null if not found
     */
    protected function getSchemaFromDatabase(string $class, ?string $method): ?array
    {
        $query = \AlizHarb\ForgePulse\Models\ActionSchema::where('action_class', $class)
            ->where('is_active', true);

        if ($method !== null) {
            $query->where('method', $method);
        }

        $model = $query->first();

        if (! $model) {
            return null;
        }

        // Convert stored schema to internal format
        return $this->normalizeSchema($class, $model->schema + [
            'x-db-id' => $model->id,
            'x-source' => $model->source,
            'x-version' => $model->version,
            'x-is-external' => $model->is_external,
        ]);
    }

    /**
     * Normalize schema from ProvidesSchema interface.
     *
     * Converts OpenAPI 3.0 format to internal format.
     *
     * @param  string  $class  The action class name
     * @param  array<string, mixed>  $schema  OpenAPI schema from action
     * @return array<string, mixed> Normalized schema
     */
    protected function normalizeSchema(string $class, array $schema): array
    {
        // Auto-detect method if not specified
        $method = $schema['method'] ?? null;
        if ($method === null) {
            $reflection = new ReflectionClass($class);
            $method = $this->detectMethod($reflection);
        }

        // Convert OpenAPI parameters to internal format
        $parameters = [];
        foreach ($schema['parameters'] ?? [] as $name => $param) {
            $parameters[] = [
                'name' => $name,
                'type' => $this->normalizeType($param['type'] ?? 'string'),
                'required' => $param['required'] ?? true,
                'default' => $param['default'] ?? null,
                'description' => $param['description'] ?? null,
                'example' => $param['example'] ?? null,
                'validation' => $this->extractValidationRules($param),
                'source' => $param['x-source'] ?? 'config',
                'format' => $param['format'] ?? null,
                'enum' => $param['enum'] ?? null,
            ];
        }

        // Extract output schema from OpenAPI response
        $outputFields = [];
        $response200 = $schema['responses']['200'] ?? $schema['responses']['default'] ?? null;
        if ($response200) {
            $responseSchema = $response200['content']['application/json']['schema'] ?? null;
            if ($responseSchema) {
                $outputFields = $this->extractOutputFields($responseSchema);
            }
        }

        return [
            'class' => $class,
            'method' => $method,
            'name' => $schema['summary'] ?? $this->generateName($class, $method),
            'description' => $schema['description'] ?? null,
            'category' => $schema['x-category'] ?? 'general',
            'tags' => $schema['tags'] ?? [],
            'recommended_timeout' => $schema['x-timeout'] ?? null,
            'operation_id' => $schema['operationId'] ?? null,
            'parameters' => $parameters,
            'return_type' => ['type' => 'array', 'description' => null],
            'output_fields' => $outputFields,
            'openapi_schema' => $schema, // Keep original for export
        ];
    }

    /**
     * Normalize OpenAPI type to PHP type.
     *
     * @param  string  $type  OpenAPI type
     * @return string PHP type
     */
    protected function normalizeType(string $type): string
    {
        return match ($type) {
            'integer' => 'int',
            'number' => 'float',
            'boolean' => 'bool',
            'string', 'array', 'object' => $type,
            default => 'mixed',
        };
    }

    /**
     * Extract validation rules from OpenAPI parameter schema.
     *
     * @param  array<string, mixed>  $param  Parameter schema
     * @return array<string, mixed> Validation rules
     */
    protected function extractValidationRules(array $param): array
    {
        $rules = [];

        // Numeric constraints
        if (isset($param['minimum'])) {
            $rules['min'] = $param['minimum'];
        }
        if (isset($param['maximum'])) {
            $rules['max'] = $param['maximum'];
        }

        // String constraints
        if (isset($param['minLength'])) {
            $rules['min_length'] = $param['minLength'];
        }
        if (isset($param['maxLength'])) {
            $rules['max_length'] = $param['maxLength'];
        }
        if (isset($param['pattern'])) {
            $rules['pattern'] = $param['pattern'];
        }

        // Array constraints
        if (isset($param['minItems'])) {
            $rules['min_items'] = $param['minItems'];
        }
        if (isset($param['maxItems'])) {
            $rules['max_items'] = $param['maxItems'];
        }

        // Enum
        if (isset($param['enum'])) {
            $rules['enum'] = $param['enum'];
        }

        return $rules;
    }

    /**
     * Extract output fields from OpenAPI response schema.
     *
     * @param  array<string, mixed>  $schema  Response schema
     * @return array<string, mixed> Output fields
     */
    protected function extractOutputFields(array $schema): array
    {
        $fields = [];

        if ($schema['type'] === 'object' && isset($schema['properties'])) {
            foreach ($schema['properties'] as $name => $prop) {
                $fields[$name] = [
                    'type' => $this->normalizeType($prop['type'] ?? 'mixed'),
                    'description' => $prop['description'] ?? null,
                    'format' => $prop['format'] ?? null,
                    'enum' => $prop['enum'] ?? null,
                    'example' => $prop['example'] ?? null,
                ];
            }
        }

        return $fields;
    }

    /**
     * Detect which method to call (same logic as ActionHandler).
     *
     * @param  ReflectionClass  $reflection  The class reflection
     * @return string Method name
     */
    protected function detectMethod(ReflectionClass $reflection): string
    {
        if ($reflection->hasMethod('__invoke')) {
            return '__invoke';
        }

        if ($reflection->hasMethod('handle')) {
            return 'handle';
        }

        if ($reflection->hasMethod('execute')) {
            return 'execute';
        }

        if ($reflection->hasMethod('asAction')) {
            return 'asAction';
        }

        throw new \RuntimeException("No suitable method found in {$reflection->getName()}");
    }

    /**
     * Get parameters schema from method signature.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return array<int, array<string, mixed>> Parameters schema
     */
    protected function getParametersSchema(ReflectionMethod $method): array
    {
        $parameters = [];

        foreach ($method->getParameters() as $param) {
            $paramMetadata = $this->getParameterMetadata($param);

            $parameters[] = [
                'name' => $param->getName(),
                'type' => $this->getParameterType($param),
                'required' => ! $param->isOptional(),
                'default' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null,
                'description' => $paramMetadata['description'] ?? $this->getParameterDescription($method, $param),
                'example' => $paramMetadata['example'] ?? null,
                'validation' => $paramMetadata['validation'] ?? null,
                'source' => $paramMetadata['source'] ?? 'config',
            ];
        }

        return $parameters;
    }

    /**
     * Get parameter type information.
     *
     * @param  ReflectionParameter  $param  The parameter reflection
     * @return string Type name
     */
    protected function getParameterType(ReflectionParameter $param): string
    {
        $type = $param->getType();

        if ($type === null) {
            return 'mixed';
        }

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        return 'mixed';
    }

    /**
     * Get return type schema.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return array<string, mixed> Return type info
     */
    protected function getReturnTypeSchema(ReflectionMethod $method): array
    {
        $returnType = $method->getReturnType();

        if ($returnType === null) {
            return ['type' => 'mixed', 'description' => null];
        }

        if ($returnType instanceof ReflectionNamedType) {
            return [
                'type' => $returnType->getName(),
                'description' => $this->getReturnDescription($method),
            ];
        }

        return ['type' => 'mixed', 'description' => null];
    }

    /**
     * Get method description from docblock.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return string|null Description
     */
    protected function getDescription(ReflectionMethod $method): ?string
    {
        $docComment = $method->getDocComment();

        if (! $docComment) {
            return null;
        }

        // Extract first line of docblock (summary)
        preg_match('/\/\*\*\s*\n\s*\*\s*(.+?)\n/s', $docComment, $matches);

        return $matches[1] ?? null;
    }

    /**
     * Get parameter description from docblock @param tag.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @param  ReflectionParameter  $param  The parameter reflection
     * @return string|null Description
     */
    protected function getParameterDescription(ReflectionMethod $method, ReflectionParameter $param): ?string
    {
        $docComment = $method->getDocComment();

        if (! $docComment) {
            return null;
        }

        $paramName = $param->getName();
        $pattern = '/@param\s+\S+\s+\$'.$paramName.'\s+(.+?)(?:\n|$)/';

        if (preg_match($pattern, $docComment, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Get return description from docblock @return tag.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return string|null Description
     */
    protected function getReturnDescription(ReflectionMethod $method): ?string
    {
        $docComment = $method->getDocComment();

        if (! $docComment) {
            return null;
        }

        if (preg_match('/@return\s+\S+\s+(.+?)(?:\n|$)/s', $docComment, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Get custom tags from docblock (e.g., @workflow-output).
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return array<string, mixed> Custom tags
     */
    protected function getTags(ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if (! $docComment) {
            return [];
        }

        $tags = [];

        // Look for @workflow-* tags
        if (preg_match_all('/@workflow-(\w+)\s+(.+?)(?:\n|$)/s', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tags[$match[1]] = trim($match[2]);
            }
        }

        return $tags;
    }

    /**
     * Get metadata from WorkflowAction attribute on class.
     *
     * @param  ReflectionClass  $reflection  The class reflection
     * @return array<string, mixed> Metadata from attribute
     */
    protected function getClassMetadata(ReflectionClass $reflection): array
    {
        $attributes = $reflection->getAttributes(\AlizHarb\ForgePulse\Attributes\WorkflowAction::class);

        if (empty($attributes)) {
            return [];
        }

        $attribute = $attributes[0]->newInstance();

        return [
            'name' => $attribute->name,
            'description' => $attribute->description,
            'category' => $attribute->category,
            'tags' => $attribute->tags,
            'timeout' => $attribute->timeout,
        ];
    }

    /**
     * Get metadata from WorkflowAction attribute on method.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return array<string, mixed> Metadata from attribute
     */
    protected function getMethodMetadata(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(\AlizHarb\ForgePulse\Attributes\WorkflowAction::class);

        if (empty($attributes)) {
            return [];
        }

        $attribute = $attributes[0]->newInstance();

        return [
            'name' => $attribute->name,
            'description' => $attribute->description,
            'tags' => $attribute->tags,
        ];
    }

    /**
     * Get metadata from WorkflowParameter attribute.
     *
     * @param  ReflectionParameter  $param  The parameter reflection
     * @return array<string, mixed> Metadata from attribute
     */
    protected function getParameterMetadata(ReflectionParameter $param): array
    {
        $attributes = $param->getAttributes(\AlizHarb\ForgePulse\Attributes\WorkflowParameter::class);

        if (empty($attributes)) {
            return [];
        }

        $attribute = $attributes[0]->newInstance();

        return [
            'description' => $attribute->description,
            'example' => $attribute->example,
            'validation' => $attribute->validation,
            'source' => $attribute->source,
        ];
    }

    /**
     * Get output fields from WorkflowOutput attribute.
     *
     * @param  ReflectionMethod  $method  The method reflection
     * @return array<string, mixed> Output fields schema
     */
    protected function getOutputFields(ReflectionMethod $method): array
    {
        $attributes = $method->getAttributes(\AlizHarb\ForgePulse\Attributes\WorkflowOutput::class);

        if (empty($attributes)) {
            return [];
        }

        $attribute = $attributes[0]->newInstance();

        return $attribute->fields;
    }

    /**
     * Generate a readable name from class and method.
     *
     * @param  string  $class  The class name
     * @param  string  $method  The method name
     * @return string Generated name
     */
    protected function generateName(string $class, string $method): string
    {
        $className = class_basename($class);

        // Convert from PascalCase/camelCase to Title Case
        $name = preg_replace('/([a-z])([A-Z])/', '$1 $2', $className);

        return trim($name ?? $className);
    }

    /**
     * Get schemas for all available action classes.
     *
     * @param  array<string>  $classes  Array of class names
     * @return array<int, array<string, mixed>> Schemas for all classes
     */
    public function getBulkSchemas(array $classes): array
    {
        $schemas = [];

        foreach ($classes as $class) {
            try {
                $schemas[] = $this->getSchema($class);
            } catch (\Exception $e) {
                // Skip classes that can't be analyzed
                continue;
            }
        }

        return $schemas;
    }

    /**
     * Export actions as OpenAPI 3.0 specification.
     *
     * @param  array<string>  $classes  Array of action class names
     * @param  bool  $includeDbSchemas  Include schemas from database
     * @return array<string, mixed> OpenAPI spec
     */
    public function exportAsOpenAPI(array $classes, bool $includeDbSchemas = true): array
    {
        $paths = [];

        // Add class-based actions
        foreach ($classes as $class) {
            try {
                $schema = $this->getSchema($class);

                // Use original OpenAPI schema if available
                $operationSchema = $schema['openapi_schema'] ?? $this->convertToOpenAPI($schema);

                $operationId = $schema['operation_id'] ?? class_basename($class);
                $paths["/actions/{$operationId}"] = [
                    'post' => $operationSchema,
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Add database schemas (including external services)
        if ($includeDbSchemas) {
            $dbSchemas = \AlizHarb\ForgePulse\Models\ActionSchema::active()->get();

            foreach ($dbSchemas as $model) {
                $operationId = $model->schema['operationId'] ?? class_basename($model->action_class);
                $paths["/actions/{$operationId}"] = [
                    'post' => $model->schema,
                ];
            }
        }

        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'ForgePulse Workflow Actions',
                'version' => '1.0.0',
                'description' => 'Available actions for workflow orchestration',
            ],
            'paths' => $paths,
        ];
    }

    /**
     * Convert internal schema format to OpenAPI format.
     *
     * @param  array<string, mixed>  $schema  Internal schema
     * @return array<string, mixed> OpenAPI operation schema
     */
    protected function convertToOpenAPI(array $schema): array
    {
        $parameters = [];
        foreach ($schema['parameters'] as $param) {
            $paramSchema = [
                'type' => $param['type'],
                'description' => $param['description'],
                'required' => $param['required'],
            ];

            if ($param['default'] !== null) {
                $paramSchema['default'] = $param['default'];
            }
            if ($param['example'] !== null) {
                $paramSchema['example'] = $param['example'];
            }
            if ($param['format'] !== null) {
                $paramSchema['format'] = $param['format'];
            }
            if ($param['enum'] !== null) {
                $paramSchema['enum'] = $param['enum'];
            }

            // Add validation constraints
            if ($param['validation']) {
                if (isset($param['validation']['min'])) {
                    $paramSchema['minimum'] = $param['validation']['min'];
                }
                if (isset($param['validation']['max'])) {
                    $paramSchema['maximum'] = $param['validation']['max'];
                }
                if (isset($param['validation']['pattern'])) {
                    $paramSchema['pattern'] = $param['validation']['pattern'];
                }
            }

            $parameters[$param['name']] = $paramSchema;
        }

        $responseProperties = [];
        foreach ($schema['output_fields'] as $name => $field) {
            $responseProperties[$name] = [
                'type' => $field['type'],
                'description' => $field['description'],
            ];
        }

        return [
            'summary' => $schema['name'],
            'description' => $schema['description'],
            'tags' => $schema['tags'],
            'operationId' => $schema['operation_id'] ?? class_basename($schema['class']),
            'parameters' => $parameters,
            'responses' => [
                '200' => [
                    'description' => 'Success',
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => $responseProperties,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
