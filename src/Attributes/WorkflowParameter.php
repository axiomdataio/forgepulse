<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Attributes;

use Attribute;

/**
 * Workflow Parameter Attribute
 *
 * Provides metadata about an action parameter.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class WorkflowParameter
{
    /**
     * @param  string|null  $description  Parameter description
     * @param  string|null  $example  Example value
     * @param  array<string, mixed>|null  $validation  Validation rules (min, max, pattern, etc.)
     * @param  string|null  $source  Where the value comes from ('context', 'config', 'user')
     */
    public function __construct(
        public readonly ?string $description = null,
        public readonly ?string $example = null,
        public readonly ?array $validation = null,
        public readonly ?string $source = 'config',
    ) {}
}
