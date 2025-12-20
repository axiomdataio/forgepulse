<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services;

use AlizHarb\ForgePulse\Enums\LogStatus;
use AlizHarb\ForgePulse\Events\StepExecuted;
use AlizHarb\ForgePulse\Events\WorkflowCompleted;
use AlizHarb\ForgePulse\Events\WorkflowFailed;
use AlizHarb\ForgePulse\Jobs\ExecuteStepJob;
use AlizHarb\ForgePulse\Models\WorkflowExecution;
use AlizHarb\ForgePulse\Models\WorkflowExecutionLog;
use AlizHarb\ForgePulse\Models\WorkflowStep;
use Illuminate\Support\Facades\Log;

/**
 * Workflow Engine Service
 *
 * Orchestrates the execution of workflows using a step-per-job approach.
 * Each step executes in its own queued job, enabling fault tolerance,
 * proper delays, and preventing long-running jobs from blocking workers.
 */
final readonly class WorkflowEngine
{
    public function __construct(
        private StepExecutor $stepExecutor
    ) {}

    /**
     * Start a workflow execution by dispatching the first step(s).
     *
     * @param  WorkflowExecution  $execution  The workflow execution to start
     */
    public function start(WorkflowExecution $execution): void
    {
        $execution->markAsStarted();

        $this->logExecution($execution, 'Workflow started');

        // Dispatch first step(s)
        $this->advance($execution);
    }

    /**
     * Execute a workflow synchronously (all steps in one process).
     *
     * This mode is faster for workflows with fast steps and no delays,
     * but provides no fault tolerance or resume capability.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     *
     * @throws \Exception
     */
    public function executeSync(WorkflowExecution $execution): void
    {
        try {
            $execution->markAsStarted();

            $this->logExecution($execution, 'Workflow started (sync mode)');

            $workflow = $execution->workflow;
            $context = $execution->context?->getArrayCopy() ?? [];

            // Get all steps ordered by position
            $steps = $this->getAllStepsOrdered($workflow);

            // Execute all steps sequentially in same process
            foreach ($steps as $step) {
                // Check if execution is paused
                $freshExecution = $execution->fresh();
                if ($freshExecution && $freshExecution->isPaused()) {
                    $this->logExecution($execution, 'Workflow paused by user (sync mode)');

                    return;
                }

                // Execute step and update context
                $context = $this->executeStepSync($execution, $step, $context);
            }

            $execution->markAsCompleted($context);

            if (config('forgepulse.events.workflow_completed', true)) {
                event(new WorkflowCompleted($execution));
            }

            $this->logExecution($execution, 'Workflow completed successfully (sync mode)');
        } catch (\Exception $e) {
            $execution->markAsFailed($e->getMessage());

            if (config('forgepulse.events.workflow_failed', true)) {
                event(new WorkflowFailed($execution, $e->getMessage()));
            }

            $this->logExecution($execution, 'Workflow failed (sync mode): '.$e->getMessage(), 'error');

            throw $e;
        }
    }

    /**
     * Execute a single step synchronously and return updated context.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     * @param  WorkflowStep  $step  The step to execute
     * @param  array<string, mixed>  $context  Current execution context
     * @return array<string, mixed> Updated context
     *
     * @throws \Exception
     */
    protected function executeStepSync(WorkflowExecution $execution, WorkflowStep $step, array $context): array
    {
        // Update current step
        $execution->update(['current_step_id' => $step->id]);

        // Create execution log
        $log = WorkflowExecutionLog::create([
            'workflow_execution_id' => $execution->id,
            'workflow_step_id' => $step->id,
            'status' => LogStatus::PENDING,
        ]);

        try {
            // Check if step should be executed based on conditions
            if ($step->hasConditions() && ! $step->evaluateConditions($context)) {
                $log->markAsSkipped();
                $this->logExecution($execution, "Step '{$step->name}' skipped due to conditions");
                $this->markStepCompleted($execution, $step->id);

                return $context;
            }

            $log->markAsStarted($context);

            // Execute the step
            $output = $this->stepExecutor->execute($step, $context);

            // Merge output into context
            $context = array_merge($context, $output);

            // Update execution context
            $execution->update(['context' => $context]);

            $log->markAsCompleted($output);

            if (config('forgepulse.events.step_executed', true)) {
                event(new StepExecuted($step, $log));
            }

            $this->logExecution($execution, "Step '{$step->name}' executed successfully");

            // Mark step as completed
            $this->markStepCompleted($execution, $step->id);

            return $context;
        } catch (\AlizHarb\ForgePulse\Exceptions\StepTimeoutException $e) {
            $log->markAsFailed($e->getMessage());

            $this->logExecution(
                $execution,
                "Step '{$step->name}' timed out: ".$e->getMessage(),
                'error'
            );

            throw $e;
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());

            $this->logExecution(
                $execution,
                "Step '{$step->name}' failed: ".$e->getMessage(),
                'error'
            );

            throw $e;
        }
    }

    /**
     * Get all steps in execution order (respecting parent-child relationships).
     *
     * @param  \AlizHarb\ForgePulse\Models\Workflow  $workflow  The workflow
     * @return \Illuminate\Support\Collection<int, WorkflowStep>
     */
    protected function getAllStepsOrdered($workflow): \Illuminate\Support\Collection
    {
        $allSteps = $workflow->steps()->enabled()->orderBy('position')->get();
        $orderedSteps = collect();
        $processed = [];

        // Recursive function to add steps in order
        $addStepsInOrder = function ($parentId = null) use ($allSteps, &$orderedSteps, &$processed, &$addStepsInOrder) {
            $steps = $allSteps->where('parent_step_id', $parentId)->sortBy('position');

            foreach ($steps as $step) {
                if (! in_array($step->id, $processed)) {
                    $orderedSteps->push($step);
                    $processed[] = $step->id;

                    // Recursively add children
                    $addStepsInOrder($step->id);
                }
            }
        };

        // Start with root steps (no parent)
        $addStepsInOrder(null);

        return $orderedSteps;
    }

    /**
     * Execute a single workflow step.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     * @param  WorkflowStep  $step  The step to execute
     *
     * @throws \Exception
     */
    public function executeStep(WorkflowExecution $execution, WorkflowStep $step): void
    {
        // Mark current step
        $execution->update(['current_step_id' => $step->id]);

        // Create execution log
        $log = WorkflowExecutionLog::create([
            'workflow_execution_id' => $execution->id,
            'workflow_step_id' => $step->id,
            'status' => LogStatus::PENDING,
        ]);

        try {
            $context = $execution->context?->getArrayCopy() ?? [];

            // Check if step should be executed based on conditions
            if ($step->hasConditions() && ! $step->evaluateConditions($context)) {
                $log->markAsSkipped();
                $this->logExecution($execution, "Step '{$step->name}' skipped due to conditions");
                $this->markStepCompleted($execution, $step->id);

                return;
            }

            $log->markAsStarted($context);

            // Execute the step
            $output = $this->stepExecutor->execute($step, $context);

            // Merge output into context
            $context = array_merge($context, $output);
            $execution->update(['context' => $context]);

            $log->markAsCompleted($output);

            if (config('forgepulse.events.step_executed', true)) {
                event(new StepExecuted($step, $log));
            }

            $this->logExecution($execution, "Step '{$step->name}' executed successfully");

            // Mark step as completed
            $this->markStepCompleted($execution, $step->id);
        } catch (\AlizHarb\ForgePulse\Exceptions\StepTimeoutException $e) {
            $log->markAsFailed($e->getMessage());

            $this->logExecution(
                $execution,
                "Step '{$step->name}' timed out: ".$e->getMessage(),
                'error'
            );

            $execution->markAsFailed("Step '{$step->name}' timed out: ".$e->getMessage());

            if (config('forgepulse.events.workflow_failed', true)) {
                event(new WorkflowFailed($execution, $e->getMessage()));
            }

            throw $e;
        } catch (\Exception $e) {
            $log->markAsFailed($e->getMessage());

            $this->logExecution(
                $execution,
                "Step '{$step->name}' failed: ".$e->getMessage(),
                'error'
            );

            $execution->markAsFailed("Step '{$step->name}' failed: ".$e->getMessage());

            if (config('forgepulse.events.workflow_failed', true)) {
                event(new WorkflowFailed($execution, $e->getMessage()));
            }

            throw $e;
        }
    }

    /**
     * Advance the workflow to the next step(s).
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     */
    public function advance(WorkflowExecution $execution): void
    {
        $execution = $execution->fresh();

        if (! $execution || $execution->isFinished()) {
            return;
        }

        // Get next steps to execute
        $nextSteps = $this->getNextSteps($execution);

        if ($nextSteps->isEmpty()) {
            // No more steps - workflow complete
            $this->completeWorkflow($execution);

            return;
        }

        // Check if the last completed step was a delay step
        $delay = $this->calculateDelay($execution);

        // Dispatch jobs for next steps
        foreach ($nextSteps as $step) {
            if ($delay > 0) {
                ExecuteStepJob::dispatch($execution->id, $step->id)->delay($delay);
            } else {
                ExecuteStepJob::dispatch($execution->id, $step->id);
            }
        }
    }

    /**
     * Calculate delay from the last completed step if it was a delay step.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     * @return int Delay in seconds
     */
    private function calculateDelay(WorkflowExecution $execution): int
    {
        if (! $execution->current_step_id) {
            return 0;
        }

        $currentStep = WorkflowStep::find($execution->current_step_id);

        if (! $currentStep || $currentStep->type->value !== 'delay') {
            return 0;
        }

        return \AlizHarb\ForgePulse\Services\StepHandlers\DelayHandler::getDelay($currentStep);
    }

    /**
     * Get the next step(s) to execute.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     * @return \Illuminate\Support\Collection<int, WorkflowStep>
     */
    private function getNextSteps(WorkflowExecution $execution): \Illuminate\Support\Collection
    {
        $completedStepIds = $execution->completed_step_ids ?? [];
        $workflow = $execution->workflow;

        // If no steps completed yet, return root steps
        if (empty($completedStepIds)) {
            return $workflow->steps()
                ->enabled()
                ->roots()
                ->orderBy('position')
                ->get();
        }

        // Get child steps of completed steps that haven't been executed yet
        $nextSteps = WorkflowStep::whereIn('parent_step_id', $completedStepIds)
            ->whereNotIn('id', $completedStepIds)
            ->where('is_enabled', true)
            ->where('workflow_id', $workflow->id)
            ->orderBy('position')
            ->get();

        // Filter out steps whose dependencies aren't complete
        return $nextSteps->filter(function (WorkflowStep $step) use ($completedStepIds) {
            // If step has a parent, parent must be completed
            if ($step->parent_step_id && ! in_array($step->parent_step_id, $completedStepIds)) {
                return false;
            }

            return true;
        });
    }

    /**
     * Mark a step as completed.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     * @param  int  $stepId  The step ID
     */
    private function markStepCompleted(WorkflowExecution $execution, int $stepId): void
    {
        $completedStepIds = $execution->completed_step_ids ?? [];

        if (! in_array($stepId, $completedStepIds)) {
            $completedStepIds[] = $stepId;
            $execution->update(['completed_step_ids' => $completedStepIds]);
        }
    }

    /**
     * Complete the workflow.
     *
     * @param  WorkflowExecution  $execution  The workflow execution
     */
    private function completeWorkflow(WorkflowExecution $execution): void
    {
        $context = $execution->context?->getArrayCopy() ?? [];
        $execution->markAsCompleted($context);

        if (config('forgepulse.events.workflow_completed', true)) {
            event(new WorkflowCompleted($execution));
        }

        $this->logExecution($execution, 'Workflow completed successfully');
    }

    /**
     * Log execution information.
     */
    private function logExecution(WorkflowExecution $execution, string $message, string $level = 'info'): void
    {
        if (! config('forgepulse.logging.enabled', true)) {
            return;
        }

        $channel = config('forgepulse.logging.channel', 'stack');

        Log::channel($channel)->$level($message, [
            'workflow_id' => $execution->workflow_id,
            'execution_id' => $execution->id,
        ]);
    }
}
