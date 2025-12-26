# Workflow Context Management: Ensuring Informed Decisions

**Version:** 1.0  
**Date:** 2025-12-25  
**Context:** How to provide users with complete context for approvals

---

## Executive Summary

This document explains how to ensure users have complete context when reviewing and approving workflow actions, covering data propagation, presentation patterns, and access control.

---

## Table of Contents

1. [Context Architecture](#1-context-architecture)
2. [Context Data Flow](#2-context-data-flow)
3. [Context Types](#3-context-types)
4. [Displaying Context to Users](#4-displaying-context-to-users)
5. [Context Access Control](#5-context-access-control)
6. [Real-World Examples](#6-real-world-examples)
7. [Best Practices](#7-best-practices)

---

## 1. Context Architecture

### 1.1 Where Context Lives

```
┌─────────────────────────────────────────────────────────┐
│                    Context Storage                       │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  WorkflowExecution                              │    │
│  │  ├─ context (JSON) ← Initial workflow data     │    │
│  │  ├─ output (JSON) ← Accumulated results        │    │
│  │  └─ metadata (JSON) ← Additional tracking      │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  WorkflowStep                                   │    │
│  │  ├─ configuration (JSON) ← Step config         │    │
│  │  └─ conditions (JSON) ← Conditional logic      │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  UserAction                                     │    │
│  │  ├─ action_config (JSON) ← What to show user   │    │
│  │  ├─ response_data (JSON) ← User's response     │    │
│  │  └─ metadata (JSON) ← Additional context       │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  WorkflowExecutionLog                           │    │
│  │  ├─ input (JSON) ← What went into this step    │    │
│  │  └─ output (JSON) ← What came out              │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
│  ┌────────────────────────────────────────────────┐    │
│  │  UserActionAttachment                           │    │
│  │  └─ Uploaded files/documents                    │    │
│  └────────────────────────────────────────────────┘    │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### 1.2 Context Propagation

```php
// Initial workflow execution with context
$execution = $workflow->execute([
    'requester_id' => auth()->id(),
    'requester_name' => auth()->user()->name,
    'requester_department' => auth()->user()->department,
    'po_number' => 'PO-2025-001',
    'supplier_id' => 123,
    'supplier_name' => 'Acme Corp',
    'items' => [
        ['sku' => 'LAPTOP-001', 'quantity' => 10, 'unit_price' => 1500],
        ['sku' => 'MOUSE-001', 'quantity' => 10, 'unit_price' => 25],
    ],
    'total_amount' => 15250,
    'currency' => 'USD',
    'cost_center' => 'IT',
    'business_justification' => 'New laptops for development team',
    'delivery_location' => 'HQ - Building A',
    'requested_delivery_date' => '2026-01-15',
]);

// This context is available to ALL steps in the workflow
// Each step can ADD to the context, but cannot remove from it
```

---

## 2. Context Data Flow

### 2.1 How Context Flows Through a Workflow

```
Start Workflow
├─ Initial Context: { requester_id: 1, amount: 15000, items: [...] }
│
Step 1: Calculate Total
├─ Receives: { requester_id: 1, amount: 15000, items: [...] }
├─ Adds: { calculated_total: 15250, tax: 1525, grand_total: 16775 }
└─ Context Now: { requester_id: 1, amount: 15000, items: [...], calculated_total: 15250, tax: 1525, grand_total: 16775 }
│
Step 2: Manager Approval (USER ACTION)
├─ Receives: ALL context from above
├─ User Sees: Configured subset via action_config.show_context
├─ User Adds: { manager_decision: 'approve', manager_notes: 'Approved' }
└─ Context Now: { ...previous..., manager_decision: 'approve', manager_notes: 'Approved', manager_id: 5, approved_at: '2025-12-25 10:30:00' }
│
Step 3: Budget Check
├─ Receives: ALL accumulated context
├─ Adds: { budget_available: true, remaining_budget: 50000 }
└─ Context Now: { ...previous..., budget_available: true, remaining_budget: 50000 }
│
Step 4: Finance Approval (USER ACTION)
├─ Receives: ALL context (including manager's decision and budget info)
├─ User Sees: Configured subset
├─ User Adds: { finance_decision: 'approve' }
└─ Context Now: { ...all previous context... }
│
Complete: Final context contains ENTIRE journey
```

### 2.2 Context Merging in Code

```php
// In WorkflowEngine::executeStep()

private function executeStep(WorkflowExecution $execution, WorkflowStep $step, array $context): array
{
    // Step receives current context
    $log->markAsStarted($context);
    
    // Execute the step (may be user action, API call, etc.)
    $output = $this->stepExecutor->execute($step, $context);
    
    // Merge step output into context for next steps
    $context = array_merge($context, $output);
    
    $log->markAsCompleted($output);
    
    // Pass enriched context to child steps
    foreach ($step->children()->enabled()->get() as $childStep) {
        $context = $this->executeStep($execution, $childStep, $context);
    }
    
    return $context;
}
```

---

## 3. Context Types

### 3.1 Structured Data Context

**What:** Key-value data that flows through the workflow

**Example:**

```json
{
  "requester_id": 123,
  "requester_name": "John Smith",
  "department": "IT",
  "po_number": "PO-2025-001",
  "supplier_name": "Acme Corp",
  "total_amount": 15250,
  "currency": "USD",
  "items": [
    {
      "sku": "LAPTOP-001",
      "description": "Dell Laptop",
      "quantity": 10,
      "unit_price": 1500,
      "line_total": 15000
    }
  ]
}
```

### 3.2 Document/File Context

**What:** Attached files that provide supporting evidence

**Examples:**
- Purchase requisition forms
- Supplier quotes
- Budget approval documents
- Contracts
- Technical specifications
- Photos/screenshots

**Storage:**

```php
// UserActionAttachment model
UserActionAttachment::create([
    'user_action_id' => $userAction->id,
    'file_name' => 'supplier_quote.pdf',
    'file_path' => 'user-actions/123/supplier_quote.pdf',
    'file_type' => 'application/pdf',
    'file_size' => 204800,
    'uploaded_by' => auth()->id(),
]);
```

### 3.3 Historical Context

**What:** Previous decisions and actions in the workflow

**Available via:**
- `WorkflowExecutionLog` - All previous steps
- `UserAction` history - Previous approvals/decisions
- Timeline view

**Example:**

```json
{
  "workflow_history": [
    {
      "step": "Manager Approval",
      "user": "Sarah Johnson",
      "decision": "Approved",
      "timestamp": "2025-12-25 10:30:00",
      "notes": "Within budget, approved"
    },
    {
      "step": "Budget Verification",
      "status": "Completed",
      "result": "Budget available: $50,000",
      "timestamp": "2025-12-25 10:31:00"
    }
  ]
}
```

### 3.4 Reference Context

**What:** Links to related records in your system

**Example:**

```json
{
  "related_records": {
    "original_requisition_id": 456,
    "related_po_ids": [789, 790],
    "supplier_id": 123,
    "contract_id": 555,
    "budget_line_item_id": 888
  }
}
```

### 3.5 Comments/Notes Context

**What:** Informal communication between workflow participants

**Implementation:**

```php
// Add comment support to UserAction
class UserActionComment extends Model
{
    protected $fillable = [
        'user_action_id',
        'workflow_execution_id',
        'user_id',
        'comment',
        'is_internal', // Hide from external users
        'created_at',
    ];
}
```

---

## 4. Displaying Context to Users

### 4.1 Configured Context Display

**In Step Configuration:**

```json
{
  "type": "user_action",
  "name": "Finance Director Approval",
  "configuration": {
    "action_type": "approval",
    "title": "Approve Purchase Order {{po_number}}",
    "description": "Please review this {{total_amount}} {{currency}} purchase request",
    
    "show_context": [
      "requester_name",
      "department",
      "po_number",
      "supplier_name",
      "items",
      "total_amount",
      "currency",
      "business_justification",
      "delivery_location",
      "manager_decision",
      "manager_notes",
      "budget_available",
      "remaining_budget"
    ],
    
    "context_sections": {
      "request_details": {
        "title": "Request Details",
        "fields": ["requester_name", "department", "po_number", "supplier_name"]
      },
      "financial": {
        "title": "Financial Information",
        "fields": ["total_amount", "currency", "budget_available", "remaining_budget"]
      },
      "items": {
        "title": "Line Items",
        "fields": ["items"],
        "format": "table"
      },
      "previous_approvals": {
        "title": "Previous Approvals",
        "fields": ["manager_decision", "manager_notes"],
        "show_user": true,
        "show_timestamp": true
      }
    },
    
    "show_documents": true,
    "show_timeline": true,
    "show_comments": true
  }
}
```

### 4.2 Context Display Component

```blade
{{-- resources/views/livewire/user-action-context-display.blade.php --}}

<div class="context-display">
    @foreach($contextSections as $sectionKey => $section)
        <div class="context-section mb-4">
            <h4>{{ $section['title'] }}</h4>
            
            @if($section['format'] === 'table')
                {{-- Table format for arrays --}}
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            @foreach(array_keys($contextData[$section['fields'][0]][0]) as $header)
                                <th>{{ Str::title(str_replace('_', ' ', $header)) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($contextData[$section['fields'][0]] as $row)
                            <tr>
                                @foreach($row as $value)
                                    <td>{{ $value }}</td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                {{-- Key-value format --}}
                <dl class="row">
                    @foreach($section['fields'] as $field)
                        @if(isset($contextData[$field]))
                            <dt class="col-sm-4">{{ Str::title(str_replace('_', ' ', $field)) }}:</dt>
                            <dd class="col-sm-8">
                                @if($section['show_user'] ?? false)
                                    <strong>{{ $contextData[$field] }}</strong>
                                    <br>
                                    <small class="text-muted">
                                        by {{ $contextData[$field . '_user_name'] ?? 'Unknown' }}
                                        @if($section['show_timestamp'] ?? false)
                                            on {{ $contextData[$field . '_timestamp'] ?? '' }}
                                        @endif
                                    </small>
                                @else
                                    {{ $this->formatValue($contextData[$field]) }}
                                @endif
                            </dd>
                        @endif
                    @endforeach
                </dl>
            @endif
        </div>
    @endforeach
    
    {{-- Documents Section --}}
    @if($showDocuments && $attachments->count() > 0)
        <div class="context-section mb-4">
            <h4>Supporting Documents</h4>
            <div class="document-list">
                @foreach($attachments as $attachment)
                    <div class="document-item">
                        <i class="icon-file-{{ $attachment->icon }} text-{{ $attachment->color }}"></i>
                        <span>{{ $attachment->file_name }}</span>
                        <span class="text-muted">({{ $attachment->file_size_human }})</span>
                        <a href="{{ route('user-actions.download', $attachment) }}" 
                           class="btn btn-sm btn-link" 
                           target="_blank">
                            <i class="icon-eye"></i> View
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
    
    {{-- Timeline Section --}}
    @if($showTimeline)
        <div class="context-section mb-4">
            <h4>Workflow Timeline</h4>
            <livewire:forgepulse::workflow-timeline 
                :execution="$userAction->workflowExecution"
                :compactMode="true"
                :highlightUserActions="true"
            />
        </div>
    @endif
    
    {{-- Comments Section --}}
    @if($showComments)
        <div class="context-section mb-4">
            <h4>Comments & Discussion</h4>
            <livewire:forgepulse::comments-thread 
                :userAction="$userAction"
            />
        </div>
    @endif
</div>
```

### 4.3 Complete Approval Page with Context

```blade
{{-- resources/views/approvals/show.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        {{-- Main Content --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">{{ $userAction->action_config['title'] }}</h2>
                    <p class="mb-0">
                        <small>{{ $userAction->workflowStep->workflow->name }}</small>
                    </p>
                </div>
                
                <div class="card-body">
                    {{-- Description --}}
                    @if($userAction->action_config['description'] ?? null)
                        <div class="alert alert-info">
                            {{ $userAction->action_config['description'] }}
                        </div>
                    @endif
                    
                    {{-- Context Display Component --}}
                    <livewire:forgepulse::user-action-context-display 
                        :userAction="$userAction"
                    />
                    
                    <hr class="my-4">
                    
                    {{-- Action Form --}}
                    <livewire:forgepulse::user-action-form 
                        :userAction="$userAction"
                        :showTitle="false"
                        :showContext="false"
                    />
                </div>
            </div>
        </div>
        
        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Quick Summary Card --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Quick Summary</h5>
                </div>
                <div class="card-body">
                    <dl class="mb-0">
                        <dt>Workflow:</dt>
                        <dd>{{ $userAction->workflowStep->workflow->name }}</dd>
                        
                        <dt>Current Step:</dt>
                        <dd>{{ $userAction->workflowStep->name }}</dd>
                        
                        <dt>Assigned:</dt>
                        <dd>{{ $userAction->assigned_at->diffForHumans() }}</dd>
                        
                        @if($userAction->due_at)
                            <dt>Due:</dt>
                            <dd class="{{ $userAction->due_at->isPast() ? 'text-danger' : '' }}">
                                {{ $userAction->due_at->format('M d, Y H:i') }}
                                ({{ $userAction->due_at->diffForHumans() }})
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
            
            {{-- Progress Card --}}
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Progress</h5>
                </div>
                <div class="card-body">
                    @php
                        $completedSteps = $userAction->workflowExecution->logs()
                            ->where('status', 'completed')
                            ->count();
                        $totalSteps = $userAction->workflowExecution->workflow->steps()->count();
                        $progress = $totalSteps > 0 ? ($completedSteps / $totalSteps) * 100 : 0;
                    @endphp
                    
                    <div class="progress mb-2">
                        <div class="progress-bar" 
                             role="progressbar" 
                             style="width: {{ $progress }}%"
                             aria-valuenow="{{ $progress }}" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            {{ round($progress) }}%
                        </div>
                    </div>
                    
                    <small class="text-muted">
                        {{ $completedSteps }} of {{ $totalSteps }} steps completed
                    </small>
                </div>
            </div>
            
            {{-- Previous Decisions Card --}}
            @php
                $previousActions = UserAction::where('workflow_execution_id', $userAction->workflow_execution_id)
                    ->where('id', '<', $userAction->id)
                    ->where('status', 'completed')
                    ->orderBy('completed_at', 'desc')
                    ->limit(3)
                    ->get();
            @endphp
            
            @if($previousActions->count() > 0)
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">Previous Decisions</h5>
                    </div>
                    <div class="card-body">
                        @foreach($previousActions as $prevAction)
                            <div class="previous-action mb-3">
                                <div class="d-flex align-items-start">
                                    <div class="mr-2">
                                        @if(($prevAction->response_data['decision'] ?? null) === 'approve')
                                            <i class="icon-check-circle text-success"></i>
                                        @elseif(($prevAction->response_data['decision'] ?? null) === 'reject')
                                            <i class="icon-times-circle text-danger"></i>
                                        @else
                                            <i class="icon-info-circle text-info"></i>
                                        @endif
                                    </div>
                                    <div class="flex-grow-1">
                                        <strong>{{ $prevAction->workflowStep->name }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            by {{ $prevAction->actualUser->name }}
                                            <br>
                                            {{ $prevAction->completed_at->format('M d, Y H:i') }}
                                        </small>
                                        @if($prevAction->response_notes)
                                            <p class="mt-2 mb-0 text-muted small">
                                                "{{ Str::limit($prevAction->response_notes, 100) }}"
                                            </p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            
            {{-- Actions Card --}}
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    @if($userAction->action_config['allow_delegation'] ?? true)
                        <button class="btn btn-secondary btn-block mb-2" 
                                onclick="Livewire.emit('openDelegationModal', {{ $userAction->id }})">
                            <i class="icon-user"></i> Delegate to Someone
                        </button>
                    @endif
                    
                    <a href="{{ route('workflows.show', $userAction->workflowExecution->workflow) }}" 
                       class="btn btn-outline-secondary btn-block mb-2">
                        <i class="icon-sitemap"></i> View Workflow
                    </a>
                    
                    <button class="btn btn-outline-secondary btn-block" 
                            onclick="Livewire.emit('openCommentModal', {{ $userAction->id }})">
                        <i class="icon-comment"></i> Add Comment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

---

## 5. Context Access Control

### 5.1 Sensitive Data Masking

**Problem:** Some context data should be visible only to certain roles

**Solution:**

```php
// In WorkflowStep configuration
{
  "show_context": ["requester_name", "amount", "bank_account"],
  
  "context_access_rules": {
    "bank_account": {
      "visible_to_roles": ["finance_director", "cfo"],
      "mask_for_others": "****6789"
    },
    "credit_score": {
      "visible_to_roles": ["credit_controller", "finance_director"],
      "hide_for_others": true
    },
    "internal_notes": {
      "visible_to_roles": ["manager", "director"],
      "hide_for_others": true
    }
  }
}
```

**Implementation:**

```php
// app/Services/ContextAccessControlService.php

class ContextAccessControlService
{
    public function filterContextForUser(array $context, User $user, array $accessRules): array
    {
        $filteredContext = $context;
        
        foreach ($accessRules as $field => $rule) {
            if (!isset($context[$field])) {
                continue;
            }
            
            $visibleToRoles = $rule['visible_to_roles'] ?? [];
            $userHasAccess = $user->hasAnyRole($visibleToRoles);
            
            if (!$userHasAccess) {
                if ($rule['hide_for_others'] ?? false) {
                    unset($filteredContext[$field]);
                } elseif ($rule['mask_for_others'] ?? null) {
                    $filteredContext[$field] = $rule['mask_for_others'];
                }
            }
        }
        
        return $filteredContext;
    }
}
```

### 5.2 Department-Based Access

```php
// Show different context based on department
{
  "context_access_rules": {
    "budget_details": {
      "visible_to_departments": ["finance", "accounting"],
      "hide_for_others": true
    },
    "technical_specs": {
      "visible_to_departments": ["it", "engineering"],
      "hide_for_others": true
    }
  }
}
```

### 5.3 Progressive Disclosure

**Show more context as workflow progresses:**

```php
{
  "show_context": ["basic_info"],
  
  "conditional_context": {
    "supplier_details": {
      "show_if": "step_number >= 3"
    },
    "credit_assessment": {
      "show_if": "credit_check_completed == true"
    },
    "previous_approvals": {
      "show_if": "has_previous_approvals == true"
    }
  }
}
```

---

## 6. Real-World Examples

### 6.1 Purchase Order Approval Context

```json
{
  "action_type": "approval",
  "title": "Approve Purchase Order {{po_number}}",
  
  "show_context": [
    "requester_name",
    "requester_department",
    "po_number",
    "supplier_name",
    "items",
    "subtotal",
    "tax",
    "total_amount",
    "currency",
    "business_justification",
    "delivery_location",
    "requested_delivery_date",
    "budget_available",
    "remaining_budget",
    "contract_exists",
    "manager_approval"
  ],
  
  "context_sections": {
    "requester": {
      "title": "Requested By",
      "icon": "user",
      "fields": ["requester_name", "requester_department", "requester_email"]
    },
    
    "order_details": {
      "title": "Order Details",
      "icon": "shopping-cart",
      "fields": ["po_number", "supplier_name", "requested_delivery_date", "delivery_location"]
    },
    
    "financial": {
      "title": "Financial Summary",
      "icon": "dollar-sign",
      "fields": ["subtotal", "tax", "total_amount", "currency"],
      "format": "financial",
      "highlight_total": true
    },
    
    "items": {
      "title": "Line Items",
      "icon": "list",
      "fields": ["items"],
      "format": "table",
      "columns": ["sku", "description", "quantity", "unit_price", "line_total"]
    },
    
    "budget": {
      "title": "Budget Verification",
      "icon": "calculator",
      "fields": ["budget_available", "remaining_budget", "cost_center"],
      "alert_if_low": true,
      "threshold": 10000
    },
    
    "compliance": {
      "title": "Compliance Checks",
      "icon": "check-square",
      "fields": ["contract_exists", "supplier_approved", "within_authority_limit"],
      "format": "checklist"
    },
    
    "previous_approvals": {
      "title": "Approval History",
      "icon": "history",
      "show_timeline": true,
      "show_decisions": true,
      "show_notes": true
    },
    
    "justification": {
      "title": "Business Justification",
      "icon": "file-text",
      "fields": ["business_justification"],
      "format": "markdown"
    }
  },
  
  "show_documents": true,
  "show_timeline": true,
  "show_comments": true,
  
  "context_warnings": [
    {
      "condition": "total_amount > remaining_budget",
      "message": "⚠️ This purchase exceeds remaining budget for this cost center",
      "severity": "warning"
    },
    {
      "condition": "contract_exists == false",
      "message": "⚠️ No contract exists with this supplier",
      "severity": "info"
    },
    {
      "condition": "total_amount > authority_limit",
      "message": "⚠️ This amount exceeds your approval authority",
      "severity": "error"
    }
  ],
  
  "related_links": [
    {
      "label": "View Supplier Profile",
      "url": "/suppliers/{{supplier_id}}"
    },
    {
      "label": "View Original Requisition",
      "url": "/requisitions/{{requisition_id}}"
    },
    {
      "label": "View Budget Details",
      "url": "/budgets/{{cost_center}}"
    }
  ]
}
```

**What the Approver Sees:**

```html
<div class="approval-context">
    <!-- Requester Section -->
    <div class="context-section">
        <h4><i class="icon-user"></i> Requested By</h4>
        <dl class="row">
            <dt class="col-sm-3">Name:</dt>
            <dd class="col-sm-9">John Smith</dd>
            
            <dt class="col-sm-3">Department:</dt>
            <dd class="col-sm-9">IT</dd>
            
            <dt class="col-sm-3">Email:</dt>
            <dd class="col-sm-9">john.smith@company.com</dd>
        </dl>
    </div>
    
    <!-- Order Details -->
    <div class="context-section">
        <h4><i class="icon-shopping-cart"></i> Order Details</h4>
        <dl class="row">
            <dt class="col-sm-3">PO Number:</dt>
            <dd class="col-sm-9"><strong>PO-2025-001</strong></dd>
            
            <dt class="col-sm-3">Supplier:</dt>
            <dd class="col-sm-9">Acme Corp <a href="/suppliers/123" class="btn btn-sm btn-link">View Profile</a></dd>
            
            <dt class="col-sm-3">Delivery Date:</dt>
            <dd class="col-sm-9">January 15, 2026</dd>
            
            <dt class="col-sm-3">Location:</dt>
            <dd class="col-sm-9">HQ - Building A</dd>
        </dl>
    </div>
    
    <!-- Financial Summary with Highlight -->
    <div class="context-section financial-highlight">
        <h4><i class="icon-dollar-sign"></i> Financial Summary</h4>
        <table class="table table-sm">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right">$15,000.00</td>
            </tr>
            <tr>
                <td>Tax (10%):</td>
                <td class="text-right">$1,500.00</td>
            </tr>
            <tr class="total-row">
                <th>Total:</th>
                <th class="text-right">$16,500.00 USD</th>
            </tr>
        </table>
    </div>
    
    <!-- Line Items Table -->
    <div class="context-section">
        <h4><i class="icon-list"></i> Line Items</h4>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>SKU</th>
                    <th>Description</th>
                    <th class="text-center">Qty</th>
                    <th class="text-right">Unit Price</th>
                    <th class="text-right">Total</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>LAPTOP-001</td>
                    <td>Dell Latitude 5520 Laptop</td>
                    <td class="text-center">10</td>
                    <td class="text-right">$1,500.00</td>
                    <td class="text-right">$15,000.00</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <!-- Budget Warning -->
    <div class="alert alert-warning">
        <i class="icon-alert-triangle"></i>
        <strong>Budget Note:</strong> This purchase will use $16,500 of the $50,000 remaining budget for IT department.
    </div>
    
    <!-- Compliance Checklist -->
    <div class="context-section">
        <h4><i class="icon-check-square"></i> Compliance Checks</h4>
        <div class="checklist-display">
            <div class="check-item checked">
                <i class="icon-check-circle text-success"></i>
                <span>Contract exists with supplier</span>
            </div>
            <div class="check-item checked">
                <i class="icon-check-circle text-success"></i>
                <span>Supplier is approved</span>
            </div>
            <div class="check-item checked">
                <i class="icon-check-circle text-success"></i>
                <span>Within approval authority limit</span>
            </div>
        </div>
    </div>
    
    <!-- Previous Approvals -->
    <div class="context-section">
        <h4><i class="icon-history"></i> Approval History</h4>
        <div class="approval-timeline">
            <div class="timeline-item approved">
                <div class="timeline-badge bg-success">
                    <i class="icon-check"></i>
                </div>
                <div class="timeline-content">
                    <strong>Manager Approval</strong>
                    <br>
                    <small class="text-muted">
                        Approved by Sarah Johnson on Dec 25, 2025 at 10:30 AM
                    </small>
                    <p class="mt-2 mb-0">
                        <em>"Approved - necessary for Q1 projects"</em>
                    </p>
                </div>
            </div>
            
            <div class="timeline-item completed">
                <div class="timeline-badge bg-info">
                    <i class="icon-check"></i>
                </div>
                <div class="timeline-content">
                    <strong>Budget Verification</strong>
                    <br>
                    <small class="text-muted">
                        Completed automatically on Dec 25, 2025 at 10:31 AM
                    </small>
                    <p class="mt-2 mb-0">
                        Budget available: $50,000 | After purchase: $33,500
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Business Justification -->
    <div class="context-section">
        <h4><i class="icon-file-text"></i> Business Justification</h4>
        <div class="well">
            <p>
                We need to replace 10 aging laptops for the development team. 
                Current laptops are 4+ years old and causing productivity issues 
                with slow compile times and frequent crashes. New laptops will 
                improve developer productivity by an estimated 20% and support 
                our Q1 project deadlines.
            </p>
        </div>
    </div>
    
    <!-- Attached Documents -->
    <div class="context-section">
        <h4><i class="icon-paperclip"></i> Supporting Documents</h4>
        <div class="document-list">
            <div class="document-item">
                <i class="icon-file-pdf text-danger"></i>
                <span>Supplier_Quote_Acme_Corp.pdf</span>
                <span class="badge badge-secondary">2.4 MB</span>
                <a href="/download/doc1" class="btn btn-sm btn-primary" target="_blank">
                    <i class="icon-eye"></i> View
                </a>
            </div>
            <div class="document-item">
                <i class="icon-file-pdf text-danger"></i>
                <span>Technical_Specifications.pdf</span>
                <span class="badge badge-secondary">1.2 MB</span>
                <a href="/download/doc2" class="btn btn-sm btn-primary" target="_blank">
                    <i class="icon-eye"></i> View
                </a>
            </div>
        </div>
    </div>
    
    <!-- Related Links -->
    <div class="context-section">
        <h4><i class="icon-link"></i> Related Information</h4>
        <div class="btn-group-vertical btn-block">
            <a href="/suppliers/123" class="btn btn-outline-primary" target="_blank">
                View Supplier Profile →
            </a>
            <a href="/requisitions/456" class="btn btn-outline-primary" target="_blank">
                View Original Requisition →
            </a>
            <a href="/budgets/IT" class="btn btn-outline-primary" target="_blank">
                View IT Budget Details →
            </a>
        </div>
    </div>
</div>
```

---

## 7. Best Practices

### 7.1 Context Organization Principles

#### ✅ DO:

1. **Group Related Information**
   ```json
   {
     "context_sections": {
       "financial": ["amount", "tax", "total"],
       "requester": ["name", "department", "email"],
       "items": ["line_items"]
     }
   }
   ```

2. **Show Most Important First**
   - Financial impact at top for finance approvers
   - Risk/compliance info prominent for compliance officers
   - Technical details first for technical reviewers

3. **Use Progressive Disclosure**
   - Summary view by default
   - Expandable sections for details
   - "Show more" for less critical info

4. **Provide Navigation Aids**
   - Table of contents for long contexts
   - Jump links to sections
   - Sticky header with key info

5. **Highlight Changes**
   - Show what changed from previous step
   - Mark new information added since last review
   - Track edits and amendments

#### ❌ DON'T:

1. **Don't Overload with Raw Data**
   ```json
   // Bad: Raw technical IDs
   {
     "show_context": ["id", "user_id", "workflow_id", "step_id", "execution_id"]
   }
   
   // Good: Human-readable info
   {
     "show_context": ["requester_name", "workflow_name", "current_step"]
   }
   ```

2. **Don't Show Sensitive Data Unnecessarily**
   - Mask bank accounts, SSN, passwords
   - Hide internal notes from external users
   - Redact confidential information

3. **Don't Repeat Information**
   - If shown in summary, don't repeat in detail
   - Consolidate duplicate fields

### 7.2 Context Quality Checklist

Before deploying a workflow with user actions, verify:

- [ ] Users have all information needed to make decision
- [ ] Context is organized logically (not dumped as JSON)
- [ ] Previous approvals/decisions are visible
- [ ] Supporting documents are easily accessible
- [ ] Financial impacts are clearly shown
- [ ] Related records are linked (not just IDs)
- [ ] Timeline shows what happened when
- [ ] Warnings/alerts for edge cases are shown
- [ ] Sensitive data is properly masked
- [ ] Mobile view is readable and complete

### 7.3 Testing Context Visibility

```php
// Test that context is properly displayed
public function test_approver_sees_complete_context()
{
    // Create workflow execution with context
    $execution = $workflow->execute([
        'requester_name' => 'John Smith',
        'amount' => 15000,
        'items' => [...],
    ]);
    
    // Get user action for approver
    $userAction = UserAction::where('workflow_execution_id', $execution->id)
        ->first();
    
    // Impersonate approver
    $this->actingAs($approver);
    
    // Visit approval page
    $response = $this->get(route('approvals.show', $userAction));
    
    // Assert context is visible
    $response->assertSee('John Smith');
    $response->assertSee('$15,000');
    $response->assertSee('Business Justification');
    
    // Assert sensitive data is masked
    $response->assertDontSee('full-ssn-here');
    $response->assertSee('****6789'); // Masked version
}
```

---

## 8. Summary

### Context Flow Summary

```
1. Workflow starts with initial context
2. Each step receives ALL accumulated context
3. Each step can ADD to context
4. User action steps FILTER what user sees (via show_context config)
5. User provides response → ADDED to context
6. Next steps receive enriched context
7. Final context contains complete journey
```

### Key Takeaways

1. **Context is cumulative** - it grows as workflow progresses
2. **Filter for relevance** - don't show everything to everyone
3. **Structure matters** - organize in logical sections
4. **History is context** - previous decisions inform current ones
5. **Documents are context** - make files easily accessible
6. **Access control** - mask sensitive data appropriately
7. **Test thoroughly** - verify users have what they need

---

**End of Document**

Approvers now have complete, organized, and relevant context to make informed decisions!
