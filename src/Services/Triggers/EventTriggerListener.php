<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\Triggers;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Log;

/**
 * Event Trigger Listener Service
 *
 * Manages registration and handling of Laravel event listeners for workflow triggers.
 * Optimised for serverless environments with caching support.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
final class EventTriggerListener
{
    private const CACHE_KEY = 'forgepulse:event_triggers';

    /**
     * Events that have been registered for listening in this request.
     *
     * @var array<string>
     */
    private array $registeredEvents = [];

    public function __construct(
        private readonly TriggerManager $triggerManager,
        private readonly Dispatcher $events,
        private readonly Cache $cache
    ) {}

    /**
     * Register a listener for a specific event class.
     * Safe to call multiple times - will only register once per request.
     *
     * @param  string  $eventClass  Fully qualified event class name
     */
    public function registerEvent(string $eventClass): void
    {
        // Already registered in this request - skip
        if (in_array($eventClass, $this->registeredEvents, true)) {
            return;
        }

        // Validate event class exists
        if (! class_exists($eventClass)) {
            $this->log("Cannot register listener for non-existent event: {$eventClass}", 'warning');

            return;
        }

        // Register the listener
        $this->events->listen($eventClass, function ($event) use ($eventClass) {
            $this->handleEvent($eventClass, $event);
        });

        $this->registeredEvents[] = $eventClass;
        $this->log("Registered workflow trigger listener for event: {$eventClass}");
    }

    /**
     * Check if an event is already being listened to.
     *
     * @param  string  $eventClass  Fully qualified event class name
     */
    public function isListening(string $eventClass): bool
    {
        return in_array($eventClass, $this->registeredEvents, true);
    }

    /**
     * Get all registered event classes.
     *
     * @return array<string>
     */
    public function getRegisteredEvents(): array
    {
        return $this->registeredEvents;
    }

    /**
     * Register all event listeners from database.
     * Called during application boot.
     */
    public function registerAllEventTriggers(): void
    {
        $eventClasses = $this->getEventClassesFromCache();

        foreach ($eventClasses as $eventClass) {
            $this->registerEvent($eventClass);
        }

        if (! empty($eventClasses)) {
            $this->log('Registered '.count($eventClasses).' event trigger listeners');
        }
    }

    /**
     * Handle an incoming event.
     *
     * @param  string  $eventClass  The event class name
     * @param  mixed  $event  The event instance
     */
    protected function handleEvent(string $eventClass, mixed $event): void
    {
        $triggers = $this->triggerManager->findEventTriggers($eventClass);

        if ($triggers->isEmpty()) {
            return;
        }

        $this->log("Event {$eventClass} fired, found {$triggers->count()} trigger(s)");

        foreach ($triggers as $trigger) {
            // Convert event to array for context
            $eventData = $this->eventToArray($event);

            $this->triggerManager->fire($trigger, $eventData);
        }
    }

    /**
     * Convert an event to an array.
     *
     * @return array<string, mixed>
     */
    protected function eventToArray(mixed $event): array
    {
        if (is_array($event)) {
            return $event;
        }

        if (method_exists($event, 'toArray')) {
            return $event->toArray();
        }

        if (method_exists($event, 'broadcastWith')) {
            return $event->broadcastWith();
        }

        // Use reflection to get public properties
        $reflection = new \ReflectionClass($event);
        $properties = [];

        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $value = $property->getValue($event);
            $properties[$property->getName()] = $this->serializeValue($value);
        }

        return $properties;
    }

    /**
     * Serialize a value for context.
     */
    protected function serializeValue(mixed $value): mixed
    {
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            if ($value instanceof \JsonSerializable) {
                return $value->jsonSerialize();
            }

            return (array) $value;
        }

        return $value;
    }

    /**
     * Get event classes from cache, or database if cache miss.
     *
     * @return array<string>
     */
    protected function getEventClassesFromCache(): array
    {
        if (! config('forgepulse.triggers.serverless.cache_enabled', true)) {
            return $this->getEventClassesFromDatabase();
        }

        $ttl = config('forgepulse.triggers.serverless.cache_ttl', 300);

        return $this->cache->remember(
            self::CACHE_KEY,
            $ttl,
            fn () => $this->getEventClassesFromDatabase()
        );
    }

    /**
     * Get event classes from database.
     *
     * @return array<string>
     */
    protected function getEventClassesFromDatabase(): array
    {
        return WorkflowTrigger::query()
            ->where('is_active', true)
            ->where('type', TriggerType::EVENT)
            ->whereHas('workflow', fn ($q) => $q->where('status', 'active'))
            ->pluck('configuration')
            ->map(fn ($config) => $config['event_class'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Clear the event triggers cache.
     */
    public static function clearCache(): void
    {
        cache()->forget(self::CACHE_KEY);
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
        Log::channel($channel)->$level("[ForgePulse Events] {$message}");
    }
}
