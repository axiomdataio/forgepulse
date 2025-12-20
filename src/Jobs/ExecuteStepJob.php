<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Jobs;

use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Models\WorkflowExecution;
use AlizHarb\ForgePulse\Models\WorkflowStep;
use AlizHarb\ForgePulse\Services\WorkflowEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * ExecuteStepJob
 *
 * Executes a single workflow step and advances the workflow to the next step.
 * This job-per-step approach enables fault tolerance, proper delays, and
 * prevents long-running jobs from blocking queue workers.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
final class ExecuteStepJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout;

    /**
     * Create a new job instance.
     *
     * Priority: Step → Workflow → Global Config
     *
     * @param  int  $executionId  The workflow execution ID
     * @param  int  $stepId  The step to execute
     */
    public function __construct(
        public readonly int $executionId,
        public readonly int $stepId
    ) {
        // Load step and workflow for configuration
        $step = WorkflowStep::find($this->stepId);
        $execution = WorkflowExecution::find($this->executionId);
        $workflow = $execution?->workflow;

        // Priority: Step → Workflow → Global Config
        $this->timeout = $step?->timeout
            ?? $workflow?->timeout
            ?? config('forgepulse.execution.step_timeout', 300);

        $this->tries = $step?->max_retries
            ?? $workflow?->max_retries
            ?? config('forgepulse.execution.max_retries', 3);

        $this->onQueue(config('forgepulse.execution.queue', 'default'));
    }

    /**
     * Execute the job.
     *
     * @param  WorkflowEngine  $engine  The workflow engine service
     */
    public function handle(WorkflowEngine $engine): void
    {
        $execution = WorkflowExecution::find($this->executionId);
        $step = WorkflowStep::find($this->stepId);

        if (! $execution || ! $step) {
            return;
        }

        // Check if execution is paused
        if ($execution->isPaused()) {
            // Re-queue this job to check again later
            self::dispatch($this->executionId, $this->stepId)->delay(60);

            return;
        }

        // Execute the step
        $engine->executeStep($execution, $step);

        // Advance workflow to next step(s)
        $engine->advance($execution);
    }

    /**
     * Get the unique ID for the job.
     *
     * Ensures only one job per execution step can be queued at a time.
     *
     * @return string Unique identifier for this job
     */
    public function uniqueId(): string
    {
        return "workflow-execution-{$this->executionId}-step-{$this->stepId}";
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string> Job tags for monitoring and filtering
     */
    public function tags(): array
    {
        $execution = WorkflowExecution::find($this->executionId);

        if (! $execution) {
            return [];
        }

        return [
            'workflow:'.$execution->workflow_id,
            'execution:'.$execution->id,
            'step:'.$this->stepId,
        ];
    }
}
