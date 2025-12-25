# Workflow Context Lifecycle: Trigger to Completion

**Version:** 1.0  
**Date:** 2025-12-25  
**Context:** Understanding when and how context is determined

---

## Executive Summary

Context flows through three stages:
1. **Initial Context** - Set when workflow is triggered
2. **Accumulated Context** - Grows as workflow executes
3. **Display Context** - Filtered for each user based on step configuration

---

## 1. Context at Workflow Trigger (Initial Context)

### 1.1 YES - Initial Context is Set at Trigger

**When you trigger a workflow, you provide the initial context:**

```php
// Example: User clicks "Request New Supplier" in your application
Route::post('/suppliers/onboarding/start', function (Request $request) {
    $workflow = Workflow::where('name', 'Supplier Onboarding')->first();
    
    // THIS is the initial context - determined at trigger time
    $execution = $workflow->execute([
        // Who triggered it
        'initiated_by' => auth()->id(),
        'initiated_by_name' => auth()->user()->name,
        'initiated_by_email' => auth()->user()->email,
        'initiated_by_department' => auth()->user()->department,
        
        // What they're requesting
        'supplier_contact_email' => $request->supplier_contact_email,
        'supplier_type' => $request->supplier_type,
        'reason_for_onboarding' => $request->reason,
        
        // When
        'requested_at' => now(),
        'urgency' => $request->urgency ?? 'normal',
        
        // Where (metadata)
        'initiated_from_ip' => $request->ip(),
        'initiated_from_location' => $request->header('X-Location'),
    ]);
    
    return response()->json(['execution_id' => $execution->id]);
});
```

**This initial context is stored in:**
- `workflow_executions.context` (JSON column)

### 1.2 Common Trigger Patterns

#### Pattern 1: User-Initiated (Manual Trigger)

```php
// User fills out a form in your UI, then triggers workflow
public function submitPurchaseRequisition(Request $request)
{
    $validated = $request->validate([
        'items' => 'required|array',
        'cost_center' => 'required',
        'justification' => 'required|min:50',
    ]);
    
    $workflow = Workflow::where('name', 'Purchase Requisition')->first();
    
    $execution = $workflow->execute([
        // From form submission
        'requester_id' => auth()->id(),
        'requester_name' => auth()->user()->name,
        'requester_department' => auth()->user()->department,
        'items' => $validated['items'],
        'cost_center' => $validated['cost_center'],
        'business_justification' => $validated['justification'],
        
        // Calculated
        'estimated_total' => $this->calculateTotal($validated['items']),
        'currency' => 'USD',
        
        // Auto-generated
        'requisition_number' => $this->generateReqNumber(),
        'submission_date' => now(),
    ]);
}
```

#### Pattern 2: Event-Driven (Automatic Trigger)

```php
// Workflow triggered automatically when something happens
class StockLevelListener
{
    public function handle(StockLevelLow $event)
    {
        $workflow = Workflow::where('name', 'Low Stock Alert')->first();
        
        $execution = $workflow->execute([
            // From the event
            'product_id' => $event->product->id,
            'product_name' => $event->product->name,
            'current_stock' => $event->currentStock,
            'reorder_level' => $event->product->reorder_level,
            'supplier_id' => $event->product->default_supplier_id,
            
            // Calculated
            'shortage_quantity' => $event->product->reorder_level - $event->currentStock,
            'estimated_cost' => $this->estimateReorderCost($event->product),
            
            // Context
            'triggered_by' => 'system',
            'trigger_reason' => 'stock_level_threshold',
            'triggered_at' => now(),
        ]);
    }
}
```

#### Pattern 3: Scheduled (Cron Trigger)

```php
// Scheduled command that triggers workflow
class MonthlyBudgetReview extends Command
{
    public function handle()
    {
        $departments = Department::all();
        
        foreach ($departments as $dept) {
            $workflow = Workflow::where('name', 'Monthly Budget Review')->first();
            
            $execution = $workflow->execute([
                'department_id' => $dept->id,
                'department_name' => $dept->name,
                'budget_allocated' => $dept->monthly_budget,
                'budget_spent' => $dept->getCurrentSpend(),
                'budget_remaining' => $dept->getRemainingBudget(),
                'review_period' => now()->format('Y-m'),
                
                // This is a scheduled trigger
                'triggered_by' => 'scheduler',
                'trigger_type' => 'monthly_review',
                'triggered_at' => now(),
            ]);
        }
    }
}
```

#### Pattern 4: API Trigger (External System)

```php
// External system triggers workflow via API
Route::post('/api/workflows/invoice-approval/trigger', function (Request $request) {
    $validated = $request->validate([
        'invoice_number' => 'required',
        'supplier_id' => 'required',
        'amount' => 'required|numeric',
        'invoice_data' => 'required|array',
    ]);
    
    $workflow = Workflow::where('name', 'Invoice Approval')->first();
    
    $execution = $workflow->execute([
        // From API request
        'invoice_number' => $validated['invoice_number'],
        'supplier_id' => $validated['supplier_id'],
        'total_amount' => $validated['amount'],
        'invoice_line_items' => $validated['invoice_data'],
        
        // API metadata
        'triggered_by' => 'api',
        'api_client' => $request->user()->name,
        'api_client_id' => $request->user()->id,
        'source_system' => $request->header('X-Source-System'),
    ]);
    
    return response()->json([
        'execution_id' => $execution->id,
        'status' => 'initiated'
    ]);
})->middleware('auth:sanctum');
```

---

## 2. Context Accumulation During Execution

### 2.1 NO - Context Grows as Workflow Runs

**Initial context is just the starting point. Each step can ADD to it:**

```php
// Initial context at trigger
$execution = $workflow->execute([
    'requester_id' => 1,
    'amount' => 15000,
]);

// After Step 1: Calculate Taxes
$context = [
    'requester_id' => 1,
    'amount' => 15000,
    'tax_amount' => 1500,        // ← ADDED by step
    'grand_total' => 16500,      // ← ADDED by step
];

// After Step 2: Check Budget
$context = [
    'requester_id' => 1,
    'amount' => 15000,
    'tax_amount' => 1500,
    'grand_total' => 16500,
    'budget_available' => true,   // ← ADDED by step
    'remaining_budget' => 33500,  // ← ADDED by step
];

// After Step 3: Manager Approval (User Action)
$context = [
    'requester_id' => 1,
    'amount' => 15000,
    'tax_amount' => 1500,
    'grand_total' => 16500,
    'budget_available' => true,
    'remaining_budget' => 33500,
    'manager_decision' => 'approve', // ← ADDED by user
    'manager_notes' => 'Approved',   // ← ADDED by user
    'manager_id' => 5,               // ← ADDED automatically
    'approved_at' => '2025-12-25',   // ← ADDED automatically
];
```

### 2.2 How Steps Add to Context

#### System Steps (Automatic)

```php
// Step: Calculate Total
class CalculateTotalAction
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $items = $context['items'];
        $subtotal = collect($items)->sum('line_total');
        $tax = $subtotal * 0.1;
        $total = $subtotal + $tax;
        
        // Return array is MERGED into context
        return [
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'grand_total' => $total,
            'tax_rate' => 0.1,
            'calculated_at' => now()->toDateTimeString(),
        ];
    }
}
```

#### User Action Steps (Manual)

```php
// When user submits approval decision
POST /api/forgepulse/user-actions/123/respond
{
  "response_data": {
    "decision": "approve",
    "conditions": "Approved pending budget confirmation"
  },
  "notes": "Looks good, proceeding"
}

// This becomes part of context:
$context = [
    ...existing_context...,
    'approval_decision' => 'approve',
    'approval_conditions' => 'Approved pending budget confirmation',
    'approval_notes' => 'Looks good, proceeding',
    'approved_by' => 'Sarah Johnson',
    'approved_by_id' => 5,
    'approved_at' => '2025-12-25 10:30:00',
];
```

#### API/Webhook Steps

```php
// Step: Check Supplier Credit
class CheckSupplierCreditWebhook
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $response = Http::post('https://credit-api.com/check', [
            'supplier_id' => $context['supplier_id'],
        ]);
        
        // API response merged into context
        return [
            'credit_score' => $response['score'],
            'credit_rating' => $response['rating'],
            'credit_limit' => $response['limit'],
            'credit_checked_at' => now()->toDateTimeString(),
            'credit_check_status' => 'completed',
        ];
    }
}
```

---

## 3. Display Context (What Users See)

### 3.1 Configured Per Step

**Each step's configuration determines what users see from the accumulated context:**

```php
// Step Configuration
$workflow->steps()->create([
    'name' => 'Finance Director Approval',
    'type' => StepType::USER_ACTION,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Purchase Request',
        
        // THIS determines what the Finance Director sees
        'show_context' => [
            // From initial trigger
            'requester_name',
            'requester_department',
            'requisition_number',
            
            // From accumulated steps
            'items',
            'subtotal',
            'tax_amount',
            'grand_total',
            
            // From previous user actions
            'manager_decision',
            'manager_notes',
            
            // From system checks
            'budget_available',
            'remaining_budget',
            'contract_exists',
        ],
    ],
]);
```

### 3.2 Different Users See Different Context

**Manager (Step 1) sees:**
```json
{
  "show_context": [
    "requester_name",
    "items",
    "estimated_total",
    "business_justification"
  ]
}
```

**Finance Director (Step 3) sees MORE:**
```json
{
  "show_context": [
    "requester_name",
    "items",
    "subtotal",
    "tax_amount",
    "grand_total",
    "manager_decision",        // ← Previous approval
    "manager_notes",           // ← Manager's comments
    "budget_available",        // ← System calculation
    "remaining_budget",        // ← System calculation
    "credit_score",            // ← API result
    "contract_exists"          // ← System check
  ]
}
```

---

## 4. Complete Context Flow Example

### Scenario: Purchase Order Workflow

```php
// ========================================
// TRIGGER: User submits purchase request
// ========================================
$execution = $workflow->execute([
    'requester_id' => 1,
    'requester_name' => 'John Smith',
    'requester_department' => 'IT',
    'items' => [
        ['sku' => 'LAPTOP-001', 'qty' => 10, 'price' => 1500]
    ],
    'supplier_id' => 123,
    'supplier_name' => 'Acme Corp',
    'business_justification' => 'Need laptops for new hires',
]);

// Context = { requester_id: 1, requester_name: 'John Smith', ... }


// ========================================
// STEP 1: Calculate Total (System)
// ========================================
// Handler receives: { requester_id: 1, requester_name: 'John Smith', items: [...], ... }
// Handler adds: { subtotal: 15000, tax: 1500, grand_total: 16500 }

// Context = { ...previous..., subtotal: 15000, tax: 1500, grand_total: 16500 }


// ========================================
// STEP 2: Check Budget (System)
// ========================================
// Handler receives: { ...all previous context... }
// Handler adds: { budget_available: true, remaining_budget: 50000 }

// Context = { ...previous..., budget_available: true, remaining_budget: 50000 }


// ========================================
// STEP 3: Manager Approval (USER ACTION)
// ========================================
// UserAction created with:
{
    "action_config": {
        "show_context": [
            "requester_name",      // From trigger
            "items",               // From trigger
            "grand_total",         // From Step 1
            "business_justification" // From trigger
        ]
    }
}

// Manager SEES (filtered):
{
    "requester_name": "John Smith",
    "items": [...],
    "grand_total": 16500,
    "business_justification": "Need laptops for new hires"
}

// Manager RESPONDS:
{
    "decision": "approve",
    "notes": "Approved - within budget"
}

// Context GROWS:
// Context = { ...previous..., manager_decision: 'approve', manager_notes: '...', approved_by: 5, approved_at: '...' }


// ========================================
// STEP 4: Check Contract (System)
// ========================================
// Handler receives: { ...all accumulated context... }
// Handler adds: { contract_exists: true, contract_id: 555, contract_expires: '2026-12-31' }

// Context = { ...previous..., contract_exists: true, contract_id: 555, ... }


// ========================================
// STEP 5: Finance Approval (USER ACTION)
// ========================================
// UserAction created with:
{
    "action_config": {
        "show_context": [
            "requester_name",           // From trigger
            "grand_total",              // From Step 1
            "budget_available",         // From Step 2
            "remaining_budget",         // From Step 2
            "manager_decision",         // From Step 3 (Manager)
            "manager_notes",            // From Step 3 (Manager)
            "contract_exists",          // From Step 4
        ]
    }
}

// Finance Director SEES (filtered but MORE than manager saw):
{
    "requester_name": "John Smith",
    "grand_total": 16500,
    "budget_available": true,
    "remaining_budget": 50000,
    "manager_decision": "approve",
    "manager_notes": "Approved - within budget",
    "contract_exists": true
}

// Finance Director has CONTEXT from:
// ✓ Initial trigger (who, what)
// ✓ System calculations (total, tax)
// ✓ Budget check results
// ✓ Manager's decision
// ✓ Contract verification


// ========================================
// STEP 6: Create PO (System)
// ========================================
// Handler receives: { ...ALL accumulated context... }
// Handler adds: { po_number: 'PO-2025-001', po_created_at: '...' }

// Final Context = { ...everything from entire journey... }
```

---

## 5. Key Principles

### Principle 1: Initial Context = Starting Point

```php
// You provide this at trigger time
$execution = $workflow->execute([
    'user_id' => 1,
    'amount' => 15000,
]);
```

✅ **Determined at trigger:** YES  
📝 **Can change later:** NO (but can be ADDED to)

### Principle 2: Context is Cumulative

```php
// Context GROWS as workflow progresses
Initial:  { a: 1, b: 2 }
+ Step 1: { c: 3 }           → { a: 1, b: 2, c: 3 }
+ Step 2: { d: 4 }           → { a: 1, b: 2, c: 3, d: 4 }
+ Step 3: { e: 5 }           → { a: 1, b: 2, c: 3, d: 4, e: 5 }
```

✅ **Always grows:** YES  
❌ **Can shrink:** NO (context is append-only)

### Principle 3: Display is Filtered

```php
// Full context
$fullContext = {
    'requester_id' => 1,
    'amount' => 15000,
    'tax' => 1500,
    'total' => 16500,
    'budget_ok' => true,
    'manager_approved' => true,
    'internal_note' => 'Check supplier history',
    'credit_score' => 720,
};

// User sees filtered version
$userSeesContext = {
    'requester_id' => 1,      // ✓ Shown
    'amount' => 15000,         // ✓ Shown
    'total' => 16500,          // ✓ Shown
    'manager_approved' => true // ✓ Shown
    // 'internal_note' is NOT shown (not in show_context)
    // 'credit_score' is NOT shown (not in show_context)
};
```

✅ **Filtered per step:** YES  
✅ **Can be different for each user:** YES  
✅ **Can hide sensitive data:** YES

---

## 6. Design Patterns

### Pattern 1: Rich Initial Context

**Best for:** Known requirements at trigger time

```php
// Provide everything upfront
$execution = $workflow->execute([
    // Core data
    'type' => 'purchase_order',
    'requester_id' => auth()->id(),
    
    // Detailed data
    'items' => $request->items,
    'supplier_id' => $request->supplier_id,
    'delivery_location' => $request->location,
    'urgency' => $request->urgency,
    
    // Business context
    'cost_center' => $request->cost_center,
    'project_code' => $request->project_code,
    'business_justification' => $request->justification,
    
    // Metadata
    'source_system' => 'erp',
    'reference_number' => $request->ref,
]);
```

✅ **Pros:** Complete context from start, no need to fetch later  
❌ **Cons:** Must know all requirements at trigger time

### Pattern 2: Minimal Initial + Progressive Enhancement

**Best for:** User-driven workflows where context is collected step-by-step

```php
// Start minimal
$execution = $workflow->execute([
    'requester_id' => auth()->id(),
    'workflow_type' => 'supplier_onboarding',
]);

// Step 1: User Action - Collect supplier info
// User provides: { company_name, tax_id, address, ... }
// Context grows: { requester_id, workflow_type, company_name, tax_id, ... }

// Step 2: User Action - Upload documents
// User provides: { documents: [...] }
// Context grows: { ...previous..., documents: [...] }

// Step 3: System - Verify information
// System adds: { verification_status: 'passed', verified_at: '...' }
// Context grows: { ...previous..., verification_status, verified_at, ... }
```

✅ **Pros:** Flexible, collect info as needed  
❌ **Cons:** More steps, longer workflow

### Pattern 3: Hybrid (Initial + API Enrichment)

**Best for:** Workflows with external data dependencies

```php
// Initial context
$execution = $workflow->execute([
    'order_id' => 123,
    'supplier_id' => 456,
]);

// Step 1: Fetch order details from API
// Adds: { order_details: {...}, items: [...], total: 15000 }

// Step 2: Fetch supplier details from API
// Adds: { supplier_details: {...}, credit_rating: 'A' }

// Step 3: User Action - Approve with FULL context
// User sees: order details + supplier details + credit rating
```

✅ **Pros:** Clean separation, fetch only when needed  
❌ **Cons:** Depends on external systems

---

## 7. Common Questions

### Q: Can I change initial context after workflow starts?

**A: No, but you can ADD to it.**

```php
// ❌ Cannot do this:
$execution->update(['context' => $newContext]);

// ✅ Can do this (add via steps):
// Each step merges its output into context
```

### Q: What if I forgot to include something in initial context?

**A: Add it in a subsequent step.**

```php
// Step: Enrich Context
class EnrichContextAction
{
    public function handle(WorkflowStep $step, array $context): array
    {
        // Fetch missing data
        $user = User::find($context['requester_id']);
        
        // Add to context
        return [
            'requester_email' => $user->email,
            'requester_phone' => $user->phone,
            'requester_manager_id' => $user->manager_id,
        ];
    }
}
```

### Q: Can different steps see different context?

**A: Yes! Via `show_context` configuration.**

```php
// Manager sees basic info
['show_context' => ['requester', 'amount', 'justification']]

// Finance sees everything including previous approvals
['show_context' => ['requester', 'amount', 'justification', 'manager_decision', 'budget_check']]
```

### Q: Is context stored anywhere?

**A: Yes, in multiple places:**

```sql
-- Initial context
workflow_executions.context

-- Step inputs/outputs
workflow_execution_logs.input
workflow_execution_logs.output

-- User responses
user_actions.response_data

-- Final context
workflow_executions.output
```

---

## 8. Summary

| Aspect | When Determined | Can Change | Where Stored |
|--------|----------------|-----------|--------------|
| **Initial Context** | At trigger time | No (but grows) | `workflow_executions.context` |
| **Accumulated Context** | During execution | Yes (grows) | Merged from steps |
| **Step Output** | Each step | N/A | `workflow_execution_logs.output` |
| **User Response** | User action | No | `user_actions.response_data` |
| **Display Context** | Step config | No | `workflow_steps.configuration` |
| **Final Context** | Workflow end | No | `workflow_executions.output` |

### Answer to Your Question

**"Is context decided based on the workflow trigger?"**

**Answer:**
- ✅ **Initial context** is decided at trigger time
- ❌ **Full context** is NOT - it grows during execution
- 🎯 **Display context** (what users see) is decided by each step's configuration

**Better way to think about it:**
1. Trigger provides **starting context**
2. Each step **adds to context**
3. Each user action shows **filtered context** based on configuration

---

**End of Document**
