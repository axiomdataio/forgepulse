<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Contracts;

/**
 * Workflow Action Contract
 *
 * Actions can optionally implement this to provide schema information.
 * If not implemented, reflection will be used as fallback.
 */
interface ProvidesSchema
{
    /**
     * Get the action schema.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array;
}
