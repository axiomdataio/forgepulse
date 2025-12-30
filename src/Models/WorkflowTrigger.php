<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Models;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Services\Triggers\EventTriggerListener;
use AlizHarb\ForgePulse\Services\Triggers\ModelTriggerObserver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * WorkflowTrigger Model
 *
 * Represents a trigger configuration that can automatically initiate a workflow.
 * Supports multiple trigger types: event, schedule, webhook, model, and manual.
 *
 * @author Ali Harb <harbzali@gmail.com>
 *
 * @property int $id Primary key
 * @property int $workflow_id Associated workflow ID
 * @property string $name Trigger name
 * @property TriggerType $type Trigger type
 * @property bool $is_active Whether trigger is active
 * @property \ArrayObject<string, mixed> $configuration Type-specific configuration
 * @property \ArrayObject<string, mixed>|null $conditions Additional trigger conditions
 * @property \ArrayObject<string, mixed>|null $context_mapping Context mapping rules
 * @property int $priority Trigger priority (higher = first)
 * @property int|null $max_executions Maximum executions per period
 * @property string|null $max_executions_period Rate limit period
 * @property \Illuminate\Support\Carbon|null $last_triggered_at Last trigger time
 * @property int $trigger_count Total trigger count
 * @property \Illuminate\Support\Carbon $created_at Creation timestamp
 * @property \Illuminate\Support\Carbon $updated_at Last update timestamp
 * @property-read Workflow $workflow
 *
 * @method static Builder<WorkflowTrigger> active() Scope to only active triggers
 * @method static Builder<WorkflowTrigger> ofType(TriggerType $type) Scope to specific type
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
class WorkflowTrigger extends Model
{
    /** @use HasFactory<\AlizHarb\ForgePulse\Database\Factories\WorkflowTriggerFactory> */
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory<static>
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        /** @var \Illuminate\Database\Eloquent\Factories\Factory<static> */
        return \AlizHarb\ForgePulse\Database\Factories\WorkflowTriggerFactory::new();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'workflow_id',
        'name',
        'type',
        'is_active',
        'configuration',
        'conditions',
        'context_mapping',
        'priority',
        'max_executions',
        'max_executions_period',
        'last_triggered_at',
        'trigger_count',
        'team_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TriggerType::class,
            'is_active' => 'boolean',
            'configuration' => AsArrayObject::class,
            'conditions' => AsArrayObject::class,
            'context_mapping' => AsArrayObject::class,
            'last_triggered_at' => 'datetime',
            'trigger_count' => 'integer',
            'priority' => 'integer',
            'max_executions' => 'integer',
        ];
    }

    /**
     * Bootstrap the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        // Auto-populate team_id from workflow and generate webhook token
        static::creating(function (WorkflowTrigger $trigger) {
            // Inherit team_id from workflow for multi-tenancy
            if ($trigger->team_id === null && $trigger->workflow) {
                $trigger->team_id = $trigger->workflow->team_id;
            }

            // Generate webhook token for webhook triggers
            if ($trigger->type === TriggerType::WEBHOOK) {
                $config = $trigger->configuration?->getArrayCopy() ?? [];
                if (empty($config['webhook_token'])) {
                    $config['webhook_token'] = Str::random(64);
                    $trigger->configuration = $config;
                }
            }
        });

        // Register trigger with system when created
        static::created(function (WorkflowTrigger $trigger) {
            $trigger->registerWithSystem();
            static::clearTriggerCaches();
        });

        // Re-register when updated and activated
        static::updated(function (WorkflowTrigger $trigger) {
            if ($trigger->wasChanged('is_active') && $trigger->is_active) {
                $trigger->registerWithSystem();
            }
            static::clearTriggerCaches();
        });

        // Clear caches when deleted
        static::deleted(function () {
            static::clearTriggerCaches();
        });
    }

    /**
     * Get the workflow that owns the trigger.
     *
     * @return BelongsTo<Workflow, $this>
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the team that owns the trigger.
     *
     * @return BelongsTo<\Illuminate\Database\Eloquent\Model, $this>
     */
    public function team(): BelongsTo
    {
        /** @var class-string<\Illuminate\Database\Eloquent\Model> $model */
        $model = config('forgepulse.teams.model', 'App\\Models\\Team');

        return $this->belongsTo($model);
    }

    /**
     * Scope a query to only include active triggers.
     *
     * @param  Builder<WorkflowTrigger>  $query
     * @return Builder<WorkflowTrigger>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include triggers of a specific type.
     *
     * @param  Builder<WorkflowTrigger>  $query
     * @return Builder<WorkflowTrigger>
     */
    public function scopeOfType(Builder $query, TriggerType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to triggers with active workflows.
     *
     * @param  Builder<WorkflowTrigger>  $query
     * @return Builder<WorkflowTrigger>
     */
    public function scopeWithActiveWorkflow(Builder $query): Builder
    {
        return $query->whereHas('workflow', fn ($q) => $q->where('status', 'active'));
    }

    /**
     * Scope a query to triggers for a specific team (multi-tenancy).
     *
     * @param  Builder<WorkflowTrigger>  $query
     * @param  int|null  $teamId  Team ID to filter by
     * @return Builder<WorkflowTrigger>
     */
    public function scopeForTeam(Builder $query, ?int $teamId): Builder
    {
        if ($teamId === null) {
            return $query->whereNull('team_id');
        }

        return $query->where('team_id', $teamId);
    }

    /**
     * Get the current tenant/team ID from context.
     * Override this method to integrate with your tenancy package.
     */
    public static function getCurrentTeamId(): ?int
    {
        // Integration points for popular tenancy packages:
        // - Spatie Multitenancy: app('currentTenant')?->id
        // - Tenancy for Laravel: tenant()?->id
        // - Laravel Jetstream Teams: auth()->user()?->currentTeam?->id
        // - Custom: session('team_id') or request header

        // Default: Try common patterns
        if (function_exists('tenant') && tenant()) {
            return tenant()->id;
        }

        if (auth()->check() && method_exists(auth()->user(), 'currentTeam')) {
            return auth()->user()->currentTeam?->id;
        }

        return null;
    }

    /**
     * Check if the trigger can fire based on rate limits and status.
     */
    public function canFire(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (! $this->workflow->canExecute()) {
            return false;
        }

        if ($this->max_executions === null) {
            return true;
        }

        return $this->getExecutionCountInPeriod() < $this->max_executions;
    }

    /**
     * Get the webhook URL for webhook triggers.
     */
    public function getWebhookUrl(): ?string
    {
        if ($this->type !== TriggerType::WEBHOOK) {
            return null;
        }

        $token = $this->configuration['webhook_token'] ?? null;

        if (! $token) {
            return null;
        }

        return route('forgepulse.api.webhook.trigger', ['token' => $token]);
    }

    /**
     * Record a trigger execution.
     */
    public function recordExecution(): void
    {
        $this->increment('trigger_count');
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Build the execution context from trigger data.
     *
     * @param  mixed  $triggerData  Raw data from the trigger source
     * @return array<string, mixed> Mapped context for workflow execution
     */
    public function buildContext(mixed $triggerData): array
    {
        $mapping = $this->context_mapping?->getArrayCopy() ?? [];

        if (empty($mapping)) {
            return is_array($triggerData) ? $triggerData : ['trigger_data' => $triggerData];
        }

        $context = [];
        foreach ($mapping as $contextKey => $sourcePath) {
            $context[$contextKey] = data_get($triggerData, $sourcePath);
        }

        return $context;
    }

    /**
     * Register this trigger with the appropriate system.
     */
    public function registerWithSystem(): void
    {
        if (! $this->is_active) {
            return;
        }

        match ($this->type) {
            TriggerType::EVENT => $this->registerEventListener(),
            TriggerType::MODEL => $this->registerModelObserver(),
            default => null,
        };
    }

    /**
     * Get the number of executions in the current rate limit period.
     */
    protected function getExecutionCountInPeriod(): int
    {
        if (! $this->last_triggered_at) {
            return 0;
        }

        $periodStart = match ($this->max_executions_period) {
            'per_minute' => now()->subMinute(),
            'per_hour' => now()->subHour(),
            'per_day' => now()->subDay(),
            default => now()->subHour(),
        };

        return $this->workflow->executions()
            ->where('created_at', '>=', $periodStart)
            ->count();
    }

    /**
     * Register an event listener for this trigger.
     */
    protected function registerEventListener(): void
    {
        $eventClass = $this->configuration['event_class'] ?? null;

        if (! $eventClass) {
            return;
        }

        app(EventTriggerListener::class)->registerEvent($eventClass);
    }

    /**
     * Register a model observer for this trigger.
     */
    protected function registerModelObserver(): void
    {
        $modelClass = $this->configuration['model_class'] ?? null;

        if (! $modelClass || ! class_exists($modelClass)) {
            return;
        }

        app(ModelTriggerObserver::class)->observeModel($modelClass);
    }

    /**
     * Clear trigger-related caches.
     */
    public static function clearTriggerCaches(): void
    {
        if (app()->bound(EventTriggerListener::class)) {
            EventTriggerListener::clearCache();
        }

        if (app()->bound(ModelTriggerObserver::class)) {
            ModelTriggerObserver::clearCache();
        }
    }
}
