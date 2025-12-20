<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Attributes;

use Attribute;

/**
 * Workflow Action Attribute
 *
 * Provides metadata about an action for workflow integration.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class WorkflowAction
{
    /**
     * @param  string|null  $name  Display name for the action
     * @param  string|null  $description  Description of what the action does
     * @param  string|null  $category  Category for grouping (e.g., 'payment', 'notification')
     * @param  array<string>  $tags  Tags for filtering/searching
     * @param  int|null  $timeout  Recommended timeout in seconds
     */
    public function __construct(
        public readonly ?string $name = null,
        public readonly ?string $description = null,
        public readonly ?string $category = null,
        public readonly array $tags = [],
        public readonly ?int $timeout = null,
    ) {}
}
