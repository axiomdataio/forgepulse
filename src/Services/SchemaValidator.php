<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services;

/**
 * JSON Schema Validator
 *
 * Validates step configuration parameters against OpenAPI/JSON Schema.
 */
class SchemaValidator
{
    /**
     * Validate parameters against schema.
     *
     * @param  array<string, mixed>  $parameters  Parameters to validate
     * @param  array<int, array<string, mixed>>  $parameterSchema  Schema from ActionSchema
     * @return array{valid: bool, errors: array<string>} Validation result
     */
    public function validate(array $parameters, array $parameterSchema): array
    {
        $errors = [];

        // Check required parameters
        foreach ($parameterSchema as $param) {
            $name = $param['name'];

            if ($param['required'] && ! array_key_exists($name, $parameters)) {
                $errors[] = "Missing required parameter: {$name}";

                continue;
            }

            if (! array_key_exists($name, $parameters)) {
                continue;
            }

            $value = $parameters[$name];

            // Type validation
            $typeError = $this->validateType($value, $param['type'], $name);
            if ($typeError) {
                $errors[] = $typeError;

                continue;
            }

            // Validation rules
            if (isset($param['validation'])) {
                $ruleErrors = $this->validateRules($value, $param['validation'], $name);
                $errors = array_merge($errors, $ruleErrors);
            }

            // Enum validation
            if (isset($param['enum']) && ! in_array($value, $param['enum'], true)) {
                $errors[] = "Parameter {$name} must be one of: ".implode(', ', $param['enum']);
            }
        }

        // Check for unknown parameters
        foreach (array_keys($parameters) as $name) {
            $paramExists = collect($parameterSchema)->contains('name', $name);
            if (! $paramExists) {
                $errors[] = "Unknown parameter: {$name}";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Validate parameter type.
     *
     * @param  mixed  $value  Value to validate
     * @param  string  $expectedType  Expected type
     * @param  string  $name  Parameter name
     * @return string|null Error message or null if valid
     */
    protected function validateType($value, string $expectedType, string $name): ?string
    {
        $actualType = gettype($value);

        $valid = match ($expectedType) {
            'int', 'integer' => is_int($value),
            'float', 'number' => is_float($value) || is_int($value),
            'string' => is_string($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || is_array($value),
            'mixed' => true,
            default => true,
        };

        if (! $valid) {
            return "Parameter {$name} must be {$expectedType}, got {$actualType}";
        }

        return null;
    }

    /**
     * Validate against validation rules.
     *
     * @param  mixed  $value  Value to validate
     * @param  array<string, mixed>  $rules  Validation rules
     * @param  string  $name  Parameter name
     * @return array<string> Error messages
     */
    protected function validateRules($value, array $rules, string $name): array
    {
        $errors = [];

        // Numeric rules
        if (isset($rules['min']) && is_numeric($value) && $value < $rules['min']) {
            $errors[] = "Parameter {$name} must be at least {$rules['min']}";
        }

        if (isset($rules['max']) && is_numeric($value) && $value > $rules['max']) {
            $errors[] = "Parameter {$name} must be at most {$rules['max']}";
        }

        // String rules
        if (isset($rules['min_length']) && is_string($value) && strlen($value) < $rules['min_length']) {
            $errors[] = "Parameter {$name} must be at least {$rules['min_length']} characters";
        }

        if (isset($rules['max_length']) && is_string($value) && strlen($value) > $rules['max_length']) {
            $errors[] = "Parameter {$name} must be at most {$rules['max_length']} characters";
        }

        if (isset($rules['pattern']) && is_string($value) && ! preg_match($rules['pattern'], $value)) {
            $errors[] = "Parameter {$name} does not match required pattern";
        }

        // Array rules
        if (isset($rules['min_items']) && is_array($value) && count($value) < $rules['min_items']) {
            $errors[] = "Parameter {$name} must have at least {$rules['min_items']} items";
        }

        if (isset($rules['max_items']) && is_array($value) && count($value) > $rules['max_items']) {
            $errors[] = "Parameter {$name} must have at most {$rules['max_items']} items";
        }

        return $errors;
    }
}
