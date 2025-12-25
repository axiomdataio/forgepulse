# User Action Engine Design for ForgePulse

**Version:** 3.0  
**Date:** 2025-12-25  
**Status:** Design Proposal (Generalized)

---

## Executive Summary

This design generalizes workflow user interactions into a single `UserAction` model that handles any step requiring user input: approvals, form submissions, document uploads, reviews, confirmations, and more. This provides maximum flexibility while maintaining architectural simplicity.

---

## 1. Design Philosophy

### 1.1 Core Principle

**User interactions are workflow steps that pause execution and wait for user input.**

Any workflow step can require user action:
- Approvals (approve/reject decisions)
- Form submissions (collect structured data)
- Document uploads (file attachments)
- Manual confirmations (proceed/cancel)
- Reviews (ratings, feedback)
- Sign-offs (acknowledgements)
- Data validation (verify information)
- Custom actions (extensible)

### 1.2 Benefits

- **Maximum Flexibility**: One model handles all user interaction patterns
- **Simpler**: Single model instead of approval-specific models
- **Extensible**: Easy to add new interaction types
- **Reusable**: Same infrastructure for many use cases
- **Consistent**: Unified API and UI patterns

---

## 2. Core Model: UserAction

### 2.1 UserAction Model

A single model that handles all types of user interactions with workflow steps.

```php
class UserAction extends Model
{
    // Core identification
    - id: int
    - workflow_execution_id: int
    - workflow_step_id: int
    - workflow_execution_log_id: int
    
    // User assignment
    - assigned_user_id: int              // Who should take action
    - actual_user_id: int                // Who actually took action (if different)
    - assigned_role: string (nullable)   // Or assigned by role
    
    // Action definition
    - action_type: UserActionType        // approval, form_submission, document_upload, confirmation, review, etc.
    - action_config: json                // Type-specific configuration
    - action_mode: UserActionMode        // single, any, all, majority, threshold
    
    // Status tracking
    - status: UserActionStatus           // pending, completed, rejected, delegated, escalated, expired
    - is_required: boolean               // Can this be skipped?
    
    // Response data
    - response_data: json (nullable)     // The user's response (decision, form data, file paths, etc.)
    - response_notes: text (nullable)    // User comments/notes
    
    // Delegation
    - is_delegated: boolean
    - delegated_from_user_id: int (nullable)
    - delegation_reason: text (nullable)
    
    // Escalation
    - is_escalated: boolean
    - escalated_from_user_id: int (nullable)
    - escalation_reason: text (nullable)
    
    // Timing
    - assigned_at: datetime
    - notified_at: datetime (nullable)
    - reminded_at: datetime (nullable)
    - due_at: datetime (nullable)
    - completed_at: datetime (nullable)
    
    // Audit
    - ip_address: string (nullable)
    - user_agent: string (nullable)
    - metadata: json (nullable)          // Additional tracking data
    
    - created_at: datetime
    - updated_at: datetime
    
    // Relationships
    - workflowExecution(): BelongsTo<WorkflowExecution>
    - workflowStep(): BelongsTo<WorkflowStep>
    - executionLog(): BelongsTo<WorkflowExecutionLog>
    - assignedUser(): BelongsTo<User>
    - actualUser(): BelongsTo<User>
    - delegatedFromUser(): BelongsTo<User>
    - escalatedFromUser(): BelongsTo<User>
    - attachments(): HasMany<UserActionAttachment>
}
```

### 2.2 Supporting Models

#### UserActionAttachment

```php
class UserActionAttachment extends Model
{
    - id: int
    - user_action_id: int
    - file_name: string
    - file_path: string
    - file_type: string
    - file_size: int
    - uploaded_by: int
    - created_at: datetime
}
```

#### UserDelegation (renamed from ApproverDelegation)

```php
class UserDelegation extends Model
{
    - id: int
    - from_user_id: int
    - to_user_id: int
    - action_types: json (nullable)      // Limit to specific action types
    - start_date: datetime
    - end_date: datetime (nullable)
    - reason: text (nullable)
    - scope_rules: json (nullable)
    - is_active: boolean
    - created_at: datetime
    - updated_at: datetime
}
```

---

## 3. Enums

### 3.1 UserActionType

```php
enum UserActionType: string
{
    // Approval actions
    case APPROVAL = 'approval';
    case SIGN_OFF = 'sign_off';
    
    // Data collection
    case FORM_SUBMISSION = 'form_submission';
    case DATA_VALIDATION = 'data_validation';
    
    // Document handling
    case DOCUMENT_UPLOAD = 'document_upload';
    case DOCUMENT_REVIEW = 'document_review';
    
    // Confirmations
    case MANUAL_CONFIRMATION = 'manual_confirmation';
    case ACKNOWLEDGEMENT = 'acknowledgement';
    
    // Feedback
    case REVIEW = 'review';
    case RATING = 'rating';
    case FEEDBACK = 'feedback';
    
    // Custom
    case CUSTOM = 'custom';
}
```

### 3.2 UserActionStatus

```php
enum UserActionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case DELEGATED = 'delegated';
    case ESCALATED = 'escalated';
    case EXPIRED = 'expired';
    case SKIPPED = 'skipped';
}
```

### 3.3 UserActionMode

```php
enum UserActionMode: string
{
    case SINGLE = 'single';         // One user action required
    case ANY = 'any';               // Any one of multiple users
    case ALL = 'all';               // All assigned users must act
    case MAJORITY = 'majority';     // >50% must act
    case THRESHOLD = 'threshold';   // Configurable threshold
    case SEQUENTIAL = 'sequential'; // One after another in order
}
```

---

## 4. Step Types

### 4.1 Updated StepType Enum

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
    
    // User interaction types
    case USER_ACTION = 'user_action';              // Generic user action
    case USER_ACTION_PARALLEL = 'user_action_parallel';   // Multiple users simultaneously
    case USER_ACTION_SEQUENTIAL = 'user_action_sequential'; // Multiple users in sequence
    
    public function handlerClass(): string
    {
        return match ($this) {
            // ... existing handlers ...
            self::USER_ACTION => \AlizHarb\ForgePulse\Services\StepHandlers\UserActionHandler::class,
            self::USER_ACTION_PARALLEL => \AlizHarb\ForgePulse\Services\StepHandlers\UserActionParallelHandler::class,
            self::USER_ACTION_SEQUENTIAL => \AlizHarb\ForgePulse\Services\StepHandlers\UserActionSequentialHandler::class,
        };
    }
}
```

---

## 5. Step Configuration Examples

### 5.1 Simple Approval

```json
{
  "type": "user_action",
  "name": "Manager Approval",
  "configuration": {
    "action_type": "approval",
    "title": "Approve Purchase Order {{po_number}}",
    "description": "Please review and approve this purchase request for {{amount}} {{currency}}",
    
    "users": [
      {"user_id": 123},
      {"role": "manager"},
      {"email": "manager@company.com"}
    ],
    
    "action_mode": "any",
    
    "action_config": {
      "decisions": ["approve", "reject"],
      "require_notes_on_reject": true,
      "allow_conditional_approval": false
    },
    
    "allow_delegation": true,
    "escalation_minutes": 1440,
    "escalation_target": {"role": "senior_manager"},
    "reminder_minutes": 720
  }
}
```

### 5.2 Form Submission

```json
{
  "type": "user_action",
  "name": "Collect Additional Information",
  "configuration": {
    "action_type": "form_submission",
    "title": "Additional Information Required",
    "description": "Please provide additional details for this request",
    
    "users": [
      {"user_id": "{{requester_id}}"}
    ],
    
    "action_config": {
      "form_schema": {
        "fields": [
          {
            "name": "business_justification",
            "type": "textarea",
            "label": "Business Justification",
            "required": true,
            "validation": "min:100"
          },
          {
            "name": "cost_center",
            "type": "select",
            "label": "Cost Center",
            "options": ["IT", "Marketing", "Operations"],
            "required": true
          },
          {
            "name": "urgency",
            "type": "radio",
            "label": "Urgency Level",
            "options": ["Low", "Medium", "High", "Critical"],
            "required": true
          }
        ]
      }
    },
    
    "is_required": true,
    "due_minutes": 2880
  }
}
```

### 5.3 Document Upload

```json
{
  "type": "user_action",
  "name": "Upload Supporting Documents",
  "configuration": {
    "action_type": "document_upload",
    "title": "Upload Required Documents",
    "description": "Please upload all supporting documents for this request",
    
    "users": [
      {"user_id": "{{requester_id}}"}
    ],
    
    "action_config": {
      "allowed_types": ["pdf", "docx", "xlsx", "jpg", "png"],
      "max_file_size_mb": 10,
      "min_files": 1,
      "max_files": 5,
      "required_documents": [
        "Purchase Request Form",
        "Vendor Quote",
        "Budget Approval"
      ]
    },
    
    "is_required": true
  }
}
```

### 5.4 Parallel Approval (All Must Approve)

```json
{
  "type": "user_action_parallel",
  "name": "Cross-Functional Review",
  "configuration": {
    "action_type": "approval",
    "title": "Contract Review Required",
    
    "users": [
      {"role": "legal_counsel"},
      {"role": "finance_director"},
      {"role": "operations_manager"}
    ],
    
    "action_mode": "all",
    
    "action_config": {
      "decisions": ["approve", "reject", "request_changes"],
      "require_notes": true
    },
    
    "escalation_minutes": 2880
  }
}
```

### 5.5 Sequential Approval Chain

```json
{
  "type": "user_action_sequential",
  "name": "Budget Approval Chain",
  "configuration": {
    "action_type": "approval",
    "title": "Budget Request Approval",
    
    "action_chain": [
      {
        "name": "Department Manager",
        "users": [{"role": "dept_manager"}],
        "action_mode": "any"
      },
      {
        "name": "Finance Director",
        "users": [{"role": "finance_director"}],
        "action_mode": "any",
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "amount", "operator": ">", "value": 10000}
          ]
        }
      },
      {
        "name": "CFO",
        "users": [{"role": "cfo"}],
        "action_mode": "any",
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "amount", "operator": ">", "value": 50000}
          ]
        }
      }
    ],
    
    "action_config": {
      "decisions": ["approve", "reject"],
      "pass_context_forward": true
    }
  }
}
```

### 5.6 Review with Rating

```json
{
  "type": "user_action",
  "name": "Service Quality Review",
  "configuration": {
    "action_type": "review",
    "title": "Review Service Quality",
    "description": "Please provide your feedback on the service provided",
    
    "users": [
      {"user_id": "{{customer_id}}"}
    ],
    
    "action_config": {
      "rating_scale": 5,
      "rating_labels": ["Poor", "Fair", "Good", "Very Good", "Excellent"],
      "aspects": [
        {"name": "timeliness", "label": "Timeliness"},
        {"name": "quality", "label": "Quality of Work"},
        {"name": "communication", "label": "Communication"},
        {"name": "professionalism", "label": "Professionalism"}
      ],
      "require_comments": true,
      "min_comment_length": 50
    }
  }
}
```

### 5.7 Manual Confirmation

```json
{
  "type": "user_action",
  "name": "Confirm Deployment",
  "configuration": {
    "action_type": "manual_confirmation",
    "title": "Confirm Production Deployment",
    "description": "Please review the deployment checklist and confirm you're ready to proceed",
    
    "users": [
      {"role": "devops_engineer"}
    ],
    
    "action_config": {
      "checklist": [
        "Backup completed successfully",
        "Staging tests passed",
        "Rollback plan documented",
        "Stakeholders notified"
      ],
      "require_all_checked": true,
      "confirmation_phrase": "I confirm the deployment",
      "decisions": ["proceed", "abort"]
    }
  }
}
```

### 5.8 Consensus Approval

```json
{
  "type": "user_action_parallel",
  "name": "Board Vote",
  "configuration": {
    "action_type": "approval",
    "title": "Board Resolution - {{resolution_title}}",
    
    "users": [
      {"role": "board_member"}
    ],
    
    "action_mode": "threshold",
    "threshold_percentage": 66,
    
    "action_config": {
      "decisions": ["approve", "reject", "abstain"],
      "allow_abstain": true,
      "count_abstain_in_total": false
    }
  }
}
```

---

## 6. UserActionService

### 6.1 Core Service

```php
class UserActionService
{
    /**
     * Create user action(s) for a workflow step
     */
    public function createUserActions(
        WorkflowStep $step,
        WorkflowExecution $execution,
        array $context
    ): Collection {
        $config = $step->configuration->getArrayCopy();
        $users = $this->resolveUsers($config['users'] ?? [], $context);
        
        $actions = collect();
        
        foreach ($users as $user) {
            $action = UserAction::create([
                'workflow_execution_id' => $execution->id,
                'workflow_step_id' => $step->id,
                'workflow_execution_log_id' => $this->getCurrentLog($step)->id,
                'assigned_user_id' => $user->id,
                'actual_user_id' => $user->id,
                'action_type' => UserActionType::from($config['action_type']),
                'action_config' => $config['action_config'] ?? [],
                'action_mode' => UserActionMode::from($config['action_mode'] ?? 'single'),
                'status' => UserActionStatus::PENDING,
                'is_required' => $config['is_required'] ?? true,
                'assigned_at' => now(),
                'due_at' => $this->calculateDueDate($config),
            ]);
            
            $actions->push($action);
        }
        
        // Send notifications
        $this->notifyUsers($actions, $step, $context);
        
        // Schedule escalation if configured
        if (isset($config['escalation_minutes'])) {
            $this->scheduleEscalation($actions, $config['escalation_minutes']);
        }
        
        return $actions;
    }
    
    /**
     * Process a user action response
     */
    public function processUserAction(
        UserAction $action,
        User $user,
        array $responseData,
        ?string $notes = null
    ): void {
        // Validate response based on action_type and action_config
        $this->validateResponse($action, $responseData);
        
        // Update action
        $action->update([
            'status' => $this->determineStatus($action, $responseData),
            'response_data' => $responseData,
            'response_notes' => $notes,
            'completed_at' => now(),
            'actual_user_id' => $user->id,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Check if step is complete
        if ($this->isStepComplete($action->workflowStep)) {
            $this->completeUserActionStep($action->workflowStep);
        }
    }
    
    /**
     * Validate response data based on action type
     */
    protected function validateResponse(UserAction $action, array $responseData): void
    {
        $validator = match ($action->action_type) {
            UserActionType::APPROVAL => new ApprovalResponseValidator(),
            UserActionType::FORM_SUBMISSION => new FormSubmissionValidator(),
            UserActionType::DOCUMENT_UPLOAD => new DocumentUploadValidator(),
            UserActionType::REVIEW => new ReviewResponseValidator(),
            // ... other validators
            default => new GenericResponseValidator(),
        };
        
        $validator->validate($action, $responseData);
    }
    
    /**
     * Determine if step is complete based on action_mode
     */
    protected function isStepComplete(WorkflowStep $step): bool
    {
        $config = $step->configuration->getArrayCopy();
        $mode = UserActionMode::from($config['action_mode'] ?? 'single');
        
        $actions = UserAction::where('workflow_step_id', $step->id)
            ->whereIn('status', [UserActionStatus::COMPLETED, UserActionStatus::REJECTED])
            ->get();
        
        $totalActions = UserAction::where('workflow_step_id', $step->id)->count();
        $completedCount = $actions->where('status', UserActionStatus::COMPLETED)->count();
        $rejectedCount = $actions->where('status', UserActionStatus::REJECTED)->count();
        
        // For approval type, one rejection typically fails the step
        if ($config['action_type'] === 'approval' && $rejectedCount > 0) {
            return true; // Step complete (rejected)
        }
        
        return match ($mode) {
            UserActionMode::SINGLE => $completedCount >= 1,
            UserActionMode::ANY => $completedCount >= 1,
            UserActionMode::ALL => $completedCount === $totalActions,
            UserActionMode::MAJORITY => $completedCount > ($totalActions / 2),
            UserActionMode::THRESHOLD => $completedCount >= $this->calculateThreshold(
                $totalActions,
                $config['threshold_percentage'] ?? 50
            ),
            UserActionMode::SEQUENTIAL => $this->isSequentialComplete($step),
        };
    }
    
    /**
     * Complete user action step and resume workflow
     */
    protected function completeUserActionStep(WorkflowStep $step): void
    {
        $execution = $step->workflow->executions()->latest()->first();
        
        // Gather all responses
        $actions = UserAction::where('workflow_step_id', $step->id)->get();
        
        // Determine overall result
        $isSuccessful = $this->determineStepResult($step, $actions);
        
        // Update execution log
        $log = WorkflowExecutionLog::where('workflow_step_id', $step->id)
            ->where('workflow_execution_id', $execution->id)
            ->latest()
            ->first();
        
        if ($log) {
            $log->update([
                'status' => $isSuccessful ? LogStatus::COMPLETED : LogStatus::FAILED,
                'output' => [
                    'user_action_result' => $isSuccessful ? 'success' : 'failed',
                    'responses' => $actions->map(fn($a) => [
                        'user_id' => $a->actual_user_id,
                        'status' => $a->status->value,
                        'response_data' => $a->response_data,
                    ])->toArray(),
                ],
            ]);
        }
        
        // Resume workflow execution
        if ($execution->isPaused()) {
            $execution->resume();
            dispatch(new \AlizHarb\ForgePulse\Jobs\ExecuteWorkflowJob($execution));
        }
        
        // Fire event
        event(new UserActionStepCompleted($step, $execution, $isSuccessful, $actions));
    }
    
    /**
     * Determine overall step result
     */
    protected function determineStepResult(WorkflowStep $step, Collection $actions): bool
    {
        $config = $step->configuration->getArrayCopy();
        
        // For approvals, any rejection = failure
        if ($config['action_type'] === 'approval') {
            return $actions->where('status', UserActionStatus::REJECTED)->count() === 0;
        }
        
        // For other types, completion = success
        return $actions->where('status', UserActionStatus::COMPLETED)->count() > 0;
    }
}
```

---

## 7. Step Handlers

### 7.1 UserActionHandler

```php
class UserActionHandler
{
    public function __construct(
        protected UserActionService $userActionService
    ) {}
    
    public function handle(WorkflowStep $step, array $context): array
    {
        $execution = $this->getCurrentExecution($step);
        
        // Create user actions
        $actions = $this->userActionService->createUserActions($step, $execution, $context);
        
        // Pause workflow execution
        $execution->pause('Waiting for user action: ' . $step->name);
        
        return [
            'user_action_status' => 'pending',
            'user_action_count' => $actions->count(),
            'user_action_ids' => $actions->pluck('id')->toArray(),
        ];
    }
}
```

### 7.2 UserActionParallelHandler

```php
class UserActionParallelHandler extends UserActionHandler
{
    // Same as UserActionHandler - parallel processing is handled by UserActionService
    // based on action_mode configuration
}
```

### 7.3 UserActionSequentialHandler

```php
class UserActionSequentialHandler extends UserActionHandler
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $execution = $this->getCurrentExecution($step);
        $config = $step->configuration->getArrayCopy();
        $actionChain = $config['action_chain'];
        
        // Determine current step in chain
        $currentChainIndex = $this->getCurrentChainIndex($step, $execution);
        $currentChainStep = $actionChain[$currentChainIndex];
        
        // Check conditions for current step
        if (isset($currentChainStep['conditions'])) {
            $evaluator = app(ConditionalEvaluator::class);
            if (!$evaluator->evaluate($currentChainStep['conditions'], $context)) {
                // Skip to next step in chain
                return $this->advanceToNextInChain($step, $execution, $currentChainIndex + 1, $context);
            }
        }
        
        // Create user actions for current chain step
        $actions = $this->userActionService->createUserActions($step, $execution, $context);
        
        // Pause workflow
        $execution->pause("Waiting for user action: {$currentChainStep['name']}");
        
        return [
            'user_action_status' => 'pending',
            'current_chain_index' => $currentChainIndex,
            'current_chain_name' => $currentChainStep['name'],
            'total_chain_steps' => count($actionChain),
            'user_action_ids' => $actions->pluck('id')->toArray(),
        ];
    }
}
```

---

## 8. Response Validators

### 8.1 Validator Interface

```php
interface UserActionResponseValidator
{
    public function validate(UserAction $action, array $responseData): void;
}
```

### 8.2 ApprovalResponseValidator

```php
class ApprovalResponseValidator implements UserActionResponseValidator
{
    public function validate(UserAction $action, array $responseData): void
    {
        $config = $action->action_config;
        $allowedDecisions = $config['decisions'] ?? ['approve', 'reject'];
        
        if (!isset($responseData['decision'])) {
            throw new ValidationException('Decision is required');
        }
        
        if (!in_array($responseData['decision'], $allowedDecisions)) {
            throw new ValidationException('Invalid decision');
        }
        
        if ($responseData['decision'] === 'reject' && 
            ($config['require_notes_on_reject'] ?? false) && 
            empty($responseData['notes'])) {
            throw new ValidationException('Notes are required when rejecting');
        }
    }
}
```

### 8.3 FormSubmissionValidator

```php
class FormSubmissionValidator implements UserActionResponseValidator
{
    public function validate(UserAction $action, array $responseData): void
    {
        $schema = $action->action_config['form_schema'] ?? [];
        $fields = $schema['fields'] ?? [];
        
        foreach ($fields as $field) {
            $fieldName = $field['name'];
            $isRequired = $field['required'] ?? false;
            
            if ($isRequired && !isset($responseData[$fieldName])) {
                throw new ValidationException("Field {$fieldName} is required");
            }
            
            if (isset($responseData[$fieldName]) && isset($field['validation'])) {
                $this->validateField($responseData[$fieldName], $field['validation'], $fieldName);
            }
        }
    }
}
```

### 8.4 DocumentUploadValidator

```php
class DocumentUploadValidator implements UserActionResponseValidator
{
    public function validate(UserAction $action, array $responseData): void
    {
        $config = $action->action_config;
        $minFiles = $config['min_files'] ?? 0;
        $maxFiles = $config['max_files'] ?? PHP_INT_MAX;
        $allowedTypes = $config['allowed_types'] ?? [];
        
        $files = $responseData['files'] ?? [];
        
        if (count($files) < $minFiles) {
            throw new ValidationException("At least {$minFiles} file(s) required");
        }
        
        if (count($files) > $maxFiles) {
            throw new ValidationException("Maximum {$maxFiles} file(s) allowed");
        }
        
        foreach ($files as $file) {
            if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
                throw new ValidationException("File type {$file['type']} not allowed");
            }
        }
    }
}
```

---

## 9. API Endpoints

### 9.1 User Action Endpoints

```php
// List pending actions for authenticated user
GET /api/forgepulse/user-actions/pending

// Get specific user action
GET /api/forgepulse/user-actions/{userAction}

// Submit response to user action
POST /api/forgepulse/user-actions/{userAction}/respond
{
  "response_data": {
    "decision": "approve",
    "custom_field": "value"
  },
  "notes": "Looks good to me"
}

// Delegate user action
POST /api/forgepulse/user-actions/{userAction}/delegate
{
  "delegate_to_user_id": 456,
  "reason": "On vacation"
}

// Get user action history for execution
GET /api/forgepulse/executions/{execution}/user-actions

// Upload files for user action
POST /api/forgepulse/user-actions/{userAction}/upload
Content-Type: multipart/form-data
```

### 9.2 Delegation Endpoints

```php
// Create delegation
POST /api/forgepulse/delegations
{
  "to_user_id": 456,
  "action_types": ["approval", "form_submission"],
  "start_date": "2025-12-26",
  "end_date": "2026-01-10",
  "reason": "Vacation"
}

// List delegations
GET /api/forgepulse/delegations

// Revoke delegation
DELETE /api/forgepulse/delegations/{delegation}
```

---

## 10. Events

### 10.1 User Action Events

```php
- UserActionCreated
- UserActionCompleted
- UserActionRejected
- UserActionDelegated
- UserActionEscalated
- UserActionReminder
- UserActionStepCompleted
```

---

## 11. Notifications

### 11.1 Notification Classes

```php
- UserActionAssignedNotification
- UserActionCompletedNotification (to requester)
- UserActionReminderNotification
- UserActionDelegatedNotification
- UserActionEscalatedNotification
```

---

## 12. UI Components (Livewire)

### 12.1 User Action Inbox

```php
<livewire:forgepulse::user-action-inbox />
```

Shows all pending user actions for authenticated user with:
- Filters by action type
- Sort by due date, priority
- Quick action buttons
- Delegation options

### 12.2 User Action Response Form

```php
<livewire:forgepulse::user-action-form :userAction="$userAction" />
```

Dynamic form that renders based on action_type and action_config:
- Approval decisions (approve/reject buttons)
- Form fields (text, select, radio, etc.)
- Document upload interface
- Rating/review interface
- Confirmation checklist

### 12.3 User Action Timeline

```php
<livewire:forgepulse::user-action-timeline :execution="$execution" />
```

Shows progress of all user actions in a workflow execution.

---

## 13. Complete Example: Purchase Order Workflow

### 13.1 Workflow with Multiple User Action Types

```php
$workflow = Workflow::create([
    'name' => 'Purchase Order Request',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Form submission to collect details
$workflow->steps()->create([
    'name' => 'Request Details',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Purchase Order Request Form',
        'users' => [['user_id' => '{{requester_id}}']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'justification', 'type' => 'textarea', 'required' => true],
                    ['name' => 'cost_center', 'type' => 'select', 'options' => ['IT', 'Marketing'], 'required' => true],
                    ['name' => 'urgency', 'type' => 'radio', 'options' => ['Normal', 'Urgent'], 'required' => true],
                ]
            ]
        ],
    ],
]);

// Step 2: Document upload
$workflow->steps()->create([
    'name' => 'Upload Documents',
    'type' => StepType::USER_ACTION,
    'position' => 2,
    'configuration' => [
        'action_type' => 'document_upload',
        'title' => 'Upload Required Documents',
        'users' => [['user_id' => '{{requester_id}}']],
        'action_config' => [
            'allowed_types' => ['pdf', 'docx'],
            'min_files' => 2,
            'max_files' => 5,
        ],
    ],
]);

// Step 3: Manager approval
$workflow->steps()->create([
    'name' => 'Manager Approval',
    'type' => StepType::USER_ACTION,
    'position' => 3,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Purchase Order Approval - {{po_number}}',
        'users' => [['role' => 'manager']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject'],
            'require_notes_on_reject' => true,
        ],
        'escalation_minutes' => 1440,
    ],
]);

// Step 4: Conditional high-value approval
$highValueCheck = $workflow->steps()->create([
    'name' => 'High Value Check',
    'type' => StepType::CONDITION,
    'position' => 4,
    'configuration' => [
        'field' => 'amount',
        'operator' => '>',
        'value' => 50000,
    ],
]);

$workflow->steps()->create([
    'name' => 'Executive Approval',
    'type' => StepType::USER_ACTION_PARALLEL,
    'position' => 5,
    'parent_step_id' => $highValueCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'High-Value Purchase Approval',
        'users' => [
            ['role' => 'cfo'],
            ['role' => 'ceo'],
        ],
        'action_mode' => 'majority',
        'action_config' => [
            'decisions' => ['approve', 'reject', 'request_more_info'],
        ],
    ],
]);

// Step 5: Process order
$workflow->steps()->create([
    'name' => 'Process Order',
    'type' => StepType::ACTION,
    'position' => 6,
    'configuration' => [
        'action_class' => \App\Actions\ProcessPurchaseOrder::class,
    ],
]);

// Step 6: Request feedback
$workflow->steps()->create([
    'name' => 'Service Feedback',
    'type' => StepType::USER_ACTION,
    'position' => 7,
    'configuration' => [
        'action_type' => 'review',
        'title' => 'How was your procurement experience?',
        'users' => [['user_id' => '{{requester_id}}']],
        'action_config' => [
            'rating_scale' => 5,
            'aspects' => [
                ['name' => 'speed', 'label' => 'Process Speed'],
                ['name' => 'clarity', 'label' => 'Process Clarity'],
            ],
        ],
        'is_required' => false, // Optional feedback
    ],
]);
```

### 13.2 Execute Workflow

```php
$execution = $workflow->execute([
    'requester_id' => auth()->id(),
    'po_number' => 'PO-2025-001',
]);
```

### 13.3 User Responds

```php
// Step 1: Form submission
POST /api/forgepulse/user-actions/123/respond
{
  "response_data": {
    "justification": "Need new laptops for dev team",
    "cost_center": "IT",
    "urgency": "Normal"
  }
}

// Step 2: Document upload
POST /api/forgepulse/user-actions/124/upload
(multipart form with files)

// Step 3: Manager approval
POST /api/forgepulse/user-actions/125/respond
{
  "response_data": {
    "decision": "approve"
  },
  "notes": "Approved - within budget"
}

// Step 6: Review
POST /api/forgepulse/user-actions/128/respond
{
  "response_data": {
    "speed": 4,
    "clarity": 5,
    "comments": "Very smooth process!"
  }
}
```

---

## 14. Use Case Matrix

| Use Case | Action Type | Action Mode | Configuration |
|----------|-------------|-------------|---------------|
| Simple approval | approval | any | decisions: [approve, reject] |
| Multi-approver | approval | all/majority | threshold_percentage: 60 |
| Sequential approvals | approval | sequential | action_chain with conditions |
| Data collection | form_submission | single | form_schema with fields |
| Document upload | document_upload | single | file constraints |
| Review/rating | review | single | rating_scale, aspects |
| Manual sign-off | sign_off | any | confirmation requirements |
| Board vote | approval | threshold | allow_abstain: true |
| Deployment gate | manual_confirmation | any | checklist, confirmation_phrase |

---

## 15. Benefits of Generalized Approach

### 15.1 Flexibility

- **Single Model**: One `UserAction` model handles all interaction types
- **Extensible**: Easy to add new action types without schema changes
- **Composable**: Mix different action types in same workflow
- **Configurable**: Action behavior defined in JSON configuration

### 15.2 Simplicity

- **Fewer Models**: 2 models (UserAction, UserDelegation) vs. 10+ in approval-specific design
- **Fewer Services**: 1 main service (UserActionService) + helpers
- **Consistent API**: Same endpoints for all action types
- **Unified UI**: One inbox for all pending actions

### 15.3 Power

- **Beyond Approvals**: Supports forms, uploads, reviews, confirmations
- **Rich Data**: Store any response structure in JSON
- **Validation**: Type-specific validators ensure data quality
- **Audit Trail**: Complete history of all user interactions

### 15.4 Reusability

- **Delegation Works for Everything**: Not just approvals
- **Escalation Works for Everything**: Any overdue action can escalate
- **Notifications Work for Everything**: Unified notification system
- **Same UI Components**: One inbox, one timeline, one form builder

---

## 16. Configuration

### 16.1 Add to config/forgepulse.php

```php
'user_actions' => [
    'enabled' => true,
    
    'defaults' => [
        'allow_delegation' => true,
        'require_notes' => false,
        'escalation_enabled' => true,
        'reminder_enabled' => true,
        'reminder_minutes' => 720,
    ],
    
    'action_types' => [
        'approval' => [
            'require_notes_on_reject' => true,
            'allow_conditional_approval' => false,
        ],
        'form_submission' => [
            'auto_save_draft' => true,
            'validation_mode' => 'strict',
        ],
        'document_upload' => [
            'storage_disk' => 's3',
            'max_file_size_mb' => 10,
            'scan_for_viruses' => true,
        ],
    ],
    
    'notifications' => [
        'channels' => ['mail', 'database'],
        'send_reminders' => true,
        'reminder_frequency_minutes' => 720,
    ],
    
    'escalation' => [
        'enabled' => true,
        'check_frequency_minutes' => 60,
    ],
    
    'audit' => [
        'log_ip_address' => true,
        'log_user_agent' => true,
        'retention_days' => 2555,
    ],
],
```

---

## 17. Database Migrations

```php
// create_user_actions_table.php
Schema::create('user_actions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('workflow_execution_id')->constrained()->cascadeOnDelete();
    $table->foreignId('workflow_step_id')->constrained()->cascadeOnDelete();
    $table->foreignId('workflow_execution_log_id')->constrained()->cascadeOnDelete();
    
    $table->foreignId('assigned_user_id')->constrained('users');
    $table->foreignId('actual_user_id')->constrained('users');
    $table->string('assigned_role')->nullable();
    
    $table->string('action_type'); // approval, form_submission, document_upload, etc.
    $table->json('action_config')->nullable();
    $table->string('action_mode')->default('single');
    
    $table->string('status');
    $table->boolean('is_required')->default(true);
    
    $table->json('response_data')->nullable();
    $table->text('response_notes')->nullable();
    
    $table->boolean('is_delegated')->default(false);
    $table->foreignId('delegated_from_user_id')->nullable()->constrained('users');
    $table->text('delegation_reason')->nullable();
    
    $table->boolean('is_escalated')->default(false);
    $table->foreignId('escalated_from_user_id')->nullable()->constrained('users');
    $table->text('escalation_reason')->nullable();
    
    $table->timestamp('assigned_at');
    $table->timestamp('notified_at')->nullable();
    $table->timestamp('reminded_at')->nullable();
    $table->timestamp('due_at')->nullable();
    $table->timestamp('completed_at')->nullable();
    
    $table->string('ip_address')->nullable();
    $table->text('user_agent')->nullable();
    $table->json('metadata')->nullable();
    
    $table->timestamps();
    
    $table->index(['assigned_user_id', 'status']);
    $table->index(['workflow_execution_id', 'action_type']);
    $table->index(['due_at', 'status']);
});

// create_user_action_attachments_table.php
Schema::create('user_action_attachments', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_action_id')->constrained()->cascadeOnDelete();
    $table->string('file_name');
    $table->string('file_path');
    $table->string('file_type');
    $table->integer('file_size');
    $table->foreignId('uploaded_by')->constrained('users');
    $table->timestamps();
});

// create_user_delegations_table.php
Schema::create('user_delegations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('from_user_id')->constrained('users');
    $table->foreignId('to_user_id')->constrained('users');
    $table->json('action_types')->nullable();
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

## 18. Implementation Timeline

### Phase 1: Core Infrastructure (1-2 weeks)
- UserAction model and migration
- UserActionType, UserActionStatus, UserActionMode enums
- UserActionService core methods
- Basic UserActionHandler

### Phase 2: Action Types (1-2 weeks)
- Approval validator
- Form submission validator
- Document upload validator
- Review validator
- Response processing

### Phase 3: Advanced Features (1 week)
- Sequential and parallel handlers
- Delegation service
- Escalation service
- Notification system

### Phase 4: API & Events (1 week)
- REST API endpoints
- Event dispatching
- Webhook integration

### Phase 5: UI Components (2 weeks)
- User action inbox
- Dynamic response forms
- Timeline component
- Delegation management UI

### Phase 6: Testing & Documentation (1 week)
- Comprehensive tests
- API documentation
- User guides
- Example workflows

**Total: 7-9 weeks**

---

## 19. Conclusion

This generalized design provides maximum flexibility and reusability by treating all user interactions as variants of a single `UserAction` concept. Instead of building an approval-specific system, we've created a foundation that supports:

- ✅ Approvals
- ✅ Form submissions
- ✅ Document uploads
- ✅ Reviews and ratings
- ✅ Manual confirmations
- ✅ Custom action types

All with:
- Single model and service
- Consistent API
- Unified UI
- Reusable delegation and escalation
- Complete audit trail

This approach is simpler than the approval-specific design, yet far more powerful and extensible.

---

**End of Document**
