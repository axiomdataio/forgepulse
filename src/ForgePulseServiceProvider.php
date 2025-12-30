<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse;

use AlizHarb\ForgePulse\Livewire\WorkflowBuilder;
use AlizHarb\ForgePulse\Livewire\WorkflowExecutionTracker;
use AlizHarb\ForgePulse\Livewire\WorkflowStepEditor;
use AlizHarb\ForgePulse\Livewire\WorkflowTemplateManager;
use AlizHarb\ForgePulse\Livewire\WorkflowVersionHistory;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Policies\WorkflowPolicy;
use AlizHarb\ForgePulse\Services\Triggers\EventTriggerListener;
use AlizHarb\ForgePulse\Services\Triggers\ModelTriggerObserver;
use AlizHarb\ForgePulse\Services\Triggers\ScheduleTriggerRunner;
use AlizHarb\ForgePulse\Services\Triggers\TriggerManager;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

/**
 * ForgePulse Service Provider
 *
 * Bootstraps the ForgePulse package, registers services, publishes assets,
 * and configures Livewire components.
 */
class ForgePulseServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/forgepulse.php',
            'forgepulse'
        );

        // Register core services
        $this->app->singleton(Services\WorkflowEngine::class);
        $this->app->singleton(Services\StepExecutor::class);
        $this->app->singleton(Services\ConditionalEvaluator::class);
        $this->app->singleton(Services\WorkflowValidator::class);
        $this->app->singleton(Services\TemplateManager::class);

        // Register trigger services
        $this->app->singleton(TriggerManager::class);
        $this->app->singleton(EventTriggerListener::class);
        $this->app->singleton(ModelTriggerObserver::class);
        $this->app->singleton(ScheduleTriggerRunner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'forgepulse');
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'forgepulse');

        // Register Livewire components
        $this->registerLivewireComponents();

        // Register policies
        $this->registerPolicies();

        // Register API routes
        $this->registerApiRoutes();

        // Register trigger system
        if (config('forgepulse.triggers.enabled', true)) {
            $this->registerTriggerSystem();
        }

        // Publish package assets and register commands
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/forgepulse.php' => config_path('forgepulse.php'),
            ], 'forgepulse-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'forgepulse-migrations');

            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/forgepulse'),
            ], 'forgepulse-views');

            $this->publishes([
                __DIR__.'/../resources/js' => public_path('vendor/forgepulse/js'),
                __DIR__.'/../resources/css' => public_path('vendor/forgepulse/css'),
            ], 'forgepulse-assets');

            // Register console commands
            $this->commands([
                Console\Commands\RunScheduledTriggersCommand::class,
                Console\Commands\RegisterTriggerListenersCommand::class,
                Console\Commands\ListTriggersCommand::class,
            ]);
        }
    }

    /**
     * Register Livewire components.
     */
    protected function registerLivewireComponents(): void
    {
        if (! class_exists(Livewire::class)) {
            return;
        }

        // Register with dot notation (for forgepulse.workflow-builder usage)
        Livewire::component('forgepulse.workflow-builder', WorkflowBuilder::class);
        Livewire::component('forgepulse.workflow-step-editor', WorkflowStepEditor::class);
        Livewire::component('forgepulse.workflow-execution-tracker', WorkflowExecutionTracker::class);
        Livewire::component('forgepulse.workflow-template-manager', WorkflowTemplateManager::class);
        Livewire::component('forgepulse.workflow-version-history', WorkflowVersionHistory::class);

        // Register with double colon notation (for forgepulse:: usage)
        Livewire::component('forgepulse::workflow-builder', WorkflowBuilder::class);
        Livewire::component('forgepulse::workflow-step-editor', WorkflowStepEditor::class);
        Livewire::component('forgepulse::workflow-execution-tracker', WorkflowExecutionTracker::class);
        Livewire::component('forgepulse::workflow-template-manager', WorkflowTemplateManager::class);
        Livewire::component('forgepulse::workflow-version-history', WorkflowVersionHistory::class);
    }

    /**
     * Register authorization policies.
     */
    protected function registerPolicies(): void
    {
        Gate::policy(Workflow::class, WorkflowPolicy::class);
    }

    /**
     * Register API routes.
     */
    protected function registerApiRoutes(): void
    {
        if (config('forgepulse.api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        }
    }

    /**
     * Register the trigger system.
     */
    protected function registerTriggerSystem(): void
    {
        // Auto-register event listeners
        if (config('forgepulse.triggers.events.auto_register', true)) {
            $this->app->booted(function () {
                try {
                    $this->app->make(EventTriggerListener::class)
                        ->registerAllEventTriggers();
                } catch (\Exception $e) {
                    // Silently fail if database not available (e.g., during migrations)
                    if (config('app.debug')) {
                        logger()->warning('ForgePulse: Could not register event triggers: '.$e->getMessage());
                    }
                }
            });
        }

        // Auto-observe models
        if (config('forgepulse.triggers.model.auto_observe', true)) {
            $this->app->booted(function () {
                try {
                    $this->app->make(ModelTriggerObserver::class)
                        ->registerAllModelTriggers();
                } catch (\Exception $e) {
                    // Silently fail if database not available (e.g., during migrations)
                    if (config('app.debug')) {
                        logger()->warning('ForgePulse: Could not register model observers: '.$e->getMessage());
                    }
                }
            });
        }

        // Register scheduled trigger command with Laravel's scheduler
        $this->app->booted(function () {
            if ($this->app->bound(Schedule::class)) {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('forgepulse:run-schedules')
                    ->everyMinute()
                    ->withoutOverlapping()
                    ->runInBackground();
            }
        });
    }
}
