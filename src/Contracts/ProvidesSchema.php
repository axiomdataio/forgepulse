<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Contracts;

/**
 * Workflow Action Contract
 *
 * Actions can optionally implement this to provide OpenAPI-compliant schema.
 * If not implemented, reflection will be used as fallback.
 *
 * @see https://spec.openapis.org/oas/v3.0.3
 */
interface ProvidesSchema
{
    /**
     * Get the action schema in OpenAPI 3.0 format.
     *
     * @return array{
     *     summary?: string,
     *     description?: string,
     *     tags?: string[],
     *     operationId?: string,
     *     parameters: array<string, array{type: string, description?: string, required?: bool, default?: mixed, example?: mixed, schema?: array}>,
     *     responses: array<string, array{description: string, content?: array}>
     * }
     */
    public static function schema(): array;
}
