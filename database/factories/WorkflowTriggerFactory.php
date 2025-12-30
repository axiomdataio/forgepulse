<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Database\Factories;

use AlizHarb\ForgePulse\Enums\TriggerType;
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * WorkflowTrigger Factory
 *
 * @extends Factory<WorkflowTrigger>
 */
class WorkflowTriggerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WorkflowTrigger>
     */
    protected $model = WorkflowTrigger::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'workflow_id' => Workflow::factory(),
            'name' => $this->faker->words(3, true).' Trigger',
            'type' => $this->faker->randomElement(TriggerType::cases()),
            'is_active' => true,
            'configuration' => [],
            'conditions' => null,
            'context_mapping' => null,
            'priority' => $this->faker->numberBetween(0, 100),
            'max_executions' => null,
            'max_executions_period' => null,
            'trigger_count' => 0,
        ];
    }

    /**
     * Configure the trigger as an event trigger.
     */
    public function event(string $eventClass = 'App\\Events\\TestEvent'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TriggerType::EVENT,
            'configuration' => [
                'event_class' => $eventClass,
                'listen_once' => false,
            ],
        ]);
    }

    /**
     * Configure the trigger as a schedule trigger.
     */
    public function schedule(string $cronExpression = '0 * * * *', string $timezone = 'UTC'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TriggerType::SCHEDULE,
            'configuration' => [
                'cron_expression' => $cronExpression,
                'timezone' => $timezone,
                'overlap_prevention' => true,
            ],
        ]);
    }

    /**
     * Configure the trigger as a webhook trigger.
     */
    public function webhook(bool $validateSignature = false): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TriggerType::WEBHOOK,
            'configuration' => [
                'validate_signature' => $validateSignature,
                'signature_header' => 'X-Signature',
                'allowed_ips' => [],
            ],
        ]);
    }

    /**
     * Configure the trigger as a model trigger.
     */
    public function model(string $modelClass = 'App\\Models\\User', array $events = ['created']): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TriggerType::MODEL,
            'configuration' => [
                'model_class' => $modelClass,
                'events' => $events,
                'attribute_filters' => [],
            ],
        ]);
    }

    /**
     * Configure the trigger as a manual trigger.
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TriggerType::MANUAL,
            'configuration' => [
                'description' => 'Manual execution',
            ],
        ]);
    }

    /**
     * Configure the trigger as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Configure the trigger with rate limiting.
     */
    public function rateLimited(int $maxExecutions = 10, string $period = 'per_hour'): static
    {
        return $this->state(fn (array $attributes) => [
            'max_executions' => $maxExecutions,
            'max_executions_period' => $period,
        ]);
    }

    /**
     * Configure the trigger with context mapping.
     */
    public function withContextMapping(array $mapping): static
    {
        return $this->state(fn (array $attributes) => [
            'context_mapping' => $mapping,
        ]);
    }

    /**
     * Configure the trigger with conditions.
     */
    public function withConditions(array $conditions): static
    {
        return $this->state(fn (array $attributes) => [
            'conditions' => $conditions,
        ]);
    }
}
