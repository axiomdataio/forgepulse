<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\Triggers;

use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Schedule Trigger Runner Service
 *
 * Manages execution of scheduled workflow triggers based on cron expressions.
 * Supports overlap prevention and timezone-aware scheduling.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
final readonly class ScheduleTriggerRunner
{
    public function __construct(
        private TriggerManager $triggerManager
    ) {}

    /**
     * Check and fire all due scheduled triggers.
     *
     * @return array<array{trigger_id: int, workflow_id: int, execution_id: int}>
     */
    public function runDueTriggers(): array
    {
        $executedWorkflows = [];
        $triggers = $this->triggerManager->getScheduledTriggers();

        foreach ($triggers as $trigger) {
            if (! $this->isDue($trigger)) {
                continue;
            }

            // Check for overlap prevention
            if ($this->shouldPreventOverlap($trigger)) {
                $lock = $this->acquireLock($trigger);

                if (! $lock) {
                    $this->log("Trigger '{$trigger->name}' skipped due to overlap prevention");

                    continue;
                }

                try {
                    $execution = $this->executeTrigger($trigger);

                    if ($execution) {
                        $executedWorkflows[] = [
                            'trigger_id' => $trigger->id,
                            'workflow_id' => $trigger->workflow_id,
                            'execution_id' => $execution->id,
                        ];
                    }
                } finally {
                    $this->releaseLock($trigger);
                }
            } else {
                $execution = $this->executeTrigger($trigger);

                if ($execution) {
                    $executedWorkflows[] = [
                        'trigger_id' => $trigger->id,
                        'workflow_id' => $trigger->workflow_id,
                        'execution_id' => $execution->id,
                    ];
                }
            }
        }

        return $executedWorkflows;
    }

    /**
     * Check if a scheduled trigger is due.
     */
    public function isDue(WorkflowTrigger $trigger): bool
    {
        $cronExpression = $trigger->configuration['cron_expression'] ?? null;

        if (! $cronExpression) {
            return false;
        }

        try {
            $timezone = $trigger->configuration['timezone'] ?? 'UTC';
            $cron = new CronExpression($cronExpression);

            return $cron->isDue(Carbon::now($timezone));
        } catch (\Exception $e) {
            $this->log("Invalid cron expression for trigger '{$trigger->name}': {$e->getMessage()}", 'error');

            return false;
        }
    }

    /**
     * Get the next run time for a trigger.
     */
    public function getNextRunTime(WorkflowTrigger $trigger): ?Carbon
    {
        $cronExpression = $trigger->configuration['cron_expression'] ?? null;

        if (! $cronExpression) {
            return null;
        }

        try {
            $timezone = $trigger->configuration['timezone'] ?? 'UTC';
            $cron = new CronExpression($cronExpression);

            return Carbon::instance($cron->getNextRunDate())->setTimezone($timezone);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the previous run time for a trigger.
     */
    public function getPreviousRunTime(WorkflowTrigger $trigger): ?Carbon
    {
        $cronExpression = $trigger->configuration['cron_expression'] ?? null;

        if (! $cronExpression) {
            return null;
        }

        try {
            $timezone = $trigger->configuration['timezone'] ?? 'UTC';
            $cron = new CronExpression($cronExpression);

            return Carbon::instance($cron->getPreviousRunDate())->setTimezone($timezone);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Validate a cron expression.
     */
    public function isValidCronExpression(string $expression): bool
    {
        try {
            new CronExpression($expression);

            return true;
        } catch (\Exception) {
            return false;
        }
    }

    /**
     * Execute a trigger.
     */
    protected function executeTrigger(WorkflowTrigger $trigger): ?\AlizHarb\ForgePulse\Models\WorkflowExecution
    {
        $this->log("Executing scheduled trigger '{$trigger->name}'");

        return $this->triggerManager->fire($trigger, [
            'scheduled_at' => now()->toIso8601String(),
            'cron_expression' => $trigger->configuration['cron_expression'] ?? '',
            'timezone' => $trigger->configuration['timezone'] ?? 'UTC',
        ]);
    }

    /**
     * Check if overlap prevention should be applied.
     */
    protected function shouldPreventOverlap(WorkflowTrigger $trigger): bool
    {
        return ($trigger->configuration['overlap_prevention'] ?? true)
            && config('forgepulse.triggers.schedule.use_locks', true);
    }

    /**
     * Acquire a lock for the trigger.
     */
    protected function acquireLock(WorkflowTrigger $trigger): bool
    {
        $lockKey = $this->getLockKey($trigger);
        $lockTtl = config('forgepulse.triggers.schedule.lock_ttl', 60);

        $lock = Cache::lock($lockKey, $lockTtl);

        return $lock->get();
    }

    /**
     * Release a lock for the trigger.
     */
    protected function releaseLock(WorkflowTrigger $trigger): void
    {
        $lockKey = $this->getLockKey($trigger);
        Cache::lock($lockKey)->forceRelease();
    }

    /**
     * Get the lock key for a trigger.
     */
    protected function getLockKey(WorkflowTrigger $trigger): string
    {
        // Include the minute to prevent duplicate executions in the same minute
        return 'forgepulse:schedule:'.$trigger->id.':'.now()->format('Y-m-d-H-i');
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
        Log::channel($channel)->$level("[ForgePulse Schedule] {$message}");
    }
}
