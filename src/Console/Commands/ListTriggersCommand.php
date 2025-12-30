<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Console\Commands;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use Illuminate\Console\Command;

/**
 * List Triggers Command
 *
 * Displays all workflow triggers in a table format.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
class ListTriggersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'forgepulse:triggers
                            {--type= : Filter by trigger type (event, schedule, webhook, model, manual)}
                            {--active : Only show active triggers}
                            {--workflow= : Filter by workflow ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List all workflow triggers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = WorkflowTrigger::with('workflow');

        // Apply filters
        if ($type = $this->option('type')) {
            $triggerType = TriggerType::tryFrom($type);
            if (! $triggerType) {
                $this->error("Invalid trigger type: {$type}");
                $this->line('Valid types: '.implode(', ', array_column(TriggerType::cases(), 'value')));

                return self::FAILURE;
            }
            $query->where('type', $triggerType);
        }

        if ($this->option('active')) {
            $query->where('is_active', true);
        }

        if ($workflowId = $this->option('workflow')) {
            $query->where('workflow_id', $workflowId);
        }

        $triggers = $query->orderBy('workflow_id')->orderBy('priority', 'desc')->get();

        if ($triggers->isEmpty()) {
            $this->info('No triggers found.');

            return self::SUCCESS;
        }

        $rows = $triggers->map(function (WorkflowTrigger $trigger) {
            $status = $trigger->is_active
                ? '<fg=green>Active</>'
                : '<fg=gray>Inactive</>';

            $typeColor = match ($trigger->type) {
                TriggerType::EVENT => 'magenta',
                TriggerType::SCHEDULE => 'blue',
                TriggerType::WEBHOOK => 'cyan',
                TriggerType::MODEL => 'green',
                TriggerType::MANUAL => 'gray',
            };

            return [
                $trigger->id,
                $trigger->name,
                "<fg={$typeColor}>{$trigger->type->value}</>",
                $trigger->workflow->name ?? 'Unknown',
                $status,
                $trigger->trigger_count,
                $trigger->last_triggered_at?->diffForHumans() ?? 'Never',
            ];
        });

        $this->table(
            ['ID', 'Name', 'Type', 'Workflow', 'Status', 'Count', 'Last Triggered'],
            $rows
        );

        $this->newLine();
        $this->line("Total: {$triggers->count()} trigger(s)");

        return self::SUCCESS;
    }
}
