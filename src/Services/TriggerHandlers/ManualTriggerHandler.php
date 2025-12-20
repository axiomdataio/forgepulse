<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\TriggerHandlers;

use AlizHarb\ForgePulse\Models\Workflow;

/**
 * Manual Trigger Handler
 *
 * Handles manual workflow triggering (default behavior).
 */
final readonly class ManualTriggerHandler implements TriggerHandler
{
    /**
     * Register the trigger for the workflow.
     */
    public function register(Workflow $workflow): bool
    {
        // Manual triggers don't need registration
        return true;
    }

    /**
     * Unregister the trigger for the workflow.
     */
    public function unregister(Workflow $workflow): bool
    {
        // Manual triggers don't need unregistration
        return true;
    }

    /**
     * Validate the trigger configuration.
     */
    public function validate(array $config): bool
    {
        // Manual triggers don't require configuration
        return true;
    }

    /**
     * Extract context data for manual execution.
     */
    public function extractContext(mixed $triggerData): array
    {
        if (is_array($triggerData)) {
            return $triggerData;
        }

        return [
            '_triggered_at' => now()->toISOString(),
            '_trigger_type' => 'manual',
        ];
    }
}
