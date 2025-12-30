<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\Triggers;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Models\WorkflowExecution;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use AlizHarb\ForgePulse\Services\ConditionalEvaluator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Trigger Manager Service
 *
 * Central service for managing and firing workflow triggers.
 * Handles trigger discovery, condition evaluation, and workflow execution.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
final readonly class TriggerManager
{
    public function __construct(
        private ConditionalEvaluator $conditionalEvaluator
    ) {}

    /**
     * Fire a trigger and execute its workflow.
     *
     * @param  WorkflowTrigger  $trigger  The trigger to fire
     * @param  mixed  $data  Data from the trigger source
     * @return WorkflowExecution|null The created execution, or null if trigger cannot fire
     */
    public function fire(WorkflowTrigger $trigger, mixed $data = []): ?WorkflowExecution
    {
        if (! $trigger->canFire()) {
            $this->log("Trigger '{$trigger->name}' cannot fire (rate limited or inactive)", 'info');

            return null;
        }

        // Evaluate additional conditions
        if ($trigger->conditions && ! $this->evaluateConditions($trigger, $data)) {
            $this->log("Trigger '{$trigger->name}' conditions not met", 'info');

            return null;
        }

        // Build execution context
        $context = $trigger->buildContext($data);
        $context['_trigger'] = [
            'id' => $trigger->id,
            'name' => $trigger->name,
            'type' => $trigger->type->value,
            'fired_at' => now()->toIso8601String(),
        ];

        // Record the trigger execution
        $trigger->recordExecution();

        $this->log("Trigger '{$trigger->name}' fired, executing workflow '{$trigger->workflow->name}'", 'info');

        // Execute the workflow
        return $trigger->workflow->execute($context);
    }

    /**
     * Find triggers for a specific Laravel event.
     *
     * @param  string  $eventClass  Fully qualified event class name
     * @return Collection<int, WorkflowTrigger>
     */
    public function findEventTriggers(string $eventClass): Collection
    {
        return WorkflowTrigger::active()
            ->ofType(TriggerType::EVENT)
            ->withActiveWorkflow()
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(function (WorkflowTrigger $trigger) use ($eventClass) {
                return ($trigger->configuration['event_class'] ?? '') === $eventClass;
            });
    }

    /**
     * Find triggers for a model event.
     *
     * @param  string  $modelClass  Fully qualified model class name
     * @param  string  $event  Model event (created, updated, deleted)
     * @return Collection<int, WorkflowTrigger>
     */
    public function findModelTriggers(string $modelClass, string $event): Collection
    {
        return WorkflowTrigger::active()
            ->ofType(TriggerType::MODEL)
            ->withActiveWorkflow()
            ->orderBy('priority', 'desc')
            ->get()
            ->filter(function (WorkflowTrigger $trigger) use ($modelClass, $event) {
                $config = $trigger->configuration;

                return ($config['model_class'] ?? '') === $modelClass
                    && in_array($event, $config['events'] ?? [], true);
            });
    }

    /**
     * Find a webhook trigger by its token.
     *
     * @param  string  $token  Webhook token
     */
    public function findWebhookTrigger(string $token): ?WorkflowTrigger
    {
        return WorkflowTrigger::active()
            ->ofType(TriggerType::WEBHOOK)
            ->withActiveWorkflow()
            ->get()
            ->first(function (WorkflowTrigger $trigger) use ($token) {
                return ($trigger->configuration['webhook_token'] ?? '') === $token;
            });
    }

    /**
     * Get all scheduled triggers.
     *
     * @return Collection<int, WorkflowTrigger>
     */
    public function getScheduledTriggers(): Collection
    {
        return WorkflowTrigger::active()
            ->ofType(TriggerType::SCHEDULE)
            ->withActiveWorkflow()
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Get all triggers for a workflow.
     *
     * @return Collection<int, WorkflowTrigger>
     */
    public function getWorkflowTriggers(int $workflowId): Collection
    {
        return WorkflowTrigger::where('workflow_id', $workflowId)
            ->orderBy('priority', 'desc')
            ->get();
    }

    /**
     * Evaluate trigger conditions against provided data.
     *
     * @param  WorkflowTrigger  $trigger  The trigger with conditions
     * @param  mixed  $data  Data to evaluate against
     */
    protected function evaluateConditions(WorkflowTrigger $trigger, mixed $data): bool
    {
        $conditions = $trigger->conditions?->getArrayCopy();

        if (empty($conditions)) {
            return true;
        }

        $context = is_array($data) ? $data : ['data' => $data];

        return $this->conditionalEvaluator->evaluate($conditions, $context);
    }

    /**
     * Log a message to the configured channel.
     */
    protected function log(string $message, string $level = 'info'): void
    {
        if (! config('forgepulse.logging.enabled', true)) {
            return;
        }

        $channel = config('forgepulse.logging.channel', 'stack');
        Log::channel($channel)->$level("[ForgePulse Triggers] {$message}");
    }
}
