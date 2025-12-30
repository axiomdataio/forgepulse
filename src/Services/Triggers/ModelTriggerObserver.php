<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\Triggers;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Model Trigger Observer Service
 *
 * Observes Eloquent model events and fires associated workflow triggers.
 * Supports created, updated, and deleted events with attribute filtering.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
final class ModelTriggerObserver
{
    private const CACHE_KEY = 'forgepulse:model_triggers';

    /**
     * Models that are being observed in this request.
     *
     * @var array<string>
     */
    private array $observedModels = [];

    public function __construct(
        private readonly TriggerManager $triggerManager,
        private readonly Cache $cache
    ) {}

    /**
     * Handle the "created" event.
     */
    public function created(Model $model): void
    {
        $this->handleModelEvent($model, 'created');
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->handleModelEvent($model, 'updated');
    }

    /**
     * Handle the "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->handleModelEvent($model, 'deleted');
    }

    /**
     * Register this observer for a model class.
     *
     * @param  string  $modelClass  Fully qualified model class name
     */
    public function observeModel(string $modelClass): void
    {
        if ($this->isObserving($modelClass)) {
            return;
        }

        if (! class_exists($modelClass)) {
            $this->log("Cannot observe non-existent model: {$modelClass}", 'warning');

            return;
        }

        $modelClass::observe($this);
        $this->observedModels[] = $modelClass;
        $this->log("Registered model observer for: {$modelClass}");
    }

    /**
     * Check if a model is being observed.
     *
     * @param  string  $modelClass  Fully qualified model class name
     */
    public function isObserving(string $modelClass): bool
    {
        return in_array($modelClass, $this->observedModels, true);
    }

    /**
     * Get all observed model classes.
     *
     * @return array<string>
     */
    public function getObservedModels(): array
    {
        return $this->observedModels;
    }

    /**
     * Register all model observers from database.
     * Called during application boot.
     */
    public function registerAllModelTriggers(): void
    {
        $modelClasses = $this->getModelClassesFromCache();

        foreach ($modelClasses as $modelClass) {
            $this->observeModel($modelClass);
        }

        if (! empty($modelClasses)) {
            $this->log('Registered '.count($modelClasses).' model trigger observers');
        }
    }

    /**
     * Handle a model event.
     *
     * @param  Model  $model  The model instance
     * @param  string  $event  The event type (created, updated, deleted)
     */
    protected function handleModelEvent(Model $model, string $event): void
    {
        $modelClass = get_class($model);
        $triggers = $this->triggerManager->findModelTriggers($modelClass, $event);

        if ($triggers->isEmpty()) {
            return;
        }

        $this->log("Model event {$modelClass}::{$event} fired, found {$triggers->count()} trigger(s)");

        foreach ($triggers as $trigger) {
            // Check attribute filters
            if (! $this->passesAttributeFilters($model, $event, $trigger)) {
                $this->log("Trigger '{$trigger->name}' skipped due to attribute filters");

                continue;
            }

            $modelData = [
                'model' => $model->toArray(),
                'model_class' => $modelClass,
                'model_id' => $model->getKey(),
                'event' => $event,
                'changes' => $event === 'updated' ? $model->getChanges() : [],
                'original' => $event === 'updated' ? $model->getOriginal() : [],
            ];

            $this->triggerManager->fire($trigger, $modelData);
        }
    }

    /**
     * Check if the model event passes the attribute filters.
     *
     * @param  Model  $model  The model instance
     * @param  string  $event  The event type
     * @param  WorkflowTrigger  $trigger  The trigger to check
     */
    protected function passesAttributeFilters(Model $model, string $event, WorkflowTrigger $trigger): bool
    {
        $filters = $trigger->configuration['attribute_filters'] ?? [];

        if (empty($filters)) {
            return true;
        }

        // For updates, check if any watched attributes changed
        if ($event === 'updated') {
            $watchedAttributes = $filters['watch_attributes'] ?? [];
            if (! empty($watchedAttributes)) {
                $changedKeys = array_keys($model->getChanges());
                if (empty(array_intersect($watchedAttributes, $changedKeys))) {
                    return false;
                }
            }
        }

        // Check value conditions
        foreach ($filters['conditions'] ?? [] as $attribute => $expectedValue) {
            $actualValue = $model->getAttribute($attribute);

            // Support various comparison types
            if (is_array($expectedValue)) {
                if (! in_array($actualValue, $expectedValue, true)) {
                    return false;
                }
            } elseif ($actualValue !== $expectedValue) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get model classes from cache, or database if cache miss.
     *
     * @return array<string>
     */
    protected function getModelClassesFromCache(): array
    {
        if (! config('forgepulse.triggers.serverless.cache_enabled', true)) {
            return $this->getModelClassesFromDatabase();
        }

        $ttl = config('forgepulse.triggers.serverless.cache_ttl', 300);

        return $this->cache->remember(
            self::CACHE_KEY,
            $ttl,
            fn () => $this->getModelClassesFromDatabase()
        );
    }

    /**
     * Get model classes from database.
     *
     * @return array<string>
     */
    protected function getModelClassesFromDatabase(): array
    {
        return WorkflowTrigger::query()
            ->where('is_active', true)
            ->where('type', TriggerType::MODEL)
            ->whereHas('workflow', fn ($q) => $q->where('status', 'active'))
            ->pluck('configuration')
            ->map(fn ($config) => $config['model_class'] ?? null)
            ->filter()
            ->unique()
            ->values()
            ->toArray();
    }

    /**
     * Clear the model triggers cache.
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
        Log::channel($channel)->$level("[ForgePulse Models] {$message}");
    }
}
