<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\TriggerHandlers;

use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Database\Eloquent\Model;

/**
 * Model Trigger Handler
 *
 * Handles triggering workflows based on Eloquent model events.
 */
final readonly class ModelTriggerHandler implements TriggerHandler
{
    /**
     * Register the trigger for the workflow.
     */
    public function register(Workflow $workflow): bool
    {
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];
        $modelClass = $config['model_class'] ?? null;
        $events = $config['events'] ?? ['created'];

        if (! $modelClass || ! class_exists($modelClass)) {
            throw new \InvalidArgumentException("Invalid model class: {$modelClass}");
        }

        // Register model event listeners
        foreach ($events as $event) {
            $modelClass::$event(function (Model $model) use ($workflow, $config) {
                // Extract context from model
                $context = $this->extractContext($model);

                // Check conditions if configured
                if (! empty($config['conditions'])) {
                    $evaluator = app(\AlizHarb\ForgePulse\Services\ConditionalEvaluator::class);
                    if (! $evaluator->evaluate($config['conditions'], $context)) {
                        return;
                    }
                }

                // Execute workflow
                $workflow->execute($context);
            });
        }

        return true;
    }

    /**
     * Unregister the trigger for the workflow.
     */
    public function unregister(Workflow $workflow): bool
    {
        // Model event listeners are registered per request
        // No cleanup needed
        return true;
    }

    /**
     * Validate the trigger configuration.
     */
    public function validate(array $config): bool
    {
        if (empty($config['model_class'])) {
            throw new \InvalidArgumentException('model_class is required for model trigger');
        }

        if (! class_exists($config['model_class'])) {
            throw new \InvalidArgumentException("Model class does not exist: {$config['model_class']}");
        }

        if (! is_subclass_of($config['model_class'], Model::class)) {
            throw new \InvalidArgumentException("Class must extend Eloquent Model: {$config['model_class']}");
        }

        $validEvents = ['created', 'updated', 'deleted', 'restored', 'forceDeleted'];
        $events = $config['events'] ?? ['created'];

        foreach ($events as $event) {
            if (! in_array($event, $validEvents)) {
                throw new \InvalidArgumentException("Invalid model event: {$event}");
            }
        }

        return true;
    }

    /**
     * Extract context data from the model.
     */
    public function extractContext(mixed $model): array
    {
        if (! $model instanceof Model) {
            return [];
        }

        $context = [
            'model' => $model->toArray(),
            'model_id' => $model->getKey(),
            'model_class' => get_class($model),
            '_triggered_at' => now()->toISOString(),
        ];

        // Add change information if available
        if ($model->wasRecentlyCreated) {
            $context['_event'] = 'created';
        } elseif ($model->wasChanged()) {
            $context['_event'] = 'updated';
            $context['_changes'] = $model->getChanges();
            $context['_original'] = $model->getOriginal();
        }

        return $context;
    }
}
