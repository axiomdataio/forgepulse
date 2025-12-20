<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\StepHandlers;

use AlizHarb\ForgePulse\Jobs\ExecuteStepJob;
use AlizHarb\ForgePulse\Models\WorkflowStep;

/**
 * Delay Handler
 *
 * Introduces a delay in workflow execution by scheduling the next step
 * with a delay instead of blocking the queue worker.
 */
class DelayHandler
{
    /**
     * Handle the step execution.
     *
     * This handler marks itself as complete and schedules the next step(s)
     * with the configured delay. This prevents blocking queue workers.
     *
     * @param  WorkflowStep  $step  The step to execute
     * @param  array<string, mixed>  $context  Execution context
     * @return array<string, mixed> Output data
     */
    public function handle(WorkflowStep $step, array $context): array
    {
        $config = $step->configuration;
        $seconds = $config['seconds'] ?? 0;

        // Don't sleep - let the job complete and schedule next steps with delay
        // The WorkflowEngine will handle scheduling child steps with this delay

        return [
            'delayed_seconds' => $seconds,
            'delayed_until' => now()->addSeconds($seconds)->toISOString(),
        ];
    }

    /**
     * Get the delay in seconds for this step.
     *
     * This is used by the WorkflowEngine to schedule child steps.
     *
     * @param  WorkflowStep  $step  The delay step
     * @return int Delay in seconds
     */
    public static function getDelay(WorkflowStep $step): int
    {
        $config = $step->configuration;

        return (int) ($config['seconds'] ?? 0);
    }
}
