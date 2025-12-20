<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Console\Commands;

use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Console\Command;

/**
 * Execute Scheduled Workflows Command
 *
 * Processes workflows with schedule triggers.
 */
class ExecuteScheduledWorkflows extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forgepulse:execute-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute workflows with schedule triggers based on their cron expressions';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $workflows = Workflow::where('auto_trigger_enabled', true)
            ->where('trigger_type', 'schedule')
            ->active()
            ->get();

        if ($workflows->isEmpty()) {
            $this->info('No scheduled workflows found.');

            return self::SUCCESS;
        }

        $executed = 0;

        foreach ($workflows as $workflow) {
            $config = $workflow->trigger_config?->getArrayCopy() ?? [];
            $cronExpression = $config['cron_expression'] ?? null;

            if (! $cronExpression) {
                $this->warn("Workflow {$workflow->id} has no cron expression configured.");
                continue;
            }

            // Check if workflow should run based on cron expression
            if ($this->shouldRunNow($cronExpression, $config['timezone'] ?? 'UTC')) {
                try {
                    $context = array_merge(
                        $config['context'] ?? [],
                        [
                            'scheduled_execution' => true,
                            '_triggered_at' => now()->toISOString(),
                            '_cron_expression' => $cronExpression,
                        ]
                    );

                    $execution = $workflow->execute($context);
                    $this->info("Executed workflow {$workflow->id} (Execution ID: {$execution->id})");
                    $executed++;
                } catch (\Exception $e) {
                    $this->error("Failed to execute workflow {$workflow->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Executed {$executed} scheduled workflow(s).");

        return self::SUCCESS;
    }

    /**
     * Check if the cron expression matches current time.
     *
     * @param  string  $cronExpression  The cron expression
     * @param  string  $timezone  The timezone
     * @return bool True if should run now
     */
    private function shouldRunNow(string $cronExpression, string $timezone): bool
    {
        // Use Cron Expression Parser (if available)
        // For simplicity, this is a basic implementation
        // In production, use a library like mtdowling/cron-expression

        try {
            $cron = new \Cron\CronExpression($cronExpression);
            $now = now($timezone);

            return $cron->isDue($now->toDateTime());
        } catch (\Exception $e) {
            $this->warn("Invalid cron expression: {$cronExpression}");

            return false;
        }
    }
}
