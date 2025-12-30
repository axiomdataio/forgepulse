<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Console\Commands;

use AlizHarb\ForgePulse\Services\Triggers\ScheduleTriggerRunner;
use Illuminate\Console\Command;

/**
 * Run Scheduled Triggers Command
 *
 * Checks for and executes all due scheduled workflow triggers.
 * Should be run every minute via Laravel's scheduler.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
class RunScheduledTriggersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forgepulse:run-schedules
                            {--dry-run : Show what would be executed without actually running}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all due scheduled workflow triggers';

    /**
     * Execute the console command.
     */
    public function handle(ScheduleTriggerRunner $runner): int
    {
        $this->info('Checking for due scheduled triggers...');

        if ($this->option('dry-run')) {
            return $this->dryRun($runner);
        }

        $executed = $runner->runDueTriggers();

        if (empty($executed)) {
            $this->line('No triggers were due.');

            return self::SUCCESS;
        }

        $this->info(count($executed).' workflow(s) triggered:');

        foreach ($executed as $exec) {
            $this->line(sprintf(
                '  - Trigger #%d → Workflow #%d (Execution #%d)',
                $exec['trigger_id'],
                $exec['workflow_id'],
                $exec['execution_id']
            ));
        }

        return self::SUCCESS;
    }

    /**
     * Perform a dry run, showing what would be executed.
     */
    protected function dryRun(ScheduleTriggerRunner $runner): int
    {
        $triggers = app(\AlizHarb\ForgePulse\Services\Triggers\TriggerManager::class)
            ->getScheduledTriggers();

        if ($triggers->isEmpty()) {
            $this->line('No scheduled triggers found.');

            return self::SUCCESS;
        }

        $this->info('Scheduled triggers status:');

        $rows = [];
        foreach ($triggers as $trigger) {
            $isDue = $runner->isDue($trigger);
            $nextRun = $runner->getNextRunTime($trigger);

            $rows[] = [
                $trigger->id,
                $trigger->name,
                $trigger->workflow->name ?? 'Unknown',
                $trigger->configuration['cron_expression'] ?? 'N/A',
                $isDue ? '<fg=green>Yes</>' : '<fg=gray>No</>',
                $nextRun?->format('Y-m-d H:i:s') ?? 'N/A',
            ];
        }

        $this->table(
            ['ID', 'Trigger', 'Workflow', 'Cron', 'Due Now', 'Next Run'],
            $rows
        );

        return self::SUCCESS;
    }
}
