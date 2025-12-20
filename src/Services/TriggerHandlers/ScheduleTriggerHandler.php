<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\TriggerHandlers;

use AlizHarb\ForgePulse\Models\Workflow;

/**
 * Schedule Trigger Handler
 *
 * Handles triggering workflows based on cron schedules.
 */
final readonly class ScheduleTriggerHandler implements TriggerHandler
{
    /**
     * Register the trigger for the workflow.
     */
    public function register(Workflow $workflow): bool
    {
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];

        // Validate cron expression
        $cronExpression = $config['cron_expression'] ?? '0 0 * * *';
        
        if (! $this->isValidCronExpression($cronExpression)) {
            throw new \InvalidArgumentException("Invalid cron expression: {$cronExpression}");
        }

        // Schedule is registered via Laravel's scheduler
        // See app/Console/Kernel.php for implementation
        
        return true;
    }

    /**
     * Unregister the trigger for the workflow.
     */
    public function unregister(Workflow $workflow): bool
    {
        // Schedules are checked dynamically, so no cleanup needed
        return true;
    }

    /**
     * Validate the trigger configuration.
     */
    public function validate(array $config): bool
    {
        if (empty($config['cron_expression'])) {
            throw new \InvalidArgumentException('cron_expression is required for schedule trigger');
        }

        if (! $this->isValidCronExpression($config['cron_expression'])) {
            throw new \InvalidArgumentException("Invalid cron expression: {$config['cron_expression']}");
        }

        return true;
    }

    /**
     * Extract context data for scheduled execution.
     */
    public function extractContext(mixed $triggerData): array
    {
        return [
            'scheduled_execution' => true,
            '_triggered_at' => now()->toISOString(),
            '_trigger_type' => 'schedule',
        ];
    }

    /**
     * Validate cron expression.
     */
    private function isValidCronExpression(string $expression): bool
    {
        // Basic validation - 5 or 6 parts separated by spaces
        $parts = explode(' ', $expression);
        
        return count($parts) >= 5 && count($parts) <= 6;
    }
}
