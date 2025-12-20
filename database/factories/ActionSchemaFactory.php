<?php

declare(strict_types=1);

namespace Database\Factories;

use AlizHarb\ForgePulse\Models\ActionSchema;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActionSchema>
 */
class ActionSchemaFactory extends Factory
{
    protected $model = ActionSchema::class;

    public function definition(): array
    {
        $actionName = $this->faker->words(3, true);

        return [
            'action_class' => 'App\\Actions\\'.str_replace(' ', '', ucwords($actionName)),
            'action_type' => $this->faker->randomElement(['internal', 'external', 'webhook', 'api']),
            'method' => $this->faker->randomElement(['handle', 'execute', '__invoke', null]),
            'schema' => [
                'summary' => ucfirst($actionName),
                'description' => $this->faker->sentence(),
                'operationId' => lcfirst(str_replace(' ', '', ucwords($actionName))),
                'parameters' => [
                    'id' => [
                        'type' => 'integer',
                        'required' => true,
                        'description' => 'Resource ID',
                    ],
                ],
                'responses' => [
                    '200' => [
                        'description' => 'Success',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'success' => ['type' => 'boolean'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'name' => ucfirst($actionName),
            'description' => $this->faker->sentence(),
            'category' => $this->faker->randomElement(['general', 'payments', 'notifications', 'inventory']),
            'tags' => $this->faker->words(3),
            'is_active' => true,
            'is_external' => $this->faker->boolean(30),
            'source' => $this->faker->randomElement(['manual', 'code', 'imported']),
            'version' => 1,
            'config' => [
                'timeout' => $this->faker->randomElement([15, 30, 60]),
            ],
        ];
    }

    /**
     * Indicate that the action is external.
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'action_type' => 'external',
            'is_external' => true,
            'source' => 'imported',
            'config' => [
                'endpoint' => $this->faker->url(),
                'method' => 'POST',
                'timeout' => 30,
            ],
        ]);
    }

    /**
     * Indicate that the action is code-based.
     */
    public function codeBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'code',
            'is_external' => false,
        ]);
    }

    /**
     * Indicate that the action is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
