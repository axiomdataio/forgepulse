<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Console\Commands;

use AlizHarb\ForgePulse\Services\Triggers\EventTriggerListener;
use AlizHarb\ForgePulse\Services\Triggers\ModelTriggerObserver;
use Illuminate\Console\Command;

/**
 * Register Trigger Listeners Command
 *
 * Manually registers all event and model trigger listeners.
 * Useful for debugging or forcing re-registration.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
class RegisterTriggerListenersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forgepulse:register-listeners
                            {--clear-cache : Clear trigger caches before registering}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Register all event and model trigger listeners';

    /**
     * Execute the console command.
     */
    public function handle(
        EventTriggerListener $eventListener,
        ModelTriggerObserver $modelObserver
    ): int {
        if ($this->option('clear-cache')) {
            $this->info('Clearing trigger caches...');
            EventTriggerListener::clearCache();
            ModelTriggerObserver::clearCache();
            $this->line('  Caches cleared.');
        }

        $this->info('Registering event trigger listeners...');
        $eventListener->registerAllEventTriggers();

        $registeredEvents = $eventListener->getRegisteredEvents();
        if (empty($registeredEvents)) {
            $this->line('  No event triggers found.');
        } else {
            foreach ($registeredEvents as $eventClass) {
                $this->line("  - {$eventClass}");
            }
            $this->info('  Registered '.count($registeredEvents).' event listener(s).');
        }

        $this->newLine();

        $this->info('Registering model trigger observers...');
        $modelObserver->registerAllModelTriggers();

        $observedModels = $modelObserver->getObservedModels();
        if (empty($observedModels)) {
            $this->line('  No model triggers found.');
        } else {
            foreach ($observedModels as $modelClass) {
                $this->line("  - {$modelClass}");
            }
            $this->info('  Registered '.count($observedModels).' model observer(s).');
        }

        $this->newLine();
        $this->info('All trigger listeners registered successfully.');

        return self::SUCCESS;
    }
}
