# Approval Engine Design for ForgePulse

**Version:** 1.0  
**Date:** 2025-12-25  
**Status:** Design Proposal

---

## Executive Summary

This document outlines the design for an Approval Engine to be integrated into ForgePulse. The Approval Engine enables multi-level approval workflows for business processes such as procurement requests, budget approvals, content publishing, and access requests. It builds upon ForgePulse's existing workflow infrastructure while adding specialised approval semantics, delegation, escalation, and audit capabilities.

---

## 1. Overview

### 1.1 Purpose

The Approval Engine extends ForgePulse to support structured approval processes where designated Approvers review and approve or reject requests submitted by Buyers or other users. This is essential for:

- Purchase order approvals
- Budget request validations
- Document and content approvals
- Access permission requests
- Multi-tier authorisation workflows

### 1.2 Key Requirements

1. **Multi-level Approvals**: Support sequential, parallel, and conditional approval chains
2. **Role-Based Routing**: Route approval requests based on user roles, departments, or custom rules
3. **Delegation**: Allow Approvers to delegate approval authority to other users
4. **Escalation**: Automatically escalate approvals when response time exceeds thresholds
5. **Audit Trail**: Maintain complete history of all approval actions
6. **Notifications**: Real-time notifications to Approvers and requesters
7. **Integration**: Seamless integration with existing ForgePulse workflow infrastructure

---

## 2. Architecture

### 2.1 Core Components

```
┌─────────────────────────────────────────────────────────────┐
│                    ForgePulse Core                          │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Workflow    │  │WorkflowStep  │  │WorkflowEngine│     │
│  │   Model      │  │    Model     │  │   Service    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
                            ▲
                            │ Extends
                            │
┌─────────────────────────────────────────────────────────────┐
│                   Approval Engine                           │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │  Approval    │  │ ApprovalStep │  │ Approver     │     │
│  │  Request     │  │   Model      │  │   Model      │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
│                                                             │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │
│  │ Approval     │  │  Delegation  │  │ Escalation   │     │
│  │  Engine      │  │   Service    │  │   Service    │     │
│  └──────────────┘  └──────────────┘  └──────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Data Model

#### 2.2.1 ApprovalRequest

Represents a request that requires approval. Links to a WorkflowExecution.

```php
class ApprovalRequest extends Model
{
    // Fields
    - id: int
    - workflow_execution_id: int (links to WorkflowExecution)
    - requester_id: int (user who initiated the request)
    - title: string
    - description: text
    - request_type: ApprovalRequestType (purchase_order, budget, access, document, custom)
    - status: ApprovalRequestStatus (pending, approved, rejected, cancelled, escalated)
    - priority: ApprovalPriority (low, normal, high, urgent)
    - request_data: json (contains request-specific data)
    - approval_policy_id: int (optional: links to ApprovalPolicy)
    - due_date: datetime (optional: deadline for approval)
    - total_amount: decimal (optional: for financial approvals)
    - currency: string (optional: for financial approvals)
    - submitted_at: datetime
    - completed_at: datetime (nullable)
    - final_decision: string (approved/rejected)
    - final_decision_at: datetime (nullable)
    - final_decision_by: int (nullable, user_id)
    - rejection_reason: text (nullable)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - workflowExecution(): BelongsTo<WorkflowExecution>
    - requester(): BelongsTo<User>
    - approvalSteps(): HasMany<ApprovalStep>
    - currentApprovalStep(): HasOne<ApprovalStep>
    - policy(): BelongsTo<ApprovalPolicy>
    - attachments(): HasMany<ApprovalAttachment>
    - comments(): HasMany<ApprovalComment>
    - history(): HasMany<ApprovalHistory>
}
```

#### 2.2.2 ApprovalStep

Represents a single approval step within an approval request. Each step may require approval from one or multiple Approvers.

```php
class ApprovalStep extends Model
{
    // Fields
    - id: int
    - approval_request_id: int
    - step_number: int (sequence order)
    - step_name: string
    - step_type: ApprovalStepType (sequential, parallel, conditional, consensus)
    - status: ApprovalStepStatus (pending, in_progress, approved, rejected, skipped, escalated)
    - required_approvals: int (how many approvals needed)
    - received_approvals: int (counter)
    - required_rejections: int (how many rejections to reject the step, default: 1)
    - approval_mode: ApprovalMode (any, all, majority, consensus)
    - conditions: json (nullable: conditional logic)
    - due_date: datetime (nullable)
    - escalation_minutes: int (nullable: minutes before escalation)
    - escalated_at: datetime (nullable)
    - escalation_target_id: int (nullable: user to escalate to)
    - started_at: datetime (nullable)
    - completed_at: datetime (nullable)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - approvalRequest(): BelongsTo<ApprovalRequest>
    - approvers(): HasMany<Approver>
    - actions(): HasMany<ApprovalAction>
}
```

#### 2.2.3 Approver

Represents an individual approver assignment to a specific approval step.

```php
class Approver extends Model
{
    // Fields
    - id: int
    - approval_step_id: int
    - user_id: int (original approver)
    - delegated_to_user_id: int (nullable: if delegated)
    - status: ApproverStatus (pending, approved, rejected, delegated, skipped, timed_out)
    - assigned_at: datetime
    - notified_at: datetime (nullable)
    - responded_at: datetime (nullable)
    - decision: string (nullable: approved/rejected)
    - decision_notes: text (nullable)
    - is_delegated: boolean (default: false)
    - delegation_reason: text (nullable)
    - is_escalated: boolean (default: false)
    - escalation_reason: text (nullable)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - approvalStep(): BelongsTo<ApprovalStep>
    - user(): BelongsTo<User>
    - delegatedToUser(): BelongsTo<User>
    - actions(): HasMany<ApprovalAction>
}
```

#### 2.2.4 ApprovalAction

Audit log for all approval-related actions.

```php
class ApprovalAction extends Model
{
    // Fields
    - id: int
    - approval_request_id: int
    - approval_step_id: int (nullable)
    - approver_id: int (nullable)
    - user_id: int (who performed the action)
    - action_type: ApprovalActionType (submit, approve, reject, delegate, escalate, comment, reassign, cancel)
    - action_details: json (additional metadata)
    - notes: text (nullable)
    - ip_address: string (nullable)
    - user_agent: string (nullable)
    - created_at: datetime
    
    // Relationships
    - approvalRequest(): BelongsTo<ApprovalRequest>
    - approvalStep(): BelongsTo<ApprovalStep>
    - approver(): BelongsTo<Approver>
    - user(): BelongsTo<User>
}
```

#### 2.2.5 ApprovalPolicy

Defines reusable approval policy templates.

```php
class ApprovalPolicy extends Model
{
    // Fields
    - id: int
    - name: string
    - description: text (nullable)
    - request_type: ApprovalRequestType
    - is_active: boolean (default: true)
    - policy_rules: json (approval chain configuration)
    - routing_rules: json (how to route requests)
    - escalation_rules: json (when and how to escalate)
    - threshold_rules: json (e.g., amount thresholds for different approval levels)
    - created_by: int (nullable)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - creator(): BelongsTo<User>
    - approvalRequests(): HasMany<ApprovalRequest>
}
```

#### 2.2.6 ApproverRole

Defines approver roles and their permissions.

```php
class ApproverRole extends Model
{
    // Fields
    - id: int
    - name: string
    - description: text (nullable)
    - approval_limit: decimal (nullable: max amount they can approve)
    - department: string (nullable)
    - can_delegate: boolean (default: true)
    - can_escalate: boolean (default: true)
    - auto_escalate_minutes: int (nullable: auto-escalate after X minutes)
    - permissions: json (specific permissions)
    - is_active: boolean (default: true)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - users(): BelongsToMany<User>
}
```

#### 2.2.7 ApproverDelegation

Tracks delegation assignments.

```php
class ApproverDelegation extends Model
{
    // Fields
    - id: int
    - from_user_id: int (delegator)
    - to_user_id: int (delegate)
    - start_date: datetime
    - end_date: datetime (nullable)
    - reason: text (nullable)
    - scope: json (nullable: limit delegation to specific types/amounts)
    - is_active: boolean (default: true)
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - fromUser(): BelongsTo<User>
    - toUser(): BelongsTo<User>
}
```

#### 2.2.8 Additional Support Models

```php
class ApprovalAttachment extends Model
{
    - id, approval_request_id, file_name, file_path, file_type, file_size, uploaded_by, created_at
}

class ApprovalComment extends Model
{
    - id, approval_request_id, user_id, comment, is_internal, created_at, updated_at
}

class ApprovalHistory extends Model
{
    - id, approval_request_id, event_type, event_data, user_id, created_at
}
```

---

## 3. Enums

### 3.1 ApprovalRequestStatus

```php
enum ApprovalRequestStatus: string
{
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case ESCALATED = 'escalated';
    case ON_HOLD = 'on_hold';
}
```

### 3.2 ApprovalStepStatus

```php
enum ApprovalStepStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case SKIPPED = 'skipped';
    case ESCALATED = 'escalated';
}
```

### 3.3 ApprovalStepType

```php
enum ApprovalStepType: string
{
    case SEQUENTIAL = 'sequential';  // One approver at a time
    case PARALLEL = 'parallel';      // All approvers simultaneously
    case CONDITIONAL = 'conditional'; // Based on conditions
    case CONSENSUS = 'consensus';     // Majority vote required
}
```

### 3.4 ApprovalMode

```php
enum ApprovalMode: string
{
    case ANY = 'any';           // Any one approver can approve
    case ALL = 'all';           // All approvers must approve
    case MAJORITY = 'majority'; // >50% must approve
    case CONSENSUS = 'consensus'; // Configurable threshold (e.g., 75%)
}
```

### 3.5 ApprovalActionType

```php
enum ApprovalActionType: string
{
    case SUBMIT = 'submit';
    case APPROVE = 'approve';
    case REJECT = 'reject';
    case DELEGATE = 'delegate';
    case ESCALATE = 'escalate';
    case COMMENT = 'comment';
    case REASSIGN = 'reassign';
    case CANCEL = 'cancel';
    case RECALL = 'recall';
}
```

### 3.6 ApprovalRequestType

```php
enum ApprovalRequestType: string
{
    case PURCHASE_ORDER = 'purchase_order';
    case BUDGET_REQUEST = 'budget_request';
    case ACCESS_REQUEST = 'access_request';
    case DOCUMENT_APPROVAL = 'document_approval';
    case EXPENSE_CLAIM = 'expense_claim';
    case CONTRACT_APPROVAL = 'contract_approval';
    case CUSTOM = 'custom';
}
```

### 3.7 ApprovalPriority

```php
enum ApprovalPriority: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';
    case URGENT = 'urgent';
}
```

---

## 4. Services

### 4.1 ApprovalEngine

Core service that orchestrates approval workflows.

```php
class ApprovalEngine
{
    /**
     * Initiate a new approval request
     */
    public function initiateApproval(
        WorkflowExecution $execution,
        array $requestData,
        ?ApprovalPolicy $policy = null
    ): ApprovalRequest;
    
    /**
     * Process an approval decision
     */
    public function processApproval(
        Approver $approver,
        string $decision,
        ?string $notes = null
    ): ApprovalRequest;
    
    /**
     * Advance to next approval step
     */
    public function advanceToNextStep(ApprovalRequest $request): void;
    
    /**
     * Complete approval request
     */
    public function completeApproval(ApprovalRequest $request): void;
    
    /**
     * Reject approval request
     */
    public function rejectApproval(
        ApprovalRequest $request,
        string $reason
    ): void;
    
    /**
     * Cancel approval request
     */
    public function cancelApproval(
        ApprovalRequest $request,
        string $reason
    ): void;
}
```

### 4.2 ApprovalRoutingService

Determines which Approvers should be assigned to each step.

```php
class ApprovalRoutingService
{
    /**
     * Route request to appropriate approvers based on policy
     */
    public function routeRequest(
        ApprovalRequest $request,
        ApprovalStep $step
    ): Collection<Approver>;
    
    /**
     * Determine approvers based on amount thresholds
     */
    public function getApproversByAmount(
        float $amount,
        ApprovalPolicy $policy
    ): Collection<User>;
    
    /**
     * Determine approvers based on department
     */
    public function getApproversByDepartment(
        string $department
    ): Collection<User>;
    
    /**
     * Determine approvers based on custom rules
     */
    public function getApproversByRules(
        array $rules,
        array $context
    ): Collection<User>;
}
```

### 4.3 ApprovalDelegationService

Manages delegation of approval authority.

```php
class ApprovalDelegationService
{
    /**
     * Delegate approval to another user
     */
    public function delegate(
        Approver $approver,
        User $delegateTo,
        ?string $reason = null
    ): Approver;
    
    /**
     * Check if user has active delegation
     */
    public function hasActiveDelegation(User $user): bool;
    
    /**
     * Get active delegate for user
     */
    public function getActiveDelegate(User $user): ?User;
    
    /**
     * Revoke delegation
     */
    public function revokeDelegation(ApproverDelegation $delegation): void;
    
    /**
     * Create temporary delegation (vacation, etc.)
     */
    public function createTemporaryDelegation(
        User $from,
        User $to,
        Carbon $startDate,
        Carbon $endDate,
        ?array $scope = null
    ): ApproverDelegation;
}
```

### 4.4 ApprovalEscalationService

Handles automatic escalation when approvals are delayed.

```php
class ApprovalEscalationService
{
    /**
     * Check and process pending escalations
     */
    public function processEscalations(): void;
    
    /**
     * Escalate a specific approval step
     */
    public function escalateStep(
        ApprovalStep $step,
        ?User $escalateTo = null
    ): void;
    
    /**
     * Get escalation target for an approver
     */
    public function getEscalationTarget(Approver $approver): ?User;
    
    /**
     * Schedule escalation check
     */
    public function scheduleEscalation(
        ApprovalStep $step,
        int $minutes
    ): void;
}
```

### 4.5 ApprovalNotificationService

Manages notifications for approval events.

```php
class ApprovalNotificationService
{
    /**
     * Notify approvers of pending approval
     */
    public function notifyApprovers(ApprovalStep $step): void;
    
    /**
     * Notify requester of approval decision
     */
    public function notifyRequester(ApprovalRequest $request): void;
    
    /**
     * Send escalation notification
     */
    public function notifyEscalation(
        ApprovalStep $step,
        User $escalatedTo
    ): void;
    
    /**
     * Send reminder notification
     */
    public function sendReminder(Approver $approver): void;
}
```

### 4.6 ApprovalPolicyService

Manages approval policies and their application.

```php
class ApprovalPolicyService
{
    /**
     * Find applicable policy for request
     */
    public function findApplicablePolicy(
        ApprovalRequestType $type,
        array $context
    ): ?ApprovalPolicy;
    
    /**
     * Build approval chain from policy
     */
    public function buildApprovalChain(
        ApprovalRequest $request,
        ApprovalPolicy $policy
    ): Collection<ApprovalStep>;
    
    /**
     * Validate policy rules
     */
    public function validatePolicy(ApprovalPolicy $policy): bool;
}
```

### 4.7 ApprovalAuditService

Maintains comprehensive audit trail.

```php
class ApprovalAuditService
{
    /**
     * Log approval action
     */
    public function logAction(
        ApprovalRequest $request,
        ApprovalActionType $action,
        User $user,
        ?array $details = null
    ): ApprovalAction;
    
    /**
     * Get audit trail for request
     */
    public function getAuditTrail(ApprovalRequest $request): Collection;
    
    /**
     * Generate compliance report
     */
    public function generateComplianceReport(
        Carbon $startDate,
        Carbon $endDate
    ): array;
}
```

---

## 5. Integration with ForgePulse Workflows

### 5.1 ApprovalStepHandler

New step handler for ForgePulse workflows that creates approval requests.

```php
class ApprovalStepHandler implements StepHandlerInterface
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $config = $step->configuration->getArrayCopy();
        
        // Extract approval configuration
        $requestType = $config['request_type'] ?? 'custom';
        $requestData = $config['request_data'] ?? [];
        $policyId = $config['policy_id'] ?? null;
        
        // Create approval request
        $approvalRequest = $this->approvalEngine->initiateApproval(
            execution: $step->workflow->executions()->latest()->first(),
            requestData: array_merge($requestData, $context),
            policy: $policyId ? ApprovalPolicy::find($policyId) : null
        );
        
        // Pause workflow execution until approval completes
        $step->workflow->executions()->latest()->first()->pause(
            'Waiting for approval: ' . $approvalRequest->title
        );
        
        return [
            'approval_request_id' => $approvalRequest->id,
            'approval_status' => $approvalRequest->status->value,
        ];
    }
}
```

### 5.2 Workflow Resume on Approval

When an approval is completed, the workflow automatically resumes.

```php
// In ApprovalEngine::completeApproval()
if ($request->workflowExecution && $request->workflowExecution->isPaused()) {
    $request->workflowExecution->resume();
    
    // Continue workflow execution
    dispatch(new ExecuteWorkflowJob($request->workflowExecution));
}
```

---

## 6. Step Configuration Examples

### 6.1 Simple Sequential Approval

```json
{
  "type": "approval",
  "configuration": {
    "request_type": "purchase_order",
    "policy_id": 1,
    "request_data": {
      "title": "Purchase Request for Office Supplies",
      "amount": "{{order.total}}",
      "currency": "USD",
      "items": "{{order.items}}"
    },
    "approval_chain": [
      {
        "step_name": "Manager Approval",
        "approver_roles": ["manager"],
        "approval_mode": "any"
      },
      {
        "step_name": "Finance Approval",
        "approver_roles": ["finance_director"],
        "approval_mode": "all"
      }
    ]
  }
}
```

### 6.2 Conditional Approval Based on Amount

```json
{
  "type": "approval",
  "configuration": {
    "request_type": "budget_request",
    "approval_chain": [
      {
        "step_name": "Department Manager",
        "approver_roles": ["dept_manager"],
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "amount", "operator": "<=", "value": 10000}
          ]
        }
      },
      {
        "step_name": "Senior Management",
        "approver_roles": ["senior_manager"],
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "amount", "operator": ">", "value": 10000},
            {"field": "amount", "operator": "<=", "value": 50000}
          ]
        }
      },
      {
        "step_name": "Executive Board",
        "approver_roles": ["executive", "ceo"],
        "approval_mode": "majority",
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "amount", "operator": ">", "value": 50000}
          ]
        }
      }
    ],
    "escalation_minutes": 1440
  }
}
```

### 6.3 Parallel Approval (All Must Approve)

```json
{
  "type": "approval",
  "configuration": {
    "request_type": "contract_approval",
    "approval_chain": [
      {
        "step_name": "Cross-Functional Review",
        "step_type": "parallel",
        "approvers": [
          {"role": "legal_counsel"},
          {"role": "finance_director"},
          {"role": "operations_manager"}
        ],
        "approval_mode": "all",
        "escalation_minutes": 2880
      }
    ]
  }
}
```

---

## 7. API Endpoints

### 7.1 Approval Request Management

```
POST   /api/forgepulse/approvals                    # Create approval request
GET    /api/forgepulse/approvals                    # List approval requests
GET    /api/forgepulse/approvals/{id}               # Get approval request
PUT    /api/forgepulse/approvals/{id}               # Update approval request
DELETE /api/forgepulse/approvals/{id}               # Cancel approval request

GET    /api/forgepulse/approvals/pending            # Get pending approvals for user
GET    /api/forgepulse/approvals/history            # Get approval history
```

### 7.2 Approval Actions

```
POST   /api/forgepulse/approvals/{id}/approve       # Approve request
POST   /api/forgepulse/approvals/{id}/reject        # Reject request
POST   /api/forgepulse/approvals/{id}/delegate      # Delegate approval
POST   /api/forgepulse/approvals/{id}/escalate      # Escalate approval
POST   /api/forgepulse/approvals/{id}/comment       # Add comment
POST   /api/forgepulse/approvals/{id}/recall        # Recall request (by requester)
```

### 7.3 Approval Policies

```
GET    /api/forgepulse/approval-policies            # List policies
POST   /api/forgepulse/approval-policies            # Create policy
GET    /api/forgepulse/approval-policies/{id}       # Get policy
PUT    /api/forgepulse/approval-policies/{id}       # Update policy
DELETE /api/forgepulse/approval-policies/{id}       # Delete policy
```

### 7.4 Delegation Management

```
GET    /api/forgepulse/delegations                  # List delegations
POST   /api/forgepulse/delegations                  # Create delegation
PUT    /api/forgepulse/delegations/{id}             # Update delegation
DELETE /api/forgepulse/delegations/{id}             # Revoke delegation
```

---

## 8. Events

### 8.1 Approval Request Events

```php
- ApprovalRequestSubmitted
- ApprovalRequestApproved
- ApprovalRequestRejected
- ApprovalRequestCancelled
- ApprovalRequestEscalated
```

### 8.2 Approval Step Events

```php
- ApprovalStepStarted
- ApprovalStepCompleted
- ApprovalStepSkipped
- ApprovalStepEscalated
```

### 8.3 Approver Events

```php
- ApproverAssigned
- ApproverApproved
- ApproverRejected
- ApproverDelegated
- ApprovalReminder
```

---

## 9. Notifications

### 9.1 Notification Types

```php
- ApprovalRequestNotification (to approvers)
- ApprovalDecisionNotification (to requester)
- ApprovalEscalationNotification (to escalation target)
- ApprovalReminderNotification (to pending approvers)
- ApprovalDelegationNotification (to delegate)
```

### 9.2 Notification Channels

- Email
- Database (in-app)
- Slack (optional integration)
- SMS (optional integration)
- Webhook (for external systems)

---

## 10. UI Components (Livewire)

### 10.1 ApprovalRequestManager

Component for creating and managing approval requests.

```php
<livewire:forgepulse::approval-request-manager />
```

### 10.2 ApprovalInbox

Component for Approvers to view and action pending approvals.

```php
<livewire:forgepulse::approval-inbox />
```

### 10.3 ApprovalTimeline

Component showing approval progress and history.

```php
<livewire:forgepulse::approval-timeline :request="$approvalRequest" />
```

### 10.4 ApprovalPolicyBuilder

Visual builder for creating approval policies.

```php
<livewire:forgepulse::approval-policy-builder :policy="$policy" />
```

### 10.5 ApprovalDashboard

Dashboard showing approval metrics and statistics.

```php
<livewire:forgepulse::approval-dashboard />
```

---

## 11. Configuration

### 11.1 Configuration File (config/forgepulse.php)

```php
'approvals' => [
    'enabled' => true,
    
    // Default approval settings
    'defaults' => [
        'escalation_enabled' => true,
        'escalation_minutes' => 1440, // 24 hours
        'reminder_enabled' => true,
        'reminder_minutes' => 720, // 12 hours
        'allow_delegation' => true,
        'allow_comments' => true,
        'require_rejection_reason' => true,
    ],
    
    // Notification settings
    'notifications' => [
        'channels' => ['mail', 'database'],
        'send_to_requester' => true,
        'send_reminders' => true,
        'reminder_frequency_minutes' => 720,
    ],
    
    // Approval limits
    'limits' => [
        'max_approvers_per_step' => 10,
        'max_steps_per_request' => 20,
        'max_delegation_days' => 90,
    ],
    
    // Audit settings
    'audit' => [
        'enabled' => true,
        'retention_days' => 2555, // 7 years
        'log_ip_address' => true,
        'log_user_agent' => true,
    ],
],
```

---

## 12. Database Migrations

### 12.1 Migration Order

```
1. create_approval_requests_table
2. create_approval_steps_table
3. create_approvers_table
4. create_approval_actions_table
5. create_approval_policies_table
6. create_approver_roles_table
7. create_approver_delegations_table
8. create_approval_attachments_table
9. create_approval_comments_table
10. create_approval_history_table
11. create_approver_role_user_pivot_table
```

---

## 13. Security Considerations

### 13.1 Access Control

1. **Role-Based Permissions**: Only assigned Approvers can approve/reject
2. **Request Visibility**: Requesters can only view their own requests
3. **Audit Trail**: All actions are logged with user, IP, and timestamp
4. **Delegation Limits**: Delegation must be explicitly enabled in policy
5. **Approval Authority**: Check approval limits based on amount/type

### 13.2 Data Protection

1. **Sensitive Data**: Encrypt sensitive request data
2. **Retention Policy**: Auto-archive old approval records
3. **GDPR Compliance**: Support data export and deletion
4. **Audit Logs**: Immutable audit trail for compliance

---

## 14. Performance Considerations

### 14.1 Optimisations

1. **Eager Loading**: Load relationships to avoid N+1 queries
2. **Caching**: Cache approval policies and role assignments
3. **Queue Jobs**: Process escalations and notifications asynchronously
4. **Database Indices**: Index frequently queried fields (status, dates, user_id)
5. **Pagination**: Paginate approval lists and history

### 14.2 Scalability

1. **Queue Workers**: Scale queue workers for notification processing
2. **Database Sharding**: Consider sharding for high-volume deployments
3. **Read Replicas**: Use read replicas for reporting queries
4. **Archive Old Data**: Move completed approvals to archive tables

---

## 15. Testing Strategy

### 15.1 Unit Tests

- Test approval logic (sequential, parallel, conditional)
- Test delegation service
- Test escalation service
- Test routing service
- Test policy evaluation

### 15.2 Integration Tests

- Test complete approval workflow end-to-end
- Test workflow integration with ForgePulse
- Test notification delivery
- Test API endpoints

### 15.3 Feature Tests

- Test approval request creation
- Test approval/rejection flow
- Test escalation triggers
- Test delegation scenarios
- Test policy application

---

## 16. Implementation Phases

### Phase 1: Core Infrastructure (2-3 weeks)
- Database models and migrations
- Core enums and value objects
- Basic ApprovalEngine service
- Simple sequential approval flow

### Phase 2: Advanced Features (2-3 weeks)
- Parallel and conditional approvals
- Delegation service
- Escalation service
- Policy management

### Phase 3: Integration (1-2 weeks)
- ForgePulse workflow integration
- ApprovalStepHandler implementation
- Events and listeners
- API endpoints

### Phase 4: UI Components (2-3 weeks)
- Livewire components
- Approval inbox
- Request manager
- Policy builder
- Timeline/history views

### Phase 5: Notifications & Audit (1 week)
- Notification system
- Audit trail
- Reporting
- Dashboard

### Phase 6: Testing & Documentation (1 week)
- Comprehensive test suite
- API documentation
- User guides
- Code documentation

---

## 17. Example Use Cases

### 17.1 Purchase Order Approval

```php
// Configure workflow with approval step
$workflow->steps()->create([
    'name' => 'Purchase Order Approval',
    'type' => StepType::APPROVAL,
    'configuration' => [
        'request_type' => 'purchase_order',
        'policy_id' => 1,
        'request_data' => [
            'title' => 'Purchase Request - {{po_number}}',
            'amount' => '{{total_amount}}',
            'currency' => 'USD',
            'department' => '{{department}}',
        ],
    ],
]);

// Execute workflow
$execution = $workflow->execute([
    'po_number' => 'PO-12345',
    'total_amount' => 5000,
    'department' => 'IT',
    'items' => [...],
]);
```

### 17.2 Budget Approval with Amount Thresholds

```php
$policy = ApprovalPolicy::create([
    'name' => 'Budget Approval Policy',
    'request_type' => ApprovalRequestType::BUDGET_REQUEST,
    'threshold_rules' => [
        ['max_amount' => 10000, 'approver_roles' => ['manager']],
        ['min_amount' => 10001, 'max_amount' => 50000, 'approver_roles' => ['director']],
        ['min_amount' => 50001, 'approver_roles' => ['executive', 'cfo']],
    ],
]);
```

### 17.3 Vacation Delegation

```php
$delegationService->createTemporaryDelegation(
    from: $manager,
    to: $assistantManager,
    startDate: now(),
    endDate: now()->addDays(14),
    scope: [
        'request_types' => ['purchase_order', 'expense_claim'],
        'max_amount' => 25000,
    ]
);
```

---

## 18. Monitoring & Metrics

### 18.1 Key Metrics

- Average approval time by type
- Approval/rejection rates
- Escalation frequency
- Approver response times
- Delegation usage
- Bottlenecks in approval chains

### 18.2 Dashboard Metrics

```php
- Pending approvals count
- Overdue approvals
- Approvals completed today/week/month
- Average time to approval
- Top approvers by volume
- Rejection reasons analysis
```

---

## 19. Extension Points

### 19.1 Custom Step Types

The approval engine supports custom approval step types through the existing ForgePulse step handler system.

### 19.2 Custom Routing Logic

Developers can implement custom routing services by extending `ApprovalRoutingService`.

### 19.3 Custom Policies

Approval policies support custom JSON rules that can be evaluated using the existing `ConditionalEvaluator`.

### 19.4 Custom Notifications

Additional notification channels can be added through Laravel's notification system.

---

## 20. Conclusion

This approval engine design provides a comprehensive, scalable solution for managing approval workflows within ForgePulse. It leverages the existing workflow infrastructure while adding specialised approval semantics, ensuring seamless integration and maintainability.

The phased implementation approach allows for incremental delivery of value, with core functionality available in Phase 1-2 and advanced features following in subsequent phases.

The design emphasises:
- **Flexibility**: Support for multiple approval patterns and policies
- **Auditability**: Complete audit trail for compliance
- **Scalability**: Queue-based processing and optimised queries
- **Usability**: Intuitive UI components and API
- **Integration**: Seamless integration with existing ForgePulse workflows

---

## Appendices

### Appendix A: Database Schema Diagram

```
[WorkflowExecution] 1---* [ApprovalRequest]
[ApprovalRequest] 1---* [ApprovalStep]
[ApprovalStep] 1---* [Approver]
[ApprovalRequest] 1---* [ApprovalAction]
[User] 1---* [Approver]
[ApprovalPolicy] 1---* [ApprovalRequest]
[User] *---* [ApproverRole]
[User] 1---* [ApproverDelegation]
```

### Appendix B: Sequence Diagrams

#### B.1 Simple Approval Flow

```
Requester -> System: Submit Approval Request
System -> ApprovalEngine: Create Approval Request
ApprovalEngine -> ApprovalRoutingService: Route to Approvers
ApprovalRoutingService -> Approver: Assign Approval
System -> Approver: Send Notification
Approver -> System: Approve/Reject
System -> ApprovalEngine: Process Decision
ApprovalEngine -> Requester: Notify Decision
ApprovalEngine -> WorkflowEngine: Resume Workflow
```

### Appendix C: State Machine Diagrams

#### C.1 Approval Request State Machine

```
DRAFT -> PENDING -> IN_PROGRESS -> APPROVED
                              |
                              +-> REJECTED
                              |
                              +-> ESCALATED -> IN_PROGRESS
                              |
                              +-> CANCELLED
                              |
                              +-> ON_HOLD -> IN_PROGRESS
```

---

**End of Document**
