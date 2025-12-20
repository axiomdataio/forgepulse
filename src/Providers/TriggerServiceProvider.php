<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Providers;

use AlizHarb\ForgePulse\Services\TriggerManager;
use Illuminate\Support\ServiceProvider;

/**
 * Trigger Service Provider
 *
 * Registers workflow triggers on application boot.
 */
class TriggerServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(TriggerManager::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only register triggers if enabled in config
        if (! config('forgepulse.triggers.enabled', true)) {
            return;
        }

        // Register all workflow triggers
        if (! $this->app->runningInConsole() || $this->app->runningUnitTests()) {
            try {
                $manager = app(TriggerManager::class);
                $registered = $manager->registerAll();

                if ($registered > 0) {
                    \Log::info("ForgePulse: Registered {$registered} workflow trigger(s)");
                }
            } catch (\Exception $e) {
                \Log::error("ForgePulse: Failed to register workflow triggers: {$e->getMessage()}");
            }
        }
    }
}
