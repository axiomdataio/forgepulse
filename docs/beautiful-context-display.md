# Beautiful Context Display: Best Practices & Examples

**Version:** 1.0  
**Date:** 2025-12-25  
**Context:** Making workflow context visually appealing and user-friendly

---

## Executive Summary

Raw data is ugly and confusing. This guide shows you how to transform workflow context into beautiful, scannable, decision-enabling displays that users actually appreciate.

---

## Table of Contents

1. [The Problem with Raw Context](#1-the-problem-with-raw-context)
2. [Context Display Patterns](#2-context-display-patterns)
3. [Visual Hierarchy](#3-visual-hierarchy)
4. [Component Library](#4-component-library)
5. [Real-World Examples](#5-real-world-examples)
6. [Mobile Considerations](#6-mobile-considerations)
7. [Implementation Guide](#7-implementation-guide)

---

## 1. The Problem with Raw Context

### 1.1 What NOT to Do

❌ **Raw Data Dump (BAD):**

```html
<div class="context">
    requester_id: 123
    requester_name: John Smith
    requester_department: IT
    po_number: PO-2025-001
    items: [{"sku":"LAPTOP-001","qty":10,"price":1500}]
    total_amount: 15000
    currency: USD
    manager_decision: approve
    manager_notes: Looks good
    budget_available: true
    ...
</div>
```

**Problems:**
- 😵 Overwhelming wall of text
- 🤔 No visual hierarchy
- 😰 Hard to scan quickly
- 😤 Important info gets lost
- 🚫 Ugly and unprofessional

### 1.2 User Expectations

Users need to:
- ✅ **Scan quickly** - Find key info in 2 seconds
- ✅ **Understand easily** - No mental translation needed
- ✅ **Feel confident** - Clear, organized information
- ✅ **Make decisions** - All relevant data accessible

---

## 2. Context Display Patterns

### 2.1 Pattern 1: Card Layout (Best for Overview)

```html
┌──────────────────────────────────────────────────┐
│  📋 Request Details                      [Edit]  │
├──────────────────────────────────────────────────┤
│  Requested By:    John Smith (IT)                │
│  Request Date:    Dec 25, 2025                   │
│  PO Number:       PO-2025-001                    │
│  Supplier:        Acme Corp                      │
│  Total Amount:    $16,500 USD                    │
└──────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────┐
│  💰 Financial Summary                            │
├──────────────────────────────────────────────────┤
│  Subtotal:        $15,000.00                     │
│  Tax (10%):       $ 1,500.00                     │
│  ──────────────────────────────                  │
│  Total:           $16,500.00                     │
│                                                   │
│  ✓ Budget Available    Remaining: $33,500       │
└──────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────┐
│  ✅ Previous Approvals                           │
├──────────────────────────────────────────────────┤
│  ✓ Manager Approval                              │
│    Approved by Sarah Johnson                     │
│    Dec 25, 2025 at 10:30 AM                      │
│    "Approved - within budget"                    │
└──────────────────────────────────────────────────┘
```

**Code:**

```blade
{{-- Card Layout Pattern --}}
<div class="context-cards">
    {{-- Card 1: Request Details --}}
    <div class="context-card">
        <div class="card-header">
            <div class="card-icon">📋</div>
            <h4>Request Details</h4>
        </div>
        <div class="card-body">
            <dl class="context-grid">
                <dt>Requested By:</dt>
                <dd>{{ $context['requester_name'] }} ({{ $context['requester_department'] }})</dd>
                
                <dt>Request Date:</dt>
                <dd>{{ $context['request_date']->format('M d, Y') }}</dd>
                
                <dt>PO Number:</dt>
                <dd><strong>{{ $context['po_number'] }}</strong></dd>
                
                <dt>Supplier:</dt>
                <dd>
                    {{ $context['supplier_name'] }}
                    <a href="/suppliers/{{ $context['supplier_id'] }}" class="btn-link">View Profile →</a>
                </dd>
                
                <dt>Total Amount:</dt>
                <dd class="amount-highlight">${{ number_format($context['total_amount'], 2) }} {{ $context['currency'] }}</dd>
            </dl>
        </div>
    </div>
    
    {{-- More cards... --}}
</div>

<style>
.context-cards {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1.5rem;
}

.context-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow: hidden;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.card-icon {
    font-size: 1.5rem;
}

.card-header h4 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
}

.card-body {
    padding: 1.25rem;
}

.context-grid {
    display: grid;
    grid-template-columns: 140px 1fr;
    gap: 0.75rem;
    margin: 0;
}

.context-grid dt {
    font-weight: 500;
    color: #6b7280;
}

.context-grid dd {
    margin: 0;
    color: #111827;
}

.amount-highlight {
    font-size: 1.25rem;
    font-weight: 600;
    color: #059669;
}
</style>
```

### 2.2 Pattern 2: Timeline (Best for History)

```html
┌──────────────────────────────────────────────────┐
│  🕒 Workflow Timeline                            │
├──────────────────────────────────────────────────┤
│                                                   │
│    ● ✓ Submitted                                 │
│    │   by John Smith                             │
│    │   Dec 25, 2025 at 9:00 AM                   │
│    │                                              │
│    ● ✓ Manager Approval                          │
│    │   Approved by Sarah Johnson                 │
│    │   Dec 25, 2025 at 10:30 AM                  │
│    │   "Looks good - approved"                   │
│    │                                              │
│    ● ✓ Budget Check                              │
│    │   Verified by System                        │
│    │   Dec 25, 2025 at 10:31 AM                  │
│    │   Budget Available: $50,000                 │
│    │                                              │
│    ● → Finance Director Approval (You)           │
│        Waiting for your decision                 │
│                                                   │
└──────────────────────────────────────────────────┘
```

**Code:**

```blade
<div class="timeline-card">
    <div class="card-header">
        <div class="card-icon">🕒</div>
        <h4>Workflow Timeline</h4>
    </div>
    <div class="card-body">
        <div class="timeline">
            @foreach($workflowHistory as $event)
                <div class="timeline-item {{ $event->status }}">
                    <div class="timeline-marker">
                        @if($event->status === 'completed')
                            <i class="icon-check"></i>
                        @elseif($event->status === 'current')
                            <i class="icon-arrow-right"></i>
                        @else
                            <i class="icon-circle"></i>
                        @endif
                    </div>
                    <div class="timeline-content">
                        <div class="timeline-title">{{ $event->step_name }}</div>
                        <div class="timeline-meta">
                            @if($event->user)
                                <span class="timeline-user">
                                    <img src="{{ $event->user->avatar }}" class="avatar-sm" />
                                    {{ $event->decision_text }} by {{ $event->user->name }}
                                </span>
                            @endif
                            <span class="timeline-date">{{ $event->completed_at->format('M d, Y \a\t g:i A') }}</span>
                        </div>
                        @if($event->notes)
                            <div class="timeline-notes">"{{ $event->notes }}"</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.625rem;
    top: 0.5rem;
    bottom: 0.5rem;
    width: 2px;
    background: #e5e7eb;
}

.timeline-item {
    position: relative;
    padding-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -1.5rem;
    top: 0.25rem;
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: white;
    border: 2px solid #e5e7eb;
}

.timeline-item.completed .timeline-marker {
    background: #10b981;
    border-color: #10b981;
    color: white;
}

.timeline-item.current .timeline-marker {
    background: #3b82f6;
    border-color: #3b82f6;
    color: white;
    animation: pulse 2s infinite;
}

.timeline-title {
    font-weight: 600;
    font-size: 1rem;
    color: #111827;
    margin-bottom: 0.25rem;
}

.timeline-meta {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 0.875rem;
    color: #6b7280;
    margin-bottom: 0.5rem;
}

.timeline-user {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.avatar-sm {
    width: 1.5rem;
    height: 1.5rem;
    border-radius: 50%;
}

.timeline-notes {
    margin-top: 0.5rem;
    padding: 0.75rem;
    background: #f9fafb;
    border-left: 3px solid #3b82f6;
    font-style: italic;
    color: #4b5563;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>
```

### 2.3 Pattern 3: Data Table (Best for Line Items)

```html
┌──────────────────────────────────────────────────────────────────┐
│  📦 Order Items                                         3 items  │
├──────────────────────────────────────────────────────────────────┤
│                                                                   │
│  SKU          Description              Qty    Price      Total   │
│  ────────────────────────────────────────────────────────────── │
│  LAPTOP-001   Dell Latitude 5520       10    $1,500   $15,000   │
│  MOUSE-001    Wireless Mouse           10    $   25   $   250   │
│  KB-001       Mechanical Keyboard      10    $   75   $   750   │
│  ────────────────────────────────────────────────────────────── │
│                                      Subtotal:        $16,000    │
│                                      Tax (10%):       $ 1,600    │
│                                      Total:           $17,600    │
│                                                                   │
└──────────────────────────────────────────────────────────────────┘
```

**Code:**

```blade
<div class="context-card">
    <div class="card-header">
        <div class="card-icon">📦</div>
        <h4>Order Items</h4>
        <div class="card-badge">{{ count($context['items']) }} items</div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="items-table">
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
                    @foreach($context['items'] as $item)
                        <tr>
                            <td class="font-mono">{{ $item['sku'] }}</td>
                            <td>{{ $item['description'] }}</td>
                            <td class="text-center">{{ $item['quantity'] }}</td>
                            <td class="text-right">${{ number_format($item['unit_price'], 2) }}</td>
                            <td class="text-right font-semibold">${{ number_format($item['line_total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="subtotal-row">
                        <td colspan="4" class="text-right">Subtotal:</td>
                        <td class="text-right">${{ number_format($context['subtotal'], 2) }}</td>
                    </tr>
                    <tr class="tax-row">
                        <td colspan="4" class="text-right">Tax ({{ $context['tax_rate'] * 100 }}%):</td>
                        <td class="text-right">${{ number_format($context['tax_amount'], 2) }}</td>
                    </tr>
                    <tr class="total-row">
                        <td colspan="4" class="text-right"><strong>Total:</strong></td>
                        <td class="text-right"><strong>${{ number_format($context['grand_total'], 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<style>
.items-table {
    width: 100%;
    border-collapse: collapse;
}

.items-table thead th {
    padding: 0.75rem 1rem;
    background: #f9fafb;
    font-size: 0.875rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    border-bottom: 2px solid #e5e7eb;
}

.items-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid #f3f4f6;
}

.items-table tbody tr:hover {
    background: #f9fafb;
}

.items-table tfoot td {
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
}

.total-row td {
    padding-top: 1rem;
    border-top: 2px solid #e5e7eb;
    font-size: 1.125rem;
    color: #059669;
}
</style>
```

### 2.4 Pattern 4: Status Indicators (Best for Checks)

```html
┌──────────────────────────────────────────────────┐
│  ✓ Compliance Checks                             │
├──────────────────────────────────────────────────┤
│                                                   │
│  ✓  Contract exists with supplier                │
│      Contract #12345 • Expires: Dec 2026         │
│                                                   │
│  ✓  Supplier is approved                         │
│      Status: Active • Rating: A                  │
│                                                   │
│  ✓  Within approval authority                    │
│      Your limit: $50,000 • Amount: $16,500      │
│                                                   │
│  ⚠  Budget partially available                   │
│      Available: $30,000 • Needed: $16,500       │
│      Will use 55% of remaining budget            │
│                                                   │
└──────────────────────────────────────────────────┘
```

**Code:**

```blade
<div class="context-card">
    <div class="card-header">
        <div class="card-icon">✓</div>
        <h4>Compliance Checks</h4>
    </div>
    <div class="card-body">
        <div class="status-list">
            {{-- Contract Check --}}
            <div class="status-item success">
                <div class="status-icon">
                    <i class="icon-check-circle"></i>
                </div>
                <div class="status-content">
                    <div class="status-title">Contract exists with supplier</div>
                    <div class="status-meta">
                        Contract #{{ $context['contract_id'] }} • 
                        Expires: {{ $context['contract_expires']->format('M Y') }}
                    </div>
                </div>
            </div>
            
            {{-- Supplier Check --}}
            <div class="status-item success">
                <div class="status-icon">
                    <i class="icon-check-circle"></i>
                </div>
                <div class="status-content">
                    <div class="status-title">Supplier is approved</div>
                    <div class="status-meta">
                        Status: Active • Rating: {{ $context['supplier_rating'] }}
                    </div>
                </div>
            </div>
            
            {{-- Authority Check --}}
            <div class="status-item success">
                <div class="status-icon">
                    <i class="icon-check-circle"></i>
                </div>
                <div class="status-content">
                    <div class="status-title">Within approval authority</div>
                    <div class="status-meta">
                        Your limit: ${{ number_format($context['approval_limit']) }} • 
                        Amount: ${{ number_format($context['grand_total']) }}
                    </div>
                </div>
            </div>
            
            {{-- Budget Warning --}}
            <div class="status-item warning">
                <div class="status-icon">
                    <i class="icon-alert-triangle"></i>
                </div>
                <div class="status-content">
                    <div class="status-title">Budget partially available</div>
                    <div class="status-meta">
                        Available: ${{ number_format($context['remaining_budget']) }} • 
                        Needed: ${{ number_format($context['grand_total']) }}
                    </div>
                    <div class="status-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 55%"></div>
                        </div>
                        <span class="progress-label">Will use 55% of remaining budget</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.status-list {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.status-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    border-radius: 8px;
    background: #f9fafb;
}

.status-item.success {
    background: #f0fdf4;
    border-left: 4px solid #10b981;
}

.status-item.warning {
    background: #fffbeb;
    border-left: 4px solid #f59e0b;
}

.status-item.error {
    background: #fef2f2;
    border-left: 4px solid #ef4444;
}

.status-icon {
    flex-shrink: 0;
    width: 2rem;
    height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.status-item.success .status-icon {
    color: #10b981;
}

.status-item.warning .status-icon {
    color: #f59e0b;
}

.status-item.error .status-icon {
    color: #ef4444;
}

.status-content {
    flex: 1;
}

.status-title {
    font-weight: 600;
    color: #111827;
    margin-bottom: 0.25rem;
}

.status-meta {
    font-size: 0.875rem;
    color: #6b7280;
}

.status-progress {
    margin-top: 0.75rem;
}

.progress-bar {
    height: 8px;
    background: #e5e7eb;
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 0.25rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #f59e0b, #d97706);
    transition: width 0.3s ease;
}

.progress-label {
    font-size: 0.75rem;
    color: #6b7280;
}
</style>
```

### 2.5 Pattern 5: Key Metrics (Best for Summary)

```html
┌────────────────────────────────────────────────────────────┐
│  Quick Summary                                              │
├────────────────────────────────────────────────────────────┤
│                                                             │
│   ┌─────────────┐  ┌─────────────┐  ┌─────────────┐      │
│   │  $16,500    │  │  2 of 3     │  │  1 day      │      │
│   │  Total      │  │  Approved   │  │  Remaining  │      │
│   └─────────────┘  └─────────────┘  └─────────────┘      │
│                                                             │
└────────────────────────────────────────────────────────────┘
```

**Code:**

```blade
<div class="metrics-row">
    <div class="metric-card primary">
        <div class="metric-value">${{ number_format($context['grand_total']) }}</div>
        <div class="metric-label">Total Amount</div>
        <div class="metric-sublabel">{{ $context['currency'] }}</div>
    </div>
    
    <div class="metric-card success">
        <div class="metric-value">{{ $approvedCount }} of {{ $totalApprovers }}</div>
        <div class="metric-label">Approved</div>
        <div class="metric-sublabel">{{ $totalApprovers - $approvedCount }} pending</div>
    </div>
    
    <div class="metric-card warning">
        <div class="metric-value">{{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }}</div>
        <div class="metric-label">Time Remaining</div>
        <div class="metric-sublabel">Due: {{ $dueDate->format('M d') }}</div>
    </div>
    
    <div class="metric-card info">
        <div class="metric-value">{{ $context['items_count'] }}</div>
        <div class="metric-label">Line Items</div>
        <div class="metric-sublabel">{{ $context['unique_skus'] }} unique SKUs</div>
    </div>
</div>

<style>
.metrics-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.metric-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    text-align: center;
    border-top: 4px solid #e5e7eb;
}

.metric-card.primary {
    border-top-color: #3b82f6;
}

.metric-card.success {
    border-top-color: #10b981;
}

.metric-card.warning {
    border-top-color: #f59e0b;
}

.metric-card.info {
    border-top-color: #6366f1;
}

.metric-value {
    font-size: 2rem;
    font-weight: 700;
    color: #111827;
    margin-bottom: 0.5rem;
}

.metric-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6b7280;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.metric-sublabel {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: 0.25rem;
}
</style>
```

---

## 3. Visual Hierarchy

### 3.1 Information Priority Levels

```
Level 1: Critical (Can't proceed without)
├─ Total amount
├─ Decision required
└─ Due date

Level 2: Important (Influences decision)
├─ Previous approvals
├─ Budget status
├─ Compliance checks
└─ Line items

Level 3: Supporting (Provides context)
├─ Requester info
├─ Supplier details
├─ Reference numbers
└─ Timestamps

Level 4: Optional (Nice to have)
├─ Internal notes
├─ Workflow metadata
└─ System information
```

**Implementation:**

```blade
{{-- Level 1: Hero section (large, prominent) --}}
<div class="hero-summary">
    <div class="hero-amount">${{ number_format($total) }}</div>
    <div class="hero-action">Approval Required</div>
    <div class="hero-deadline">Due in {{ $hours }} hours</div>
</div>

{{-- Level 2: Main content cards --}}
<div class="content-section">
    <div class="context-card">...</div>
    <div class="context-card">...</div>
</div>

{{-- Level 3: Details in accordion --}}
<details class="details-section">
    <summary>Additional Details</summary>
    <div class="details-content">...</div>
</details>

{{-- Level 4: Collapsible metadata --}}
<details class="metadata-section">
    <summary>System Information</summary>
    <div class="metadata-content">...</div>
</details>
```

---

## 4. Component Library

### 4.1 Context Display Component (Blade)

```php
// app/View/Components/ContextDisplay.php

namespace App\View\Components;

use Illuminate\View\Component;

class ContextDisplay extends Component
{
    public array $context;
    public array $config;
    
    public function __construct(array $context, array $config = [])
    {
        $this->context = $context;
        $this->config = array_merge([
            'layout' => 'cards', // cards, timeline, table
            'sections' => [],
            'showTimeline' => true,
            'showMetrics' => true,
        ], $config);
    }
    
    public function render()
    {
        return view('components.context-display');
    }
}
```

```blade
{{-- resources/views/components/context-display.blade.php --}}

<div class="context-display">
    @if($config['showMetrics'])
        <x-context-metrics :context="$context" />
    @endif
    
    @if($config['layout'] === 'cards')
        <div class="context-cards">
            @foreach($config['sections'] as $section)
                <x-context-card 
                    :title="$section['title']"
                    :icon="$section['icon']"
                    :fields="$section['fields']"
                    :context="$context"
                />
            @endforeach
        </div>
    @endif
    
    @if($config['showTimeline'])
        <x-workflow-timeline :execution="$execution" />
    @endif
</div>
```

### 4.2 Usage Example

```blade
{{-- In your approval page --}}

<x-context-display 
    :context="$userAction->getContextData()"
    :config="[
        'layout' => 'cards',
        'showMetrics' => true,
        'showTimeline' => true,
        'sections' => [
            [
                'title' => 'Request Details',
                'icon' => '📋',
                'fields' => ['requester_name', 'po_number', 'supplier_name', 'total_amount']
            ],
            [
                'title' => 'Financial Summary',
                'icon' => '💰',
                'fields' => ['subtotal', 'tax_amount', 'grand_total'],
                'format' => 'financial'
            ],
            [
                'title' => 'Line Items',
                'icon' => '📦',
                'fields' => ['items'],
                'format' => 'table'
            ],
        ]
    ]"
/>
```

**Result:** Beautiful, organized context display with zero custom code!

---

## 5. Real-World Examples

### 5.1 Purchase Order Approval (Complete)

```blade
<div class="approval-page">
    {{-- Hero Section --}}
    <div class="hero-section">
        <div class="hero-content">
            <h1>Approve Purchase Order {{ $context['po_number'] }}</h1>
            <div class="hero-meta">
                <span class="badge badge-warning">Pending Your Approval</span>
                <span class="text-muted">Requested by {{ $context['requester_name'] }}</span>
            </div>
        </div>
        <div class="hero-actions">
            <button class="btn btn-success btn-lg">
                <i class="icon-check"></i> Approve
            </button>
            <button class="btn btn-danger btn-lg">
                <i class="icon-times"></i> Reject
            </button>
        </div>
    </div>
    
    {{-- Quick Metrics --}}
    <div class="metrics-row">
        <div class="metric-card primary">
            <div class="metric-icon">💰</div>
            <div class="metric-value">${{ number_format($context['grand_total']) }}</div>
            <div class="metric-label">Total Amount</div>
        </div>
        
        <div class="metric-card {{ $context['budget_available'] ? 'success' : 'error' }}">
            <div class="metric-icon">{{ $context['budget_available'] ? '✓' : '⚠' }}</div>
            <div class="metric-value">${{ number_format($context['remaining_budget']) }}</div>
            <div class="metric-label">Budget Available</div>
        </div>
        
        <div class="metric-card info">
            <div class="metric-icon">📦</div>
            <div class="metric-value">{{ count($context['items']) }}</div>
            <div class="metric-label">Line Items</div>
        </div>
        
        <div class="metric-card warning">
            <div class="metric-icon">⏱</div>
            <div class="metric-value">{{ $hoursRemaining }}h</div>
            <div class="metric-label">Time to Decide</div>
        </div>
    </div>
    
    {{-- Main Content Grid --}}
    <div class="content-grid">
        {{-- Left Column --}}
        <div class="content-main">
            {{-- Request Details Card --}}
            <div class="context-card">
                <div class="card-header">
                    <div class="card-icon">📋</div>
                    <h4>Request Details</h4>
                </div>
                <div class="card-body">
                    <dl class="context-grid">
                        <dt>Requested By:</dt>
                        <dd>
                            <div class="user-info">
                                <img src="{{ $requester->avatar }}" class="avatar" />
                                <div>
                                    <div class="user-name">{{ $context['requester_name'] }}</div>
                                    <div class="user-dept">{{ $context['requester_department'] }}</div>
                                </div>
                            </div>
                        </dd>
                        
                        <dt>PO Number:</dt>
                        <dd><span class="badge badge-secondary">{{ $context['po_number'] }}</span></dd>
                        
                        <dt>Supplier:</dt>
                        <dd>
                            {{ $context['supplier_name'] }}
                            <a href="/suppliers/{{ $context['supplier_id'] }}" class="btn-link">
                                View Profile <i class="icon-external-link"></i>
                            </a>
                        </dd>
                        
                        <dt>Delivery:</dt>
                        <dd>
                            {{ $context['delivery_location'] }}<br>
                            <small class="text-muted">Requested: {{ $context['requested_delivery_date']->format('M d, Y') }}</small>
                        </dd>
                    </dl>
                </div>
            </div>
            
            {{-- Items Table Card --}}
            <div class="context-card">
                <div class="card-header">
                    <div class="card-icon">📦</div>
                    <h4>Order Items</h4>
                    <div class="card-badge">{{ count($context['items']) }} items</div>
                </div>
                <div class="card-body p-0">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th class="text-center">Qty</th>
                                <th class="text-right">Unit Price</th>
                                <th class="text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($context['items'] as $item)
                                <tr>
                                    <td>
                                        <div class="item-info">
                                            <div class="item-name">{{ $item['description'] }}</div>
                                            <div class="item-sku">SKU: {{ $item['sku'] }}</div>
                                        </div>
                                    </td>
                                    <td class="text-center">{{ $item['quantity'] }}</td>
                                    <td class="text-right">${{ number_format($item['unit_price'], 2) }}</td>
                                    <td class="text-right">${{ number_format($item['line_total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="text-right">Subtotal:</td>
                                <td class="text-right">${{ number_format($context['subtotal'], 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="3" class="text-right">Tax (10%):</td>
                                <td class="text-right">${{ number_format($context['tax_amount'], 2) }}</td>
                            </tr>
                            <tr class="total-row">
                                <td colspan="3" class="text-right"><strong>Total:</strong></td>
                                <td class="text-right"><strong>${{ number_format($context['grand_total'], 2) }}</strong></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            
            {{-- Business Justification Card --}}
            <div class="context-card">
                <div class="card-header">
                    <div class="card-icon">📝</div>
                    <h4>Business Justification</h4>
                </div>
                <div class="card-body">
                    <div class="justification-text">
                        {{ $context['business_justification'] }}
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Right Sidebar --}}
        <div class="content-sidebar">
            {{-- Timeline Card --}}
            <div class="context-card sticky">
                <div class="card-header">
                    <div class="card-icon">🕒</div>
                    <h4>Approval History</h4>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <div class="timeline-item completed">
                            <div class="timeline-marker">
                                <i class="icon-check"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Submitted</div>
                                <div class="timeline-user">
                                    <img src="{{ $requester->avatar }}" class="avatar-xs" />
                                    {{ $context['requester_name'] }}
                                </div>
                                <div class="timeline-date">Dec 25, 9:00 AM</div>
                            </div>
                        </div>
                        
                        <div class="timeline-item completed">
                            <div class="timeline-marker">
                                <i class="icon-check"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Manager Approved</div>
                                <div class="timeline-user">
                                    <img src="{{ $manager->avatar }}" class="avatar-xs" />
                                    {{ $context['manager_name'] }}
                                </div>
                                <div class="timeline-date">Dec 25, 10:30 AM</div>
                                <div class="timeline-notes">"Approved - within budget"</div>
                            </div>
                        </div>
                        
                        <div class="timeline-item current">
                            <div class="timeline-marker">
                                <i class="icon-arrow-right"></i>
                            </div>
                            <div class="timeline-content">
                                <div class="timeline-title">Finance Review</div>
                                <div class="timeline-user">
                                    <img src="{{ auth()->user()->avatar }}" class="avatar-xs" />
                                    You
                                </div>
                                <div class="timeline-date">Waiting...</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Compliance Card --}}
            <div class="context-card">
                <div class="card-header">
                    <div class="card-icon">✓</div>
                    <h4>Compliance</h4>
                </div>
                <div class="card-body">
                    <div class="compliance-checks">
                        <div class="check-item success">
                            <i class="icon-check-circle"></i>
                            <span>Contract exists</span>
                        </div>
                        <div class="check-item success">
                            <i class="icon-check-circle"></i>
                            <span>Supplier approved</span>
                        </div>
                        <div class="check-item warning">
                            <i class="icon-alert-triangle"></i>
                            <span>Budget at 55%</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
```

---

## 6. Mobile Considerations

### 6.1 Responsive Patterns

```css
/* Desktop: Side-by-side cards */
@media (min-width: 768px) {
    .content-grid {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 1.5rem;
    }
}

/* Mobile: Stacked cards */
@media (max-width: 767px) {
    .content-grid {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    
    .metric-card {
        padding: 1rem;
    }
    
    .metric-value {
        font-size: 1.5rem;
    }
    
    .context-grid {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .context-grid dt {
        font-weight: 600;
        margin-top: 0.75rem;
    }
}
```

### 6.2 Mobile-First Components

```html
<!-- Collapsible sections on mobile -->
<div class="mobile-sections">
    <details open>
        <summary>Request Details</summary>
        <div class="section-content">...</div>
    </details>
    
    <details>
        <summary>Order Items (3)</summary>
        <div class="section-content">...</div>
    </details>
    
    <details>
        <summary>Approval History</summary>
        <div class="section-content">...</div>
    </details>
</div>
```

---

## 7. Implementation Guide

### 7.1 Quick Start (2 hours)

**Step 1: Create Base Component**

```bash
php artisan make:component ContextDisplay
```

**Step 2: Add Styles**

```bash
# Copy provided CSS to resources/css/context-display.css
```

**Step 3: Use in Your View**

```blade
<x-context-display :context="$userAction->getContextData()" />
```

**Done!** You have beautiful context display.

### 7.2 Customization (1-2 days)

- Match your brand colors
- Add custom icons
- Create specialized card types
- Add animations

---

## Summary

### What Makes Context Display "Nice"?

✅ **Visual Hierarchy** - Important info stands out  
✅ **Organized Sections** - Related data grouped together  
✅ **Scannable** - Can find info in 2 seconds  
✅ **Professional** - Clean, modern design  
✅ **Responsive** - Works on all devices  
✅ **Contextual** - Shows what matters when  

### Quick Wins

1. Use **card layouts** instead of raw data
2. Add **icons** to sections
3. Use **colors** for status (green=good, yellow=warning, red=error)
4. Show **timeline** for history
5. Use **tables** for line items
6. Add **metrics** at the top
7. Make it **responsive**

---

**End of Document**

Yes, you absolutely need nice context display. The code is in `/workspace/docs/beautiful-context-display.md`!
