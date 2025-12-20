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
     * @param  string  $class  The action class name
     * @param  string|null  $method  The method name (null for auto-detect)
     * @return array<string, mixed> Schema information
     *
     * @throws \ReflectionException
     */
    public function getSchema(string $class, ?string $method = null): array
    {
        if (! class_exists($class)) {
            throw new \RuntimeException("Class not found: {$class}");
        }

        // Priority 1: Check if class provides its own schema
        if (method_exists($class, 'schema')) {
            $schema = $class::schema();

            return $this->normalizeSchema($class, $schema);
        }

        // Priority 2: Use reflection
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
        ];
    }

    /**
     * Normalize schema from ProvidesSchema interface.
     *
     * @param  string  $class  The action class name
     * @param  array<string, mixed>  $schema  Raw schema from action
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

        // Normalize input fields to parameters format
        $parameters = [];
        foreach ($schema['input'] ?? [] as $name => $config) {
            // Support shorthand: 'field' => 'type'
            if (is_string($config)) {
                $config = ['type' => $config];
            }

            $parameters[] = [
                'name' => $name,
                'type' => $config['type'] ?? 'mixed',
                'required' => $config['required'] ?? true,
                'default' => $config['default'] ?? null,
                'description' => $config['description'] ?? null,
                'example' => $config['example'] ?? null,
                'validation' => $config['validation'] ?? null,
                'source' => $config['source'] ?? 'config',
            ];
        }

        // Normalize output fields
        $outputFields = [];
        foreach ($schema['output'] ?? [] as $name => $config) {
            // Support shorthand: 'field' => 'type'
            if (is_string($config)) {
                $config = ['type' => $config];
            }

            $outputFields[$name] = [
                'type' => $config['type'] ?? 'mixed',
                'description' => $config['description'] ?? null,
            ];
        }

        return [
            'class' => $class,
            'method' => $method,
            'name' => $schema['name'] ?? $this->generateName($class, $method),
            'description' => $schema['description'] ?? null,
            'category' => $schema['category'] ?? 'general',
            'tags' => $schema['tags'] ?? [],
            'recommended_timeout' => $schema['timeout'] ?? null,
            'parameters' => $parameters,
            'return_type' => ['type' => 'array', 'description' => null],
            'output_fields' => $outputFields,
        ];
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
}
