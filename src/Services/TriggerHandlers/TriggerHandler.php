<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\TriggerHandlers;

use AlizHarb\ForgePulse\Models\Workflow;

/**
 * Base Trigger Handler Interface
 *
 * All trigger handlers must implement this interface.
 */
interface TriggerHandler
{
    /**
     * Register the trigger for the workflow.
     *
     * This method is called when auto_trigger_enabled is set to true.
     *
     * @param  Workflow  $workflow  The workflow to register trigger for
     * @return bool True if registration successful
     */
    public function register(Workflow $workflow): bool;

    /**
     * Unregister the trigger for the workflow.
     *
     * This method is called when auto_trigger_enabled is set to false
     * or when the workflow is deleted.
     *
     * @param  Workflow  $workflow  The workflow to unregister trigger for
     * @return bool True if unregistration successful
     */
    public function unregister(Workflow $workflow): bool;

    /**
     * Validate the trigger configuration.
     *
     * @param  array<string, mixed>  $config  Trigger configuration
     * @return bool True if configuration is valid
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function validate(array $config): bool;

    /**
     * Get the context data for workflow execution.
     *
     * This method extracts relevant data from the trigger event
     * to pass to the workflow execution.
     *
     * @param  mixed  $triggerData  Data from the trigger event
     * @return array<string, mixed> Context for workflow execution
     */
    public function extractContext(mixed $triggerData): array;
}
