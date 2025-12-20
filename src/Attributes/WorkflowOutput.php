<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Attributes;

use Attribute;

/**
 * Workflow Output Attribute
 *
 * Describes what an action returns to the workflow context.
 */
#[Attribute(Attribute::TARGET_METHOD)]
class WorkflowOutput
{
    /**
     * @param  array<string, array<string, mixed>>  $fields  Output fields with schema
     *
     * Example:
     * [
     *   'order_id' => ['type' => 'int', 'description' => 'Created order ID'],
     *   'total' => ['type' => 'float', 'description' => 'Order total'],
     * ]
     */
    public function __construct(
        public readonly array $fields = [],
    ) {}
}
