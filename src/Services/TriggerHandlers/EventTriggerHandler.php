<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\TriggerHandlers;

use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Support\Facades\Event;

/**
 * Event Trigger Handler
 *
 * Handles triggering workflows based on Laravel events.
 */
final readonly class EventTriggerHandler implements TriggerHandler
{
    /**
     * Register the trigger for the workflow.
     */
    public function register(Workflow $workflow): bool
    {
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];
        $eventClass = $config['event_class'] ?? null;

        if (! $eventClass || ! class_exists($eventClass)) {
            throw new \InvalidArgumentException("Invalid event class: {$eventClass}");
        }

        // Register event listener
        Event::listen($eventClass, function ($event) use ($workflow, $config) {
            // Extract context from event
            $context = $this->extractContext($event);

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

        return true;
    }

    /**
     * Unregister the trigger for the workflow.
     */
    public function unregister(Workflow $workflow): bool
    {
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];
        $eventClass = $config['event_class'] ?? null;

        if ($eventClass && class_exists($eventClass)) {
            Event::forget($eventClass);
        }

        return true;
    }

    /**
     * Validate the trigger configuration.
     */
    public function validate(array $config): bool
    {
        if (empty($config['event_class'])) {
            throw new \InvalidArgumentException('event_class is required for event trigger');
        }

        if (! class_exists($config['event_class'])) {
            throw new \InvalidArgumentException("Event class does not exist: {$config['event_class']}");
        }

        return true;
    }

    /**
     * Extract context data from the event.
     */
    public function extractContext(mixed $event): array
    {
        $context = [];

        // If event is an object, extract public properties
        if (is_object($event)) {
            $reflection = new \ReflectionClass($event);
            
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $propertyName = $property->getName();
                $context[$propertyName] = $property->getValue($event);
            }
        }

        // Add event metadata
        $context['_event_class'] = is_object($event) ? get_class($event) : null;
        $context['_triggered_at'] = now()->toISOString();

        return $context;
    }
}
