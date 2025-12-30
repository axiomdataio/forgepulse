<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Resources;

use AlizHarb\ForgePulse\Enums\TriggerType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Trigger Resource
 *
 * Transforms WorkflowTrigger model data for API responses.
 *
 * @author Ali Harb <harbzali@gmail.com>
 *
 * @mixin \AlizHarb\ForgePulse\Models\WorkflowTrigger
 */
class TriggerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'type_description' => $this->type->description(),
            'type_color' => $this->type->color(),
            'is_active' => $this->is_active,
            'configuration' => $this->configuration?->getArrayCopy() ?? [],
            'conditions' => $this->conditions?->getArrayCopy(),
            'context_mapping' => $this->context_mapping?->getArrayCopy(),
            'priority' => $this->priority,
            'max_executions' => $this->max_executions,
            'max_executions_period' => $this->max_executions_period,
            'last_triggered_at' => $this->last_triggered_at?->toIso8601String(),
            'trigger_count' => $this->trigger_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Computed fields
            'webhook_url' => $this->when(
                $this->type === TriggerType::WEBHOOK,
                fn () => $this->getWebhookUrl()
            ),
            'next_run_at' => $this->when(
                $this->type === TriggerType::SCHEDULE,
                fn () => $this->getNextRunTime()
            ),
        ];
    }

    /**
     * Get the next run time for schedule triggers.
     */
    protected function getNextRunTime(): ?string
    {
        if ($this->type !== TriggerType::SCHEDULE) {
            return null;
        }

        $cronExpression = $this->configuration['cron_expression'] ?? null;

        if (! $cronExpression) {
            return null;
        }

        try {
            $timezone = $this->configuration['timezone'] ?? 'UTC';
            $cron = new \Cron\CronExpression($cronExpression);

            return \Illuminate\Support\Carbon::instance($cron->getNextRunDate())
                ->setTimezone($timezone)
                ->toIso8601String();
        } catch (\Exception) {
            return null;
        }
    }
}
