# Approval Engine Design for ForgePulse (Revised)

**Version:** 2.0  
**Date:** 2025-12-25  
**Status:** Design Proposal (Revised)

---

## Executive Summary

This revised design integrates approval functionality directly into ForgePulse's existing workflow infrastructure by adding new step types and minimal approval-specific data models. Rather than creating a parallel approval system, we leverage the existing `WorkflowStep`, `WorkflowExecution`, and `WorkflowEngine` components, making approvals just another workflow pattern.

---

## 1. Design Philosophy

### 1.1 Core Principle

**Approvals are workflows, not a separate system.**

Instead of creating separate `ApprovalRequest`, `ApprovalStep`, and `ApprovalEngine` classes, we:

1. Add new approval-related `StepType` enums
2. Create minimal new models only for approval-specific data (approver responses)
3. Use existing workflow execution and logging infrastructure
4. Leverage existing conditional logic for routing

### 1.2 Benefits

- **Simpler**: Fewer models, services, and concepts to learn
- **Consistent**: Same UI, API, and execution patterns
- **Maintainable**: Single workflow engine handles all execution
- **Flexible**: Mix approval steps with any other step types
- **Powerful**: Full access to branching, conditions, and parallel execution

---

## 2. Architecture Overview

```
┌─────────────────────────────────────────────────────────────┐
│                  ForgePulse Core (Existing)                 │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Workflow    │  │WorkflowStep  │  │WorkflowEngine│     │
│  │              │  │ + NEW TYPES  │  │              │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            ▲
                            │ Minimal Extension
                            │
┌─────────────────────────────────────────────────────────────┐
│               Approval-Specific Components                  │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ApprovalResponse│ DelegationSvc│EscalationSvc │     │
│  │  (New Model)  │  │  (Service)   │  │  (Service)   │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│                                                             │
│  ┌──────────────────────────────────────────────────────┐  │
│  │        New Step Handlers (Approval types)            │  │
│  └──────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
```

---

## 3. New Step Types

### 3.1 Extended StepType Enum

Add new cases to the existing `StepType` enum:

```php
enum StepType: string
{
    // Existing types
    case ACTION = 'action';
    case CONDITION = 'condition';
    case DELAY = 'delay';
    case NOTIFICATION = 'notification';
    case WEBHOOK = 'webhook';
    case EVENT = 'event';
    case JOB = 'job';
    
    // New approval types
    case APPROVAL = 'approval';                    // Single approval required
    case APPROVAL_SEQUENTIAL = 'approval_sequential'; // Multiple approvals in sequence
    case APPROVAL_PARALLEL = 'approval_parallel';     // Multiple approvals simultaneously
    case APPROVAL_CONSENSUS = 'approval_consensus';   // Majority/threshold-based approval
    
    public function handlerClass(): string
    {
        return match ($this) {
            // ... existing handlers ...
            self::APPROVAL => \AlizHarb\ForgePulse\Services\StepHandlers\ApprovalHandler::class,
            self::APPROVAL_SEQUENTIAL => \AlizHarb\ForgePulse\Services\StepHandlers\ApprovalSequentialHandler::class,
            self::APPROVAL_PARALLEL => \AlizHarb\ForgePulse\Services\StepHandlers\ApprovalParallelHandler::class,
            self::APPROVAL_CONSENSUS => \AlizHarb\ForgePulse\Services\StepHandlers\ApprovalConsensusHandler::class,
        };
    }
}
```

---

## 4. Minimal New Models

### 4.1 ApprovalResponse Model

The only new primary model - stores individual approver responses.

```php
class ApprovalResponse extends Model
{
    // Fields
    - id: int
    - workflow_execution_id: int       // Links to WorkflowExecution
    - workflow_step_id: int            // Links to WorkflowStep (the approval step)
    - workflow_execution_log_id: int   // Links to WorkflowExecutionLog
    - approver_user_id: int            // The assigned approver
    - actual_approver_user_id: int     // If delegated, who actually responded
    - status: ApprovalResponseStatus   // pending, approved, rejected, delegated, escalated
    - decision: string (nullable)      // 'approved' or 'rejected'
    - decision_notes: text (nullable)
    - is_delegated: boolean
    - delegated_from_user_id: int (nullable)
    - is_escalated: boolean
    - escalated_from_user_id: int (nullable)
    - assigned_at: datetime
    - notified_at: datetime (nullable)
    - reminded_at: datetime (nullable)
    - responded_at: datetime (nullable)
    - due_at: datetime (nullable)
    - ip_address: string (nullable)
    - user_agent: string (nullable)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - workflowExecution(): BelongsTo<WorkflowExecution>
    - workflowStep(): BelongsTo<WorkflowStep>
    - executionLog(): BelongsTo<WorkflowExecutionLog>
    - approver(): BelongsTo<User>
    - actualApprover(): BelongsTo<User>
}
```

### 4.2 ApproverDelegation Model (Optional)

For managing temporary delegation (e.g., vacation coverage).

```php
class ApproverDelegation extends Model
{
    - id: int
    - from_user_id: int
    - to_user_id: int
    - start_date: datetime
    - end_date: datetime (nullable)
    - reason: text (nullable)
    - scope_rules: json (nullable) // Limit by request type, amount, etc.
    - is_active: boolean
    - created_at: datetime
    - updated_at: datetime
}
```

### 4.3 Use Existing Models

- **WorkflowStep**: Configure approval requirements
- **WorkflowExecution**: Track overall approval request status
- **WorkflowExecutionLog**: Log approval step progress
- **WorkflowStep->conditions**: Route to correct approvers

---

## 5. Enums

### 5.1 ApprovalResponseStatus

```php
enum ApprovalResponseStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case DELEGATED = 'delegated';
    case ESCALATED = 'escalated';
    case EXPIRED = 'expired';
}
```

### 5.2 ApprovalMode

```php
enum ApprovalMode: string
{
    case ANY = 'any';           // Any one approver can approve
    case ALL = 'all';           // All approvers must approve
    case MAJORITY = 'majority'; // >50% must approve
    case THRESHOLD = 'threshold'; // Configurable threshold (e.g., 75%)
}
```

---

## 6. Step Configuration Structure

### 6.1 Simple Approval Step

```json
{
  "type": "approval",
  "name": "Manager Approval",
  "configuration": {
    "title": "Purchase Order Approval - {{po_number}}",
    "description": "Please review and approve this purchase request",
    "approvers": [
      {"user_id": 123},
      {"role": "manager"},
      {"email": "manager@company.com"}
    ],
    "approval_mode": "any",
    "require_rejection_reason": true,
    "allow_delegation": true,
    "escalation_minutes": 1440,
    "escalation_target": {"role": "senior_manager"},
    "reminder_minutes": 720
  }
}
```

### 6.2 Parallel Approval (All Must Approve)

```json
{
  "type": "approval_parallel",
  "name": "Cross-Functional Review",
  "configuration": {
    "title": "Contract Review - {{contract_number}}",
    "approvers": [
      {"role": "legal_counsel"},
      {"role": "finance_director"},
      {"role": "operations_manager"}
    ],
    "approval_mode": "all",
    "escalation_minutes": 2880
  }
}
```

### 6.3 Sequential Approval Chain

```json
{
  "type": "approval_sequential",
  "name": "Budget Approval Chain",
  "configuration": {
    "title": "Budget Request - {{department}}",
    "approval_chain": [
      {
        "name": "Department Manager",
        "approvers": [{"role": "dept_manager"}]
      },
      {
        "name": "Finance Director",
        "approvers": [{"role": "finance_director"}]
      },
      {
        "name": "CFO",
        "approvers": [{"role": "cfo"}],
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "amount", "operator": ">", "value": 50000}
          ]
        }
      }
    ]
  }
}
```

### 6.4 Consensus Approval (Majority Vote)

```json
{
  "type": "approval_consensus",
  "name": "Executive Committee Vote",
  "configuration": {
    "title": "Strategic Initiative Approval",
    "approvers": [
      {"role": "executive"},
      {"role": "ceo"},
      {"role": "cfo"},
      {"role": "cto"},
      {"role": "coo"}
    ],
    "approval_mode": "majority",
    "threshold_percentage": 60,
    "allow_abstain": true
  }
}
```

### 6.5 Conditional Routing (Amount-Based)

Use existing ForgePulse branching:

```json
{
  "type": "condition",
  "name": "Route by Amount",
  "configuration": {
    "field": "amount",
    "operator": "<=",
    "value": 10000
  },
  "children": [
    {
      "type": "approval",
      "name": "Manager Approval (≤$10k)",
      "configuration": {
        "approvers": [{"role": "manager"}]
      }
    }
  ]
}
```

Alternative parent step:

```json
{
  "type": "condition",
  "name": "Route by Amount",
  "configuration": {
    "field": "amount",
    "operator": ">",
    "value": 10000
  },
  "children": [
    {
      "type": "approval_sequential",
      "name": "Senior Approval (>$10k)",
      "configuration": {
        "approval_chain": [
          {"name": "Director", "approvers": [{"role": "director"}]},
          {"name": "CFO", "approvers": [{"role": "cfo"}]}
        ]
      }
    }
  ]
}
```

---

## 7. Step Handlers

### 7.1 ApprovalHandler (Single Approval)

```php
class ApprovalHandler
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $config = $step->configuration->getArrayCopy();
        
        // Create approval responses for each approver
        $approvers = $this->resolveApprovers($config['approvers'], $context);
        
        foreach ($approvers as $approver) {
            ApprovalResponse::create([
                'workflow_execution_id' => $this->getCurrentExecution($step)->id,
                'workflow_step_id' => $step->id,
                'workflow_execution_log_id' => $this->getCurrentLog($step)->id,
                'approver_user_id' => $approver->id,
                'actual_approver_user_id' => $approver->id,
                'status' => ApprovalResponseStatus::PENDING,
                'assigned_at' => now(),
                'due_at' => isset($config['escalation_minutes']) 
                    ? now()->addMinutes($config['escalation_minutes'])
                    : null,
            ]);
        }
        
        // Send notifications
        $this->notifyApprovers($approvers, $step, $context);
        
        // Schedule escalation if configured
        if (isset($config['escalation_minutes'])) {
            $this->scheduleEscalation($step, $config['escalation_minutes']);
        }
        
        // Pause workflow execution until approval received
        $this->pauseExecution($step, 'Waiting for approval');
        
        return [
            'approval_status' => 'pending',
            'approval_step_id' => $step->id,
            'approvers_count' => count($approvers),
        ];
    }
    
    protected function resolveApprovers(array $approverConfig, array $context): Collection
    {
        $approvers = collect();
        
        foreach ($approverConfig as $config) {
            if (isset($config['user_id'])) {
                $approvers->push(User::find($config['user_id']));
            } elseif (isset($config['role'])) {
                $approvers = $approvers->merge(
                    $this->getUsersByRole($config['role'], $context)
                );
            } elseif (isset($config['email'])) {
                $approvers->push(User::where('email', $config['email'])->first());
            } elseif (isset($config['custom_resolver'])) {
                $approvers = $approvers->merge(
                    app($config['custom_resolver'])->resolve($context)
                );
            }
        }
        
        return $approvers->filter()->unique('id');
    }
}
```

### 7.2 ApprovalParallelHandler

```php
class ApprovalParallelHandler extends ApprovalHandler
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $result = parent::handle($step, $context);
        
        $config = $step->configuration->getArrayCopy();
        $approvalMode = ApprovalMode::from($config['approval_mode'] ?? 'all');
        
        $result['approval_mode'] = $approvalMode->value;
        $result['required_approvals'] = $this->calculateRequiredApprovals(
            $result['approvers_count'],
            $approvalMode,
            $config['threshold_percentage'] ?? null
        );
        
        return $result;
    }
    
    protected function calculateRequiredApprovals(
        int $totalApprovers,
        ApprovalMode $mode,
        ?int $thresholdPercentage
    ): int {
        return match ($mode) {
            ApprovalMode::ANY => 1,
            ApprovalMode::ALL => $totalApprovers,
            ApprovalMode::MAJORITY => (int) ceil($totalApprovers / 2),
            ApprovalMode::THRESHOLD => (int) ceil($totalApprovers * ($thresholdPercentage / 100)),
        };
    }
}
```

### 7.3 ApprovalSequentialHandler

```php
class ApprovalSequentialHandler extends ApprovalHandler
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $config = $step->configuration->getArrayCopy();
        $approvalChain = $config['approval_chain'];
        
        // Start with first step in chain
        $currentStep = $approvalChain[0];
        
        // Check if conditions are met for this step
        if (isset($currentStep['conditions'])) {
            $evaluator = app(ConditionalEvaluator::class);
            if (!$evaluator->evaluate($currentStep['conditions'], $context)) {
                // Skip to next step
                return $this->advanceToNextInChain($step, $context, 1);
            }
        }
        
        // Create approval response for current step
        $approvers = $this->resolveApprovers($currentStep['approvers'], $context);
        
        foreach ($approvers as $approver) {
            ApprovalResponse::create([
                'workflow_execution_id' => $this->getCurrentExecution($step)->id,
                'workflow_step_id' => $step->id,
                'workflow_execution_log_id' => $this->getCurrentLog($step)->id,
                'approver_user_id' => $approver->id,
                'actual_approver_user_id' => $approver->id,
                'status' => ApprovalResponseStatus::PENDING,
                'assigned_at' => now(),
            ]);
        }
        
        $this->notifyApprovers($approvers, $step, $context);
        $this->pauseExecution($step, "Waiting for approval: {$currentStep['name']}");
        
        return [
            'approval_status' => 'pending',
            'current_chain_step' => 0,
            'chain_step_name' => $currentStep['name'],
            'total_chain_steps' => count($approvalChain),
        ];
    }
}
```

### 7.4 ApprovalConsensusHandler

```php
class ApprovalConsensusHandler extends ApprovalParallelHandler
{
    // Same as ApprovalParallelHandler but allows for abstentions
    // and calculates thresholds based on actual votes, not total approvers
}
```

---

## 8. Approval Response Processing

### 8.1 ApprovalResponseService

```php
class ApprovalResponseService
{
    /**
     * Process an approval decision
     */
    public function processApproval(
        ApprovalResponse $response,
        User $user,
        string $decision,
        ?string $notes = null
    ): void {
        // Update response
        $response->update([
            'status' => $decision === 'approved' 
                ? ApprovalResponseStatus::APPROVED 
                : ApprovalResponseStatus::REJECTED,
            'decision' => $decision,
            'decision_notes' => $notes,
            'responded_at' => now(),
            'actual_approver_user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Check if step is complete
        if ($this->isStepComplete($response->workflowStep)) {
            $this->completeApprovalStep($response->workflowStep);
        }
    }
    
    /**
     * Check if approval step is complete
     */
    protected function isStepComplete(WorkflowStep $step): bool
    {
        $config = $step->configuration->getArrayCopy();
        $mode = ApprovalMode::from($config['approval_mode'] ?? 'all');
        
        $responses = ApprovalResponse::where('workflow_step_id', $step->id)
            ->get();
        
        $approvedCount = $responses->where('decision', 'approved')->count();
        $rejectedCount = $responses->where('decision', 'rejected')->count();
        $totalCount = $responses->count();
        
        // Check for rejection (typically one rejection fails the step)
        if ($rejectedCount > 0 && !($config['allow_any_rejection'] ?? false)) {
            return true; // Step complete (rejected)
        }
        
        // Check if enough approvals received
        return match ($mode) {
            ApprovalMode::ANY => $approvedCount >= 1,
            ApprovalMode::ALL => $approvedCount === $totalCount,
            ApprovalMode::MAJORITY => $approvedCount > ($totalCount / 2),
            ApprovalMode::THRESHOLD => $approvedCount >= $this->calculateThreshold(
                $totalCount,
                $config['threshold_percentage'] ?? 50
            ),
        };
    }
    
    /**
     * Complete approval step and resume workflow
     */
    protected function completeApprovalStep(WorkflowStep $step): void
    {
        $execution = $step->workflow->executions()->latest()->first();
        
        // Determine if approved or rejected
        $responses = ApprovalResponse::where('workflow_step_id', $step->id)->get();
        $rejectedCount = $responses->where('decision', 'rejected')->count();
        
        $isApproved = $rejectedCount === 0;
        
        // Update execution log
        $log = WorkflowExecutionLog::where('workflow_step_id', $step->id)
            ->where('workflow_execution_id', $execution->id)
            ->latest()
            ->first();
        
        if ($log) {
            $log->update([
                'status' => $isApproved ? LogStatus::COMPLETED : LogStatus::FAILED,
                'output' => [
                    'approval_decision' => $isApproved ? 'approved' : 'rejected',
                    'approved_count' => $responses->where('decision', 'approved')->count(),
                    'rejected_count' => $rejectedCount,
                ],
            ]);
        }
        
        // Resume workflow execution
        if ($execution->isPaused()) {
            $execution->resume();
            
            // Continue execution
            dispatch(new \AlizHarb\ForgePulse\Jobs\ExecuteWorkflowJob($execution));
        }
        
        // Fire event
        event(new ApprovalStepCompleted($step, $execution, $isApproved));
    }
}
```

---

## 9. Delegation Service

```php
class ApprovalDelegationService
{
    /**
     * Delegate an approval to another user
     */
    public function delegate(
        ApprovalResponse $response,
        User $delegateTo,
        ?string $reason = null
    ): ApprovalResponse {
        // Update original response
        $response->update([
            'status' => ApprovalResponseStatus::DELEGATED,
            'is_delegated' => true,
        ]);
        
        // Create new response for delegate
        $newResponse = ApprovalResponse::create([
            'workflow_execution_id' => $response->workflow_execution_id,
            'workflow_step_id' => $response->workflow_step_id,
            'workflow_execution_log_id' => $response->workflow_execution_log_id,
            'approver_user_id' => $delegateTo->id,
            'actual_approver_user_id' => $delegateTo->id,
            'status' => ApprovalResponseStatus::PENDING,
            'is_delegated' => true,
            'delegated_from_user_id' => $response->approver_user_id,
            'assigned_at' => now(),
        ]);
        
        // Notify delegate
        $delegateTo->notify(new ApprovalDelegatedNotification($newResponse, $reason));
        
        return $newResponse;
    }
    
    /**
     * Check for active delegation
     */
    public function getActiveDelegate(User $user): ?User
    {
        $delegation = ApproverDelegation::where('from_user_id', $user->id)
            ->where('is_active', true)
            ->where('start_date', '<=', now())
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            })
            ->first();
        
        return $delegation?->toUser;
    }
}
```

---

## 10. Escalation Service

```php
class ApprovalEscalationService
{
    /**
     * Process pending escalations (run via scheduled job)
     */
    public function processEscalations(): void
    {
        $overdueResponses = ApprovalResponse::where('status', ApprovalResponseStatus::PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<=', now())
            ->get();
        
        foreach ($overdueResponses as $response) {
            $this->escalate($response);
        }
    }
    
    /**
     * Escalate a specific approval response
     */
    protected function escalate(ApprovalResponse $response): void
    {
        $step = $response->workflowStep;
        $config = $step->configuration->getArrayCopy();
        
        if (!isset($config['escalation_target'])) {
            return; // No escalation configured
        }
        
        // Resolve escalation target
        $escalationTarget = $this->resolveEscalationTarget(
            $config['escalation_target'],
            $response
        );
        
        if (!$escalationTarget) {
            return;
        }
        
        // Update original response
        $response->update([
            'status' => ApprovalResponseStatus::ESCALATED,
            'is_escalated' => true,
        ]);
        
        // Create new response for escalation target
        $newResponse = ApprovalResponse::create([
            'workflow_execution_id' => $response->workflow_execution_id,
            'workflow_step_id' => $response->workflow_step_id,
            'workflow_execution_log_id' => $response->workflow_execution_log_id,
            'approver_user_id' => $escalationTarget->id,
            'actual_approver_user_id' => $escalationTarget->id,
            'status' => ApprovalResponseStatus::PENDING,
            'is_escalated' => true,
            'escalated_from_user_id' => $response->approver_user_id,
            'assigned_at' => now(),
        ]);
        
        // Notify escalation target
        $escalationTarget->notify(
            new ApprovalEscalatedNotification($newResponse, $response)
        );
    }
    
    protected function resolveEscalationTarget(
        array $targetConfig,
        ApprovalResponse $response
    ): ?User {
        if (isset($targetConfig['user_id'])) {
            return User::find($targetConfig['user_id']);
        }
        
        if (isset($targetConfig['role'])) {
            // Get first user with role, excluding original approver
            return User::role($targetConfig['role'])
                ->where('id', '!=', $response->approver_user_id)
                ->first();
        }
        
        return null;
    }
}
```

---

## 11. API Endpoints

### 11.1 Approval Response Endpoints

```php
// In routes/api.php or approval-specific route file

// List pending approvals for authenticated user
GET /api/forgepulse/approvals/pending

// Get specific approval response details
GET /api/forgepulse/approvals/{approvalResponse}

// Approve
POST /api/forgepulse/approvals/{approvalResponse}/approve
{
  "notes": "Approved - looks good"
}

// Reject
POST /api/forgepulse/approvals/{approvalResponse}/reject
{
  "notes": "Budget insufficient",
  "reason": "Rejected due to budget constraints"
}

// Delegate
POST /api/forgepulse/approvals/{approvalResponse}/delegate
{
  "delegate_to_user_id": 456,
  "reason": "On vacation"
}

// Get approval history for execution
GET /api/forgepulse/executions/{execution}/approvals
```

### 11.2 Delegation Management Endpoints

```php
// Create delegation
POST /api/forgepulse/delegations
{
  "to_user_id": 456,
  "start_date": "2025-12-26",
  "end_date": "2026-01-10",
  "reason": "Vacation",
  "scope_rules": {
    "max_amount": 25000
  }
}

// List my delegations
GET /api/forgepulse/delegations

// Revoke delegation
DELETE /api/forgepulse/delegations/{delegation}
```

---

## 12. Events

### 12.1 New Approval Events

```php
// Approval-specific events
- ApprovalStepStarted
- ApprovalResponseReceived
- ApprovalStepCompleted
- ApprovalStepRejected
- ApprovalDelegated
- ApprovalEscalated
- ApprovalReminder
```

---

## 13. Notifications

### 13.1 Notification Classes

```php
- ApprovalRequestNotification    // Sent to approvers when assigned
- ApprovalDecisionNotification   // Sent to requester on decision
- ApprovalReminderNotification   // Reminder to pending approvers
- ApprovalDelegatedNotification  // Sent to delegate
- ApprovalEscalatedNotification  // Sent to escalation target
```

---

## 14. UI Components (Livewire)

### 14.1 Approval Inbox Component

```php
<livewire:forgepulse::approval-inbox />
```

Shows pending approvals for the authenticated user with filters and search.

### 14.2 Approval Timeline Component

```php
<livewire:forgepulse::approval-timeline :execution="$execution" />
```

Shows the progress of approval steps within a workflow execution.

### 14.3 Visual Workflow Builder Enhancement

Update existing `workflow-builder` component to support approval step types with:
- Approver selection UI
- Approval mode configuration
- Escalation settings
- Delegation options

---

## 15. Configuration

### 15.1 Add to config/forgepulse.php

```php
'approvals' => [
    'enabled' => true,
    
    'defaults' => [
        'allow_delegation' => true,
        'allow_comments' => true,
        'require_rejection_reason' => true,
        'escalation_enabled' => true,
        'reminder_enabled' => true,
        'reminder_minutes' => 720,  // 12 hours
    ],
    
    'notifications' => [
        'channels' => ['mail', 'database'],
        'send_reminders' => true,
        'reminder_frequency_minutes' => 720,
    ],
    
    'escalation' => [
        'enabled' => true,
        'check_frequency_minutes' => 60, // How often to check for escalations
    ],
    
    'audit' => [
        'log_ip_address' => true,
        'log_user_agent' => true,
        'retention_days' => 2555, // 7 years
    ],
],
```

---

## 16. Database Migrations

### 16.1 Required Migrations

```php
// 1. create_approval_responses_table.php
Schema::create('approval_responses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_execution_id')->constrained()->cascadeOnDelete();
    $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
    $table->foreignId('workflow_execution_log_id')->constrained()->cascadeOnDelete();
    $table->foreignId('approver_user_id')->constrained('users');
    $table->foreignId('actual_approver_user_id')->constrained('users');
    $table->string('status'); // pending, approved, rejected, delegated, escalated
    $table->string('decision')->nullable(); // approved/rejected
    $table->text('decision_notes')->nullable();
    $table->boolean('is_delegated')->default(false);
    $table->foreignId('delegated_from_user_id')->nullable()->constrained('users');
    $table->boolean('is_escalated')->default(false);
    $table->foreignId('escalated_from_user_id')->nullable()->constrained('users');
    $table->timestamp('assigned_at');
    $table->timestamp('notified_at')->nullable();
    $table->timestamp('reminded_at')->nullable();
    $table->timestamp('responded_at')->nullable();
    $table->timestamp('due_at')->nullable();
    $table->string('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->timestamps();
    
    $table->index(['approver_user_id', 'status']);
    $table->index(['workflow_execution_id', 'status']);
    $table->index(['due_at', 'status']);
});

// 2. create_approver_delegations_table.php
Schema::create('approver_delegations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('from_user_id')->constrained('users');
    $table->foreignId('to_user_id')->constrained('users');
    $table->timestamp('start_date');
    $table->timestamp('end_date')->nullable();
    $table->text('reason')->nullable();
    $table->json('scope_rules')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    
    $table->index(['from_user_id', 'is_active', 'start_date', 'end_date']);
});
```

---

## 17. Complete Example: Purchase Order Approval Workflow

### 17.1 Workflow Definition

```php
use AlizHarb\ForgePulse\Models\Workflow;
use AlizHarb\ForgePulse\Enums\StepType;
use AlizHarb\ForgePulse\Enums\WorkflowStatus;

$workflow = Workflow::create([
    'name' => 'Purchase Order Approval',
    'description' => 'Multi-level approval workflow for purchase orders',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Notification to requester
$workflow->steps()->create([
    'name' => 'Send Confirmation Email',
    'type' => StepType::NOTIFICATION,
    'position' => 1,
    'configuration' => [
        'notification_class' => \App\Notifications\POSubmittedNotification::class,
        'recipients' => ['{{requester_id}}'],
    ],
]);

// Step 2: Conditional routing based on amount
$amountCheck = $workflow->steps()->create([
    'name' => 'Check Amount',
    'type' => StepType::CONDITION,
    'position' => 2,
    'configuration' => [
        'field' => 'amount',
        'operator' => '<=',
        'value' => 10000,
    ],
]);

// Step 2a: Manager approval for ≤$10k
$workflow->steps()->create([
    'name' => 'Manager Approval',
    'type' => StepType::APPROVAL,
    'position' => 3,
    'parent_step_id' => $amountCheck->id,
    'configuration' => [
        'title' => 'Purchase Order {{po_number}} - Manager Approval',
        'description' => 'Please review this purchase request for {{amount}} {{currency}}',
        'approvers' => [
            ['role' => 'manager'],
        ],
        'approval_mode' => 'any',
        'escalation_minutes' => 1440,
        'escalation_target' => ['role' => 'senior_manager'],
        'allow_delegation' => true,
    ],
]);

// Step 2b: Sequential approval for >$10k
$highValueCheck = $workflow->steps()->create([
    'name' => 'High Value Check',
    'type' => StepType::CONDITION,
    'position' => 3,
    'parent_step_id' => $amountCheck->id,
    'configuration' => [
        'field' => 'amount',
        'operator' => '>',
        'value' => 10000,
    ],
]);

$workflow->steps()->create([
    'name' => 'Senior Management Approval',
    'type' => StepType::APPROVAL_SEQUENTIAL,
    'position' => 4,
    'parent_step_id' => $highValueCheck->id,
    'configuration' => [
        'title' => 'Purchase Order {{po_number}} - Senior Approval Required',
        'approval_chain' => [
            [
                'name' => 'Department Director',
                'approvers' => [['role' => 'director']],
            ],
            [
                'name' => 'Finance Director',
                'approvers' => [['role' => 'finance_director']],
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 25000],
                    ],
                ],
            ],
            [
                'name' => 'CFO Approval',
                'approvers' => [['role' => 'cfo']],
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 50000],
                    ],
                ],
            ],
        ],
        'escalation_minutes' => 2880,
    ],
]);

// Step 3: Process purchase order
$workflow->steps()->create([
    'name' => 'Process Purchase Order',
    'type' => StepType::ACTION,
    'position' => 5,
    'configuration' => [
        'action_class' => \App\Actions\ProcessPurchaseOrder::class,
        'parameters' => [
            'po_number' => '{{po_number}}',
            'amount' => '{{amount}}',
            'items' => '{{items}}',
        ],
    ],
]);

// Step 4: Send completion notification
$workflow->steps()->create([
    'name' => 'Send Approval Notification',
    'type' => StepType::NOTIFICATION,
    'position' => 6,
    'configuration' => [
        'notification_class' => \App\Notifications\POApprovedNotification::class,
        'recipients' => ['{{requester_id}}'],
    ],
]);
```

### 17.2 Execute Workflow

```php
$execution = $workflow->execute([
    'requester_id' => auth()->id(),
    'po_number' => 'PO-2025-001',
    'amount' => 15000,
    'currency' => 'USD',
    'department' => 'IT',
    'items' => [
        ['name' => 'Laptop', 'quantity' => 10, 'unit_price' => 1500],
    ],
]);
```

### 17.3 Approve via API

```php
// Approver receives notification, clicks link to approve

POST /api/forgepulse/approvals/123/approve
Authorization: Bearer {token}
Content-Type: application/json

{
  "notes": "Approved. Within budget for Q4."
}
```

---

## 18. Comparison: Original vs. Revised Design

| Aspect | Original Design | Revised Design |
|--------|----------------|----------------|
| **Core Models** | ApprovalRequest, ApprovalStep, Approver (3 new models) | ApprovalResponse, ApproverDelegation (2 new models) |
| **Services** | ApprovalEngine, ApprovalRoutingService, ApprovalPolicyService (6+ services) | ApprovalResponseService, ApprovalDelegationService, ApprovalEscalationService (3 services) |
| **Step Types** | Separate approval system | Extends existing StepType enum |
| **Execution** | Separate approval execution logic | Uses existing WorkflowEngine |
| **Branching** | Custom approval routing | Uses existing conditional steps |
| **UI** | Separate approval builder | Extends existing workflow-builder |
| **API** | Separate approval API | Extends existing workflow API |
| **Complexity** | High - parallel system | Low - extends existing patterns |
| **Maintenance** | Complex - two systems | Simple - one system |
| **Learning Curve** | Steep - new concepts | Shallow - familiar patterns |

---

## 19. Implementation Phases (Revised)

### Phase 1: Core Approval Steps (1-2 weeks)
- Add new StepType enum cases
- Create ApprovalResponse model and migration
- Implement ApprovalHandler (single approval)
- Implement ApprovalResponseService
- Basic notifications

### Phase 2: Advanced Approval Patterns (1-2 weeks)
- Implement ApprovalParallelHandler
- Implement ApprovalSequentialHandler
- Implement ApprovalConsensusHandler
- Approval modes (any, all, majority, threshold)

### Phase 3: Delegation & Escalation (1 week)
- Create ApproverDelegation model
- Implement ApprovalDelegationService
- Implement ApprovalEscalationService
- Scheduled escalation checks

### Phase 4: API & Events (1 week)
- API endpoints for approval actions
- Approval-specific events
- Enhanced notifications

### Phase 5: UI Components (1-2 weeks)
- Approval inbox Livewire component
- Approval timeline component
- Enhance workflow-builder for approval steps
- Mobile-friendly approval interface

### Phase 6: Testing & Documentation (1 week)
- Comprehensive test suite
- API documentation
- User guides
- Example workflows

**Total: 6-9 weeks** (vs. 9-13 weeks in original design)

---

## 20. Benefits of Revised Approach

### 20.1 Simplicity
- **Fewer Models**: 2 new models vs. 10+ in original design
- **Fewer Services**: 3 services vs. 6+ in original design
- **Single Execution Engine**: Reuses existing WorkflowEngine
- **Familiar Patterns**: Uses existing step types and handlers

### 20.2 Flexibility
- **Mix and Match**: Combine approval steps with any other step types
- **Native Branching**: Use existing conditional steps for routing
- **Parallel Execution**: Leverage existing parallel execution support
- **Versioning**: Automatic versioning via existing workflow versioning

### 20.3 Maintainability
- **Single Codebase**: No parallel systems to maintain
- **Consistent API**: Same API patterns for all step types
- **Easier Testing**: Test approval steps like any other step
- **Lower Complexity**: Simpler mental model

### 20.4 Power
- **Full Workflow Features**: Access to all ForgePulse features (delays, webhooks, conditions, etc.)
- **Complex Chains**: Build arbitrarily complex approval chains
- **Dynamic Routing**: Use full conditional logic for routing
- **Event Integration**: Use existing event system

---

## 21. Migration Strategy from Original Design

If the original design was already implemented, migration would involve:

1. Map `ApprovalRequest` → `WorkflowExecution`
2. Map `ApprovalStep` → `WorkflowStep` with approval type
3. Map `Approver` → `ApprovalResponse`
4. Map `ApprovalAction` → `WorkflowExecutionLog`
5. Retire `ApprovalEngine` in favor of `WorkflowEngine`
6. Consolidate services

However, starting with this revised design avoids this complexity entirely.

---

## 22. Conclusion

This revised design achieves the same approval functionality as the original design but with significantly less complexity by:

1. **Extending, not replacing**: Add approval step types instead of creating a parallel system
2. **Reusing infrastructure**: Leverage existing execution engine, logging, and events
3. **Minimal new code**: Only 2 new models and 3 services vs. 10+ models and 6+ services
4. **Consistent patterns**: Approvals work like any other workflow step

The result is a more maintainable, flexible, and powerful approval system that feels native to ForgePulse rather than bolted on.

---

**End of Document**
