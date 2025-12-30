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
    private const CACHE_KEY_PREFIX = 'forgepulse:event_triggers';

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
        // Extract team ID from event for multi-tenancy
        $eventTeamId = $this->getEventTeamId($event);

        // Find triggers, filtered by team if multi-tenancy is enabled
        $triggers = $this->findTriggersForEvent($eventClass, $eventTeamId);

        if ($triggers->isEmpty()) {
            return;
        }

        $this->log("Event {$eventClass} fired, found {$triggers->count()} trigger(s)");

        foreach ($triggers as $trigger) {
            // Skip if trigger belongs to different tenant
            if (! $this->triggerMatchesTenant($trigger, $eventTeamId)) {
                continue;
            }

            // Convert event to array for context
            $eventData = $this->eventToArray($event);
            $eventData['_team_id'] = $eventTeamId;

            $this->triggerManager->fire($trigger, $eventData);
        }
    }

    /**
     * Extract team ID from an event for multi-tenancy.
     * Override or extend this to match your tenancy implementation.
     */
    protected function getEventTeamId(mixed $event): ?int
    {
        if (! config('forgepulse.teams.enabled', false)) {
            return null;
        }

        // Check common patterns for team ID in events
        if (is_object($event)) {
            // Direct property
            if (property_exists($event, 'teamId')) {
                return $event->teamId;
            }
            if (property_exists($event, 'team_id')) {
                return $event->team_id;
            }

            // Method
            if (method_exists($event, 'getTeamId')) {
                return $event->getTeamId();
            }

            // Nested in user
            if (property_exists($event, 'user') && $event->user) {
                if (method_exists($event->user, 'currentTeam')) {
                    return $event->user->currentTeam?->id;
                }
                if (isset($event->user->team_id)) {
                    return $event->user->team_id;
                }
            }

            // Nested in model
            if (property_exists($event, 'model') && $event->model) {
                if (isset($event->model->team_id)) {
                    return $event->model->team_id;
                }
            }
        }

        // Fallback to current context
        return \AlizHarb\ForgePulse\Models\WorkflowTrigger::getCurrentTeamId();
    }

    /**
     * Find triggers for an event, respecting multi-tenancy.
     *
     * @return \Illuminate\Support\Collection<int, \AlizHarb\ForgePulse\Models\WorkflowTrigger>
     */
    protected function findTriggersForEvent(string $eventClass, ?int $teamId): \Illuminate\Support\Collection
    {
        if (! config('forgepulse.teams.enabled', false)) {
            return $this->triggerManager->findEventTriggers($eventClass);
        }

        return $this->triggerManager->findEventTriggers($eventClass, $teamId);
    }

    /**
     * Check if a trigger matches the event's tenant.
     */
    protected function triggerMatchesTenant(\AlizHarb\ForgePulse\Models\WorkflowTrigger $trigger, ?int $eventTeamId): bool
    {
        if (! config('forgepulse.teams.enabled', false)) {
            return true;
        }

        // If event has no team, only match triggers with no team
        if ($eventTeamId === null) {
            return $trigger->team_id === null;
        }

        // Match triggers for the same team
        return $trigger->team_id === $eventTeamId;
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
        $cacheKey = $this->getCacheKey();

        return $this->cache->remember(
            $cacheKey,
            $ttl,
            fn () => $this->getEventClassesFromDatabase()
        );
    }

    /**
     * Get the cache key, optionally scoped by tenant.
     */
    protected function getCacheKey(): string
    {
        $key = self::CACHE_KEY_PREFIX;

        // For multi-tenant, we cache ALL event classes globally
        // because we need to register listeners for all tenants
        // The filtering happens at trigger lookup time
        return $key.':global';
    }

    /**
     * Get event classes from database.
     * Returns ALL event classes across all tenants for listener registration.
     *
     * @return array<string>
     */
    protected function getEventClassesFromDatabase(): array
    {
        // Note: We get ALL event classes here, not filtered by tenant
        // This is because we need to register listeners for events
        // from ALL tenants. The tenant filtering happens when the
        // event fires and we look up the specific triggers.
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
        cache()->forget(self::CACHE_KEY_PREFIX.':global');
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
