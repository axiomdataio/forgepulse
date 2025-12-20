<?php

declare(strict_types=1);

namespace AlizHarb\ForgePulse\Services\StepHandlers;

use AlizHarb\ForgePulse\Models\WorkflowStep;

/**
 * Action Handler
 *
 * Executes custom actions, services, or job classes defined in step configuration.
 * Supports multiple Laravel conventions for maximum flexibility.
 */
class ActionHandler
{
    /**
     * Handle the step execution.
     *
     * @param  WorkflowStep  $step  The step to execute
     * @param  array<string, mixed>  $context  Execution context
     * @return array<string, mixed> Output data
     */
    public function handle(WorkflowStep $step, array $context): array
    {
        /** @var array<string, mixed> $config */
        $config = $step->configuration;

        // Support both 'action_class' (backward compat) and 'class'
        $actionClass = $config['class'] ?? $config['action_class'] ?? null;
        $method = $config['method'] ?? null;
        $mode = $config['mode'] ?? 'sync'; // 'sync' or 'dispatch'

        if (! $actionClass || ! class_exists($actionClass)) {
            throw new \RuntimeException("Action class not found: {$actionClass}");
        }

        $parameters = $config['parameters'] ?? [];

        // Substitute context variables in parameters
        $parameters = $this->substituteVariables($parameters, $context);

        // Handle dispatch mode (fire and forget)
        if ($mode === 'dispatch') {
            return $this->dispatchJob($actionClass, $method, $parameters, $context, $config);
        }

        // Handle sync mode (call directly and return result)
        return $this->executeSync($actionClass, $method, $parameters, $context);
    }

    /**
     * Execute action synchronously.
     *
     * @param  class-string  $actionClass  The class to instantiate
     * @param  string|null  $method  Method to call (or auto-detect)
     * @param  array<string, mixed>  $parameters  Parameters to pass
     * @param  array<string, mixed>  $context  Execution context
     * @return array<string, mixed> Output data
     */
    protected function executeSync(
        string $actionClass,
        ?string $method,
        array $parameters,
        array $context
    ): array {
        $action = app($actionClass);

        // Determine which method to call
        $methodToCall = $this->determineMethod($action, $method);

        if (! $methodToCall) {
            throw new \RuntimeException(
                "No suitable method found on {$actionClass}. ".
                'Tried: '.$this->getTriedMethods($method)
            );
        }

        // Call the method
        /** @phpstan-ignore-next-line */
        $result = $action->$methodToCall($parameters, $context);

        // Normalize result to array
        if (is_array($result)) {
            return $result;
        }

        if ($result === null) {
            return [];
        }

        return ['result' => $result];
    }

    /**
     * Dispatch a job asynchronously (fire and forget).
     *
     * @param  class-string  $jobClass  The job class
     * @param  string|null  $method  Method to call (ignored for dispatch)
     * @param  array<string, mixed>  $parameters  Parameters to pass
     * @param  array<string, mixed>  $context  Execution context
     * @param  array<string, mixed>  $config  Step configuration
     * @return array<string, mixed> Output data
     */
    protected function dispatchJob(
        string $jobClass,
        ?string $method,
        array $parameters,
        array $context,
        array $config
    ): array {
        // Create job instance
        $job = new $jobClass(...array_values($parameters));

        // Apply queue and delay if specified
        $queue = $config['queue'] ?? null;
        $delay = $config['delay'] ?? null;

        if ($queue && method_exists($job, 'onQueue')) {
            $job->onQueue($queue);
        }

        if ($delay) {
            dispatch($job)->delay($delay);
        } else {
            dispatch($job);
        }

        return [
            'job_dispatched' => true,
            'job_class' => $jobClass,
            'queue' => $queue,
            'delay' => $delay,
        ];
    }

    /**
     * Determine which method to call on the action class.
     *
     * Tries methods in this order:
     * 1. Explicitly specified method
     * 2. __invoke() (invokable)
     * 3. handle() (job-like, Spatie Actions)
     * 4. execute() (backward compatibility)
     * 5. asAction() (Spatie Laravel Actions)
     *
     * @param  object  $action  The action instance
     * @param  string|null  $explicitMethod  Explicitly specified method
     * @return string|null Method name to call, or null if none found
     */
    protected function determineMethod(object $action, ?string $explicitMethod): ?string
    {
        // 1. If method explicitly specified, use it
        if ($explicitMethod && method_exists($action, $explicitMethod)) {
            return $explicitMethod;
        }

        // 2. Try __invoke (invokable classes)
        if (method_exists($action, '__invoke')) {
            return '__invoke';
        }

        // 3. Try handle (Laravel Jobs, Spatie Actions)
        if (method_exists($action, 'handle')) {
            return 'handle';
        }

        // 4. Try execute (backward compatibility)
        if (method_exists($action, 'execute')) {
            return 'execute';
        }

        // 5. Try asAction (Spatie Laravel Actions)
        if (method_exists($action, 'asAction')) {
            return 'asAction';
        }

        return null;
    }

    /**
     * Get list of methods tried for error message.
     *
     * @param  string|null  $explicitMethod  Explicitly specified method
     * @return string Comma-separated list of methods
     */
    protected function getTriedMethods(?string $explicitMethod): string
    {
        $methods = $explicitMethod ? [$explicitMethod] : [];
        $methods = array_merge($methods, ['__invoke', 'handle', 'execute', 'asAction']);

        return implode(', ', $methods);
    }

    /**
     * Substitute context variables in parameters.
     *
     * @param  mixed  $value  Value to process
     * @param  array<string, mixed>  $context  Execution context
     * @return mixed Processed value
     */
    protected function substituteVariables($value, array $context)
    {
        if (is_string($value) && preg_match('/^\{\{(.+)\}\}$/', $value, $matches)) {
            return data_get($context, trim($matches[1]));
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->substituteVariables($v, $context), $value);
        }

        return $value;
    }
}
