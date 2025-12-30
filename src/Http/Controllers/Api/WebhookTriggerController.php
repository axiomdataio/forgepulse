<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Http\Controllers\Api;

use AlizHarb\ForgePulse\Http\Resources\ExecutionResource;
use AlizHarb\ForgePulse\Models\WorkflowTrigger;
use AlizHarb\ForgePulse\Services\Triggers\TriggerManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

/**
 * Webhook Trigger Controller
 *
 * Handles incoming webhook requests to trigger workflows.
 * Supports signature validation and IP filtering.
 *
 * @author Ali Harb <harbzali@gmail.com>
 */
class WebhookTriggerController extends Controller
{
    public function __construct(
        private readonly TriggerManager $triggerManager
    ) {}

    /**
     * Handle incoming webhook trigger.
     */
    public function handle(Request $request, string $token): JsonResponse
    {
        $trigger = $this->triggerManager->findWebhookTrigger($token);

        if (! $trigger) {
            $this->log("Webhook request with invalid token: {$token}", 'warning');

            return response()->json([
                'error' => 'Invalid webhook token.',
            ], 404);
        }

        // Validate signature if configured
        if (! $this->validateSignature($request, $trigger)) {
            $this->log("Webhook signature validation failed for trigger '{$trigger->name}'", 'warning');

            return response()->json([
                'error' => 'Invalid signature.',
            ], 401);
        }

        // Validate IP if configured
        if (! $this->validateIp($request, $trigger)) {
            $this->log("Webhook IP not allowed for trigger '{$trigger->name}': {$request->ip()}", 'warning');

            return response()->json([
                'error' => 'IP address not allowed.',
            ], 403);
        }

        // Fire the trigger
        $execution = $this->triggerManager->fire($trigger, $this->getWebhookData($request));

        if (! $execution) {
            return response()->json([
                'message' => 'Trigger could not fire. It may be rate limited or conditions were not met.',
                'trigger_id' => $trigger->id,
                'workflow_id' => $trigger->workflow_id,
            ], 429);
        }

        $this->log("Webhook trigger '{$trigger->name}' fired successfully, execution ID: {$execution->id}");

        return response()->json([
            'message' => 'Workflow triggered successfully.',
            'execution' => new ExecutionResource($execution),
        ], 202);
    }

    /**
     * Get webhook data from request.
     *
     * @return array<string, mixed>
     */
    protected function getWebhookData(Request $request): array
    {
        return [
            'payload' => $request->all(),
            'headers' => $this->getSafeHeaders($request),
            'method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'ip' => $request->ip(),
            'received_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Get safe headers (excluding sensitive ones).
     *
     * @return array<string, mixed>
     */
    protected function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();

        // Remove sensitive headers
        $sensitiveHeaders = [
            'authorization',
            'cookie',
            'x-signature',
            'x-hub-signature',
            'x-hub-signature-256',
        ];

        foreach ($sensitiveHeaders as $header) {
            unset($headers[$header]);
        }

        return $headers;
    }

    /**
     * Validate webhook signature.
     */
    protected function validateSignature(Request $request, WorkflowTrigger $trigger): bool
    {
        $config = $trigger->configuration;

        if (! ($config['validate_signature'] ?? false)) {
            return true;
        }

        $signatureHeader = $config['signature_header'] ?? 'X-Signature';
        $secretToken = $config['secret_token'] ?? '';

        if (empty($secretToken)) {
            return true;
        }

        $providedSignature = $request->header($signatureHeader);

        if (! $providedSignature) {
            return false;
        }

        // Support multiple signature formats
        $content = $request->getContent();

        // Try HMAC-SHA256
        $expectedSha256 = hash_hmac('sha256', $content, $secretToken);
        if (hash_equals($expectedSha256, $providedSignature)) {
            return true;
        }

        // Try with sha256= prefix (GitHub style)
        if (hash_equals('sha256='.$expectedSha256, $providedSignature)) {
            return true;
        }

        // Try HMAC-SHA1
        $expectedSha1 = hash_hmac('sha1', $content, $secretToken);
        if (hash_equals($expectedSha1, $providedSignature)) {
            return true;
        }

        // Try with sha1= prefix
        if (hash_equals('sha1='.$expectedSha1, $providedSignature)) {
            return true;
        }

        return false;
    }

    /**
     * Validate request IP.
     */
    protected function validateIp(Request $request, WorkflowTrigger $trigger): bool
    {
        $allowedIps = $trigger->configuration['allowed_ips'] ?? [];

        if (empty($allowedIps)) {
            return true;
        }

        $requestIp = $request->ip();

        foreach ($allowedIps as $allowedIp) {
            // Exact match
            if ($requestIp === $allowedIp) {
                return true;
            }

            // CIDR notation support
            if (str_contains($allowedIp, '/') && $this->ipInCidr($requestIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // IPv4
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        // IPv6 not supported for now
        return false;
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
        Log::channel($channel)->$level("[ForgePulse Webhook] {$message}");
    }
}
