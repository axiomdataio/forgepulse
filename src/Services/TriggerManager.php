<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Services\TriggerHandlers\TriggerHandler;

/**
 * Trigger Manager Service
 *
 * Manages workflow trigger registration and unregistration.
 */
final readonly class TriggerManager
{
    /**
     * Register a trigger for a workflow.
     *
     * @param  Workflow  $workflow  The workflow to register trigger for
     * @return bool True if registration successful
     *
     * @throws \InvalidArgumentException If trigger configuration is invalid
     */
    public function register(Workflow $workflow): bool
    {
        if (! $workflow->auto_trigger_enabled || ! $workflow->trigger_type) {
            return false;
        }

        $handler = $this->getHandler($workflow->trigger_type);
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];

        // Validate configuration
        $handler->validate($config);

        // Register trigger
        return $handler->register($workflow);
    }

    /**
     * Unregister a trigger for a workflow.
     *
     * @param  Workflow  $workflow  The workflow to unregister trigger for
     * @return bool True if unregistration successful
     */
    public function unregister(Workflow $workflow): bool
    {
        if (! $workflow->trigger_type) {
            return false;
        }

        $handler = $this->getHandler($workflow->trigger_type);

        return $handler->unregister($workflow);
    }

    /**
     * Register all enabled workflow triggers.
     *
     * This should be called on application boot.
     *
     * @return int Number of triggers registered
     */
    public function registerAll(): int
    {
        $workflows = Workflow::where('auto_trigger_enabled', true)
            ->whereNotNull('trigger_type')
            ->active()
            ->get();

        $registered = 0;

        foreach ($workflows as $workflow) {
            try {
                if ($this->register($workflow)) {
                    $registered++;
                }
            } catch (\Exception $e) {
                \Log::error("Failed to register trigger for workflow {$workflow->id}: {$e->getMessage()}");
            }
        }

        return $registered;
    }

    /**
     * Get the handler for a trigger type.
     *
     * @param  TriggerType|string  $triggerType  The trigger type
     * @return TriggerHandler The handler instance
     *
     * @throws \InvalidArgumentException If trigger type is invalid
     */
    private function getHandler(TriggerType|string $triggerType): TriggerHandler
    {
        if (is_string($triggerType)) {
            $triggerType = TriggerType::from($triggerType);
        }

        $handlerClass = $triggerType->handlerClass();

        if (! class_exists($handlerClass)) {
            throw new \InvalidArgumentException("Trigger handler not found: {$handlerClass}");
        }

        $handler = app($handlerClass);

        if (! $handler instanceof TriggerHandler) {
            throw new \InvalidArgumentException("Invalid trigger handler: {$handlerClass}");
        }

        return $handler;
    }

    /**
     * Validate trigger configuration.
     *
     * @param  TriggerType|string  $triggerType  The trigger type
     * @param  array<string, mixed>  $config  Trigger configuration
     * @return bool True if configuration is valid
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function validateConfiguration(TriggerType|string $triggerType, array $config): bool
    {
        $handler = $this->getHandler($triggerType);

        return $handler->validate($config);
    }
}
