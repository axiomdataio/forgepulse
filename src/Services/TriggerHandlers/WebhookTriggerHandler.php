<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\TriggerHandlers;

use AlizHarb\ForgePulse\Models\Workflow;
use Illuminate\Support\Str;

/**
 * Webhook Trigger Handler
 *
 * Handles triggering workflows via incoming HTTP webhooks.
 */
final readonly class WebhookTriggerHandler implements TriggerHandler
{
    /**
     * Register the trigger for the workflow.
     */
    public function register(Workflow $workflow): bool
    {
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];

        // Generate webhook token if not exists
        if (empty($config['token'])) {
            $config['token'] = Str::random(32);
            $workflow->update(['trigger_config' => $config]);
        }

        // Webhook endpoint will be: /api/forgepulse/webhook/{workflow_id}/{token}
        // This is handled by a dedicated controller, not registered here

        return true;
    }

    /**
     * Unregister the trigger for the workflow.
     */
    public function unregister(Workflow $workflow): bool
    {
        // Optionally revoke token
        $config = $workflow->trigger_config?->getArrayCopy() ?? [];
        $config['token'] = null;
        $workflow->update(['trigger_config' => $config]);

        return true;
    }

    /**
     * Validate the trigger configuration.
     */
    public function validate(array $config): bool
    {
        // Token will be auto-generated if not provided
        // Validation rules are optional

        return true;
    }

    /**
     * Extract context data from webhook payload.
     */
    public function extractContext(mixed $webhookData): array
    {
        $context = [];

        // If webhook data is an array (typical for JSON payloads)
        if (is_array($webhookData)) {
            $context = $webhookData;
        }

        // If webhook data is a request object
        if ($webhookData instanceof \Illuminate\Http\Request) {
            $context = $webhookData->all();
            
            // Add request metadata
            $context['_webhook_ip'] = $webhookData->ip();
            $context['_webhook_user_agent'] = $webhookData->userAgent();
            $context['_webhook_method'] = $webhookData->method();
        }

        $context['_triggered_at'] = now()->toISOString();

        return $context;
    }
}
