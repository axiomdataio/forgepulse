# UserAction Engine in Procurement & Inventory Management

**Version:** 1.0  
**Date:** 2025-12-25  
**Context:** Practical Applications for Axiom Platform

---

## Executive Summary

This document outlines practical applications of the UserAction engine within a procurement and inventory management system, showing how workflows can automate and orchestrate business processes while maintaining proper controls and audit trails.

---

## Table of Contents

1. [Purchase Requisition to PO Workflow](#1-purchase-requisition-to-po-workflow)
2. [Supplier Onboarding & Approval](#2-supplier-onboarding--approval)
3. [Purchase Order Approval Workflow](#3-purchase-order-approval-workflow)
4. [Goods Receipt & Quality Check](#4-goods-receipt--quality-check)
5. [Invoice Matching & Payment Approval](#5-invoice-matching--payment-approval)
6. [Inventory Adjustment Approval](#6-inventory-adjustment-approval)
7. [Stock Transfer Request](#7-stock-transfer-request)
8. [Return Merchandise Authorization (RMA)](#8-return-merchandise-authorization-rma)
9. [Budget Request & Allocation](#9-budget-request--allocation)
10. [Contract Approval Workflow](#10-contract-approval-workflow)
11. [Supplier Performance Review](#11-supplier-performance-review)
12. [Emergency Purchase Request](#12-emergency-purchase-request)
13. [Inventory Count Reconciliation](#13-inventory-count-reconciliation)
14. [Price Change Approval](#14-price-change-approval)
15. [Backorder Resolution](#15-backorder-resolution)

---

## 1. Purchase Requisition to PO Workflow

### 1.1 Business Process

A user creates a purchase requisition → Manager approves → Procurement reviews → Budget verified → PO created

### 1.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Purchase Requisition to PO',
    'description' => 'Convert approved requisitions to purchase orders',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Requester submits requisition details
$workflow->steps()->create([
    'name' => 'Submit Requisition Details',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Create Purchase Requisition',
        'description' => 'Provide details for your purchase request',
        'users' => [['user_id' => '{{requester_id}}']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    [
                        'name' => 'items',
                        'type' => 'repeater',
                        'label' => 'Items',
                        'required' => true,
                        'fields' => [
                            ['name' => 'product_id', 'type' => 'select', 'label' => 'Product', 'required' => true],
                            ['name' => 'quantity', 'type' => 'number', 'label' => 'Quantity', 'required' => true],
                            ['name' => 'required_date', 'type' => 'date', 'label' => 'Required By', 'required' => true],
                        ]
                    ],
                    [
                        'name' => 'business_justification',
                        'type' => 'textarea',
                        'label' => 'Business Justification',
                        'required' => true,
                        'validation' => 'min:50'
                    ],
                    [
                        'name' => 'cost_center',
                        'type' => 'select',
                        'label' => 'Cost Center',
                        'options' => 'cost_centers', // Dynamic from DB
                        'required' => true
                    ],
                    [
                        'name' => 'preferred_supplier',
                        'type' => 'select',
                        'label' => 'Preferred Supplier',
                        'options' => 'suppliers',
                        'required' => false
                    ],
                    [
                        'name' => 'delivery_location',
                        'type' => 'select',
                        'label' => 'Delivery Location',
                        'options' => 'locations',
                        'required' => true
                    ]
                ]
            ]
        ],
        'is_required' => true,
    ],
]);

// Step 2: Auto-calculate estimated total
$workflow->steps()->create([
    'name' => 'Calculate Estimated Total',
    'type' => StepType::ACTION,
    'position' => 2,
    'configuration' => [
        'action_class' => \App\Actions\CalculateRequisitionTotal::class,
        'parameters' => [
            'items' => '{{items}}',
            'preferred_supplier' => '{{preferred_supplier}}',
        ],
    ],
]);

// Step 3: Manager approval (conditional based on amount)
$lowValueCheck = $workflow->steps()->create([
    'name' => 'Check Requisition Value',
    'type' => StepType::CONDITION,
    'position' => 3,
    'configuration' => [
        'field' => 'estimated_total',
        'operator' => '<=',
        'value' => 5000,
    ],
]);

// Path A: Low value - manager approval only
$workflow->steps()->create([
    'name' => 'Manager Approval (Low Value)',
    'type' => StepType::USER_ACTION,
    'position' => 4,
    'parent_step_id' => $lowValueCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Requisition {{requisition_number}}',
        'description' => 'Requisition for {{estimated_total}} {{currency}}',
        'users' => [['role' => 'department_manager', 'department' => '{{requester_department}}']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject', 'request_clarification'],
            'require_notes_on_reject' => true,
            'show_context' => ['items', 'business_justification', 'estimated_total'],
        ],
        'escalation_minutes' => 1440, // 24 hours
        'escalation_target' => ['role' => 'procurement_manager'],
        'reminder_minutes' => 480, // 8 hours
    ],
]);

// Path B: High value - sequential approvals
$highValueCheck = $workflow->steps()->create([
    'name' => 'High Value Check',
    'type' => StepType::CONDITION,
    'position' => 4,
    'configuration' => [
        'field' => 'estimated_total',
        'operator' => '>',
        'value' => 5000,
    ],
]);

$workflow->steps()->create([
    'name' => 'Multi-Level Approval Chain',
    'type' => StepType::USER_ACTION_SEQUENTIAL,
    'position' => 5,
    'parent_step_id' => $highValueCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'High-Value Requisition Approval',
        'action_chain' => [
            [
                'name' => 'Department Manager',
                'users' => [['role' => 'department_manager', 'department' => '{{requester_department}}']],
                'action_mode' => 'any',
            ],
            [
                'name' => 'Budget Holder',
                'users' => [['role' => 'budget_holder', 'cost_center' => '{{cost_center}}']],
                'action_mode' => 'any',
            ],
            [
                'name' => 'Finance Director',
                'users' => [['role' => 'finance_director']],
                'action_mode' => 'any',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'estimated_total', 'operator' => '>', 'value' => 25000]
                    ]
                ]
            ],
            [
                'name' => 'CFO',
                'users' => [['role' => 'cfo']],
                'action_mode' => 'any',
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'estimated_total', 'operator' => '>', 'value' => 100000]
                    ]
                ]
            ],
        ],
    ],
]);

// Step 4: Procurement review and supplier selection
$workflow->steps()->create([
    'name' => 'Procurement Review',
    'type' => StepType::USER_ACTION,
    'position' => 6,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Procurement Review - {{requisition_number}}',
        'description' => 'Select supplier and finalize pricing',
        'users' => [['role' => 'procurement_specialist']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    [
                        'name' => 'selected_supplier_id',
                        'type' => 'select',
                        'label' => 'Selected Supplier',
                        'options' => 'approved_suppliers',
                        'required' => true
                    ],
                    [
                        'name' => 'actual_prices',
                        'type' => 'repeater',
                        'label' => 'Actual Pricing',
                        'required' => true
                    ],
                    [
                        'name' => 'payment_terms',
                        'type' => 'select',
                        'label' => 'Payment Terms',
                        'options' => ['Net 30', 'Net 60', 'Net 90', 'Immediate'],
                        'required' => true
                    ],
                    [
                        'name' => 'delivery_date',
                        'type' => 'date',
                        'label' => 'Expected Delivery',
                        'required' => true
                    ],
                    [
                        'name' => 'notes',
                        'type' => 'textarea',
                        'label' => 'Procurement Notes',
                        'required' => false
                    ]
                ]
            ]
        ],
    ],
]);

// Step 5: Create PO in system
$workflow->steps()->create([
    'name' => 'Create Purchase Order',
    'type' => StepType::ACTION,
    'position' => 7,
    'configuration' => [
        'action_class' => \App\Actions\CreatePurchaseOrder::class,
        'parameters' => [
            'requisition_id' => '{{requisition_id}}',
            'supplier_id' => '{{selected_supplier_id}}',
            'items' => '{{items}}',
            'actual_prices' => '{{actual_prices}}',
            'payment_terms' => '{{payment_terms}}',
            'delivery_date' => '{{delivery_date}}',
            'delivery_location' => '{{delivery_location}}',
        ],
    ],
]);

// Step 6: Send PO to supplier via webhook/email
$workflow->steps()->create([
    'name' => 'Send PO to Supplier',
    'type' => StepType::NOTIFICATION,
    'position' => 8,
    'configuration' => [
        'notification_class' => \App\Notifications\PurchaseOrderToSupplier::class,
        'recipients' => ['{{supplier_email}}'],
        'attachments' => ['{{po_pdf_path}}'],
    ],
]);

// Step 7: Notify requester
$workflow->steps()->create([
    'name' => 'Notify Requester',
    'type' => StepType::NOTIFICATION,
    'position' => 9,
    'configuration' => [
        'notification_class' => \App\Notifications\RequisitionApproved::class,
        'recipients' => ['{{requester_id}}'],
    ],
]);
```

### 1.3 Execution

```php
// User initiates requisition
$execution = $workflow->execute([
    'requester_id' => auth()->id(),
    'requester_department' => auth()->user()->department,
    'currency' => 'USD',
]);

// Workflow pauses at Step 1 for form submission
// User completes form via API or UI
```

---

## 2. Supplier Onboarding & Approval

### 2.1 Business Process

New supplier application → Information verification → Credit check → Compliance review → Legal review → Approval

### 2.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Supplier Onboarding',
    'description' => 'Onboard and approve new suppliers',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Supplier submits application
$workflow->steps()->create([
    'name' => 'Supplier Application Form',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Supplier Application',
        'users' => [['email' => '{{supplier_contact_email}}']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'company_name', 'type' => 'text', 'required' => true],
                    ['name' => 'tax_id', 'type' => 'text', 'required' => true],
                    ['name' => 'business_address', 'type' => 'textarea', 'required' => true],
                    ['name' => 'primary_contact_name', 'type' => 'text', 'required' => true],
                    ['name' => 'primary_contact_email', 'type' => 'email', 'required' => true],
                    ['name' => 'primary_contact_phone', 'type' => 'tel', 'required' => true],
                    ['name' => 'bank_name', 'type' => 'text', 'required' => true],
                    ['name' => 'bank_account_number', 'type' => 'text', 'required' => true],
                    ['name' => 'categories', 'type' => 'multiselect', 'label' => 'Product/Service Categories', 'required' => true],
                    ['name' => 'certifications', 'type' => 'textarea', 'label' => 'Certifications (ISO, etc.)'],
                    ['name' => 'payment_terms_requested', 'type' => 'select', 'options' => ['Net 30', 'Net 60', 'Net 90']],
                ]
            ]
        ],
    ],
]);

// Step 2: Upload required documents
$workflow->steps()->create([
    'name' => 'Upload Required Documents',
    'type' => StepType::USER_ACTION,
    'position' => 2,
    'configuration' => [
        'action_type' => 'document_upload',
        'title' => 'Upload Supplier Documents',
        'description' => 'Please upload all required documentation',
        'users' => [['email' => '{{supplier_contact_email}}']],
        'action_config' => [
            'allowed_types' => ['pdf', 'docx', 'jpg', 'png'],
            'max_file_size_mb' => 10,
            'min_files' => 3,
            'max_files' => 10,
            'required_documents' => [
                'Business Registration Certificate',
                'Tax Clearance Certificate',
                'Bank Reference Letter',
                'Insurance Certificate',
                'Product/Service Catalog'
            ],
            'instructions' => 'All documents must be current (issued within last 12 months)'
        ],
    ],
]);

// Step 3: Procurement verification
$workflow->steps()->create([
    'name' => 'Procurement Verification',
    'type' => StepType::USER_ACTION,
    'position' => 3,
    'configuration' => [
        'action_type' => 'data_validation',
        'title' => 'Verify Supplier Information',
        'users' => [['role' => 'procurement_specialist']],
        'action_config' => [
            'validation_checklist' => [
                'Company name matches registration documents',
                'Tax ID is valid',
                'Bank details verified',
                'Contact information confirmed',
                'All required documents uploaded',
                'Documents are current and valid'
            ],
            'require_all_checked' => true,
            'allow_notes' => true,
            'decisions' => ['verified', 'request_corrections']
        ],
    ],
]);

// Step 4: Credit check
$workflow->steps()->create([
    'name' => 'Credit Check',
    'type' => StepType::USER_ACTION,
    'position' => 4,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Credit Assessment',
        'users' => [['role' => 'credit_controller']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'credit_score', 'type' => 'number', 'label' => 'Credit Score', 'required' => true],
                    ['name' => 'credit_rating', 'type' => 'select', 'label' => 'Credit Rating', 
                     'options' => ['Excellent', 'Good', 'Fair', 'Poor'], 'required' => true],
                    ['name' => 'recommended_credit_limit', 'type' => 'number', 'label' => 'Recommended Credit Limit', 'required' => true],
                    ['name' => 'payment_terms_approved', 'type' => 'select', 'options' => ['Net 30', 'Net 60', 'Net 90', 'Prepayment Only']],
                    ['name' => 'credit_notes', 'type' => 'textarea', 'label' => 'Credit Assessment Notes'],
                ]
            ]
        ],
    ],
]);

// Step 5: Parallel compliance and legal review
$workflow->steps()->create([
    'name' => 'Compliance and Legal Review',
    'type' => StepType::USER_ACTION_PARALLEL,
    'position' => 5,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Supplier Compliance & Legal Review',
        'users' => [
            ['role' => 'compliance_officer'],
            ['role' => 'legal_counsel'],
        ],
        'action_mode' => 'all',
        'action_config' => [
            'decisions' => ['approve', 'reject', 'request_additional_info'],
            'require_notes' => true,
            'show_context' => [
                'company_name',
                'categories',
                'certifications',
                'credit_rating',
                'uploaded_documents'
            ],
            'compliance_checklist' => [
                'No sanctions or embargoes',
                'Anti-bribery compliance',
                'Data protection compliance (GDPR, etc.)',
                'Industry-specific regulations met',
            ]
        ],
        'escalation_minutes' => 2880, // 48 hours
    ],
]);

// Step 6: Final approval by procurement manager
$workflow->steps()->create([
    'name' => 'Final Approval',
    'type' => StepType::USER_ACTION,
    'position' => 6,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Final Supplier Onboarding Approval',
        'users' => [['role' => 'procurement_manager']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject'],
            'require_notes_on_reject' => true,
        ],
    ],
]);

// Step 7: Create supplier in system
$workflow->steps()->create([
    'name' => 'Create Supplier Record',
    'type' => StepType::ACTION,
    'position' => 7,
    'configuration' => [
        'action_class' => \App\Actions\CreateSupplierRecord::class,
    ],
]);

// Step 8: Send welcome email
$workflow->steps()->create([
    'name' => 'Send Supplier Welcome',
    'type' => StepType::NOTIFICATION,
    'position' => 8,
    'configuration' => [
        'notification_class' => \App\Notifications\SupplierWelcome::class,
        'recipients' => ['{{supplier_contact_email}}'],
    ],
]);
```

---

## 3. Purchase Order Approval Workflow

### 3.1 Key Features

- Amount-based routing
- Budget verification
- Contract compliance check
- Audit trail

### 3.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Purchase Order Approval',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Validate PO against budget
$workflow->steps()->create([
    'name' => 'Budget Validation',
    'type' => StepType::ACTION,
    'position' => 1,
    'configuration' => [
        'action_class' => \App\Actions\ValidateBudgetAvailability::class,
        'parameters' => [
            'cost_center' => '{{cost_center}}',
            'amount' => '{{total_amount}}',
            'fiscal_year' => '{{fiscal_year}}',
        ],
        'fail_on_insufficient_budget' => false, // Allow override with approval
    ],
]);

// Step 2: Check if contract exists
$workflow->steps()->create([
    'name' => 'Contract Compliance Check',
    'type' => StepType::ACTION,
    'position' => 2,
    'configuration' => [
        'action_class' => \App\Actions\CheckContractCompliance::class,
        'parameters' => [
            'supplier_id' => '{{supplier_id}}',
            'items' => '{{items}}',
            'amount' => '{{total_amount}}',
        ],
    ],
]);

// Step 3: Conditional approval based on amount and budget status
$standardApprovalCheck = $workflow->steps()->create([
    'name' => 'Standard Approval Check',
    'type' => StepType::CONDITION,
    'position' => 3,
    'configuration' => [
        'operator' => 'and',
        'rules' => [
            ['field' => 'total_amount', 'operator' => '<=', 'value' => 50000],
            ['field' => 'budget_available', 'operator' => '==', 'value' => true],
            ['field' => 'contract_exists', 'operator' => '==', 'value' => true],
        ],
    ],
]);

// Path A: Standard approval (manager only)
$workflow->steps()->create([
    'name' => 'Manager Approval',
    'type' => StepType::USER_ACTION,
    'position' => 4,
    'parent_step_id' => $standardApprovalCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve PO {{po_number}}',
        'users' => [['role' => 'department_manager', 'department' => '{{department}}']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject'],
        ],
        'escalation_minutes' => 720,
    ],
]);

// Path B: Complex approval (multiple approvers)
$complexApprovalCheck = $workflow->steps()->create([
    'name' => 'Complex Approval Check',
    'type' => StepType::CONDITION,
    'position' => 4,
    'configuration' => [
        'operator' => 'or',
        'rules' => [
            ['field' => 'total_amount', 'operator' => '>', 'value' => 50000],
            ['field' => 'budget_available', 'operator' => '==', 'value' => false],
            ['field' => 'contract_exists', 'operator' => '==', 'value' => false],
        ],
    ],
]);

$workflow->steps()->create([
    'name' => 'Multi-Level Approval',
    'type' => StepType::USER_ACTION_SEQUENTIAL,
    'position' => 5,
    'parent_step_id' => $complexApprovalCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'action_chain' => [
            [
                'name' => 'Department Manager',
                'users' => [['role' => 'department_manager']],
            ],
            [
                'name' => 'Procurement Director',
                'users' => [['role' => 'procurement_director']],
            ],
            [
                'name' => 'Finance Director',
                'users' => [['role' => 'finance_director']],
                'conditions' => [
                    'operator' => 'or',
                    'rules' => [
                        ['field' => 'budget_available', 'operator' => '==', 'value' => false],
                        ['field' => 'total_amount', 'operator' => '>', 'value' => 100000],
                    ]
                ]
            ],
            [
                'name' => 'CFO',
                'users' => [['role' => 'cfo']],
                'conditions' => [
                    'operator' => 'and',
                    'rules' => [
                        ['field' => 'total_amount', 'operator' => '>', 'value' => 250000],
                    ]
                ]
            ],
        ],
    ],
]);

// Step 4: Finalize PO
$workflow->steps()->create([
    'name' => 'Finalize Purchase Order',
    'type' => StepType::ACTION,
    'position' => 6,
    'configuration' => [
        'action_class' => \App\Actions\FinalizePurchaseOrder::class,
    ],
]);

// Step 5: Send to supplier
$workflow->steps()->create([
    'name' => 'Send PO to Supplier',
    'type' => StepType::WEBHOOK,
    'position' => 7,
    'configuration' => [
        'url' => '{{supplier_api_endpoint}}',
        'method' => 'POST',
        'headers' => [
            'Authorization' => 'Bearer {{supplier_api_token}}',
            'Content-Type' => 'application/json',
        ],
        'payload' => [
            'po_number' => '{{po_number}}',
            'items' => '{{items}}',
            'delivery_date' => '{{delivery_date}}',
            'delivery_location' => '{{delivery_location}}',
        ],
    ],
]);
```

---

## 4. Goods Receipt & Quality Check

### 4.1 Business Process

Goods received → Quantity verification → Quality inspection → Acceptance or rejection → Update inventory

### 4.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Goods Receipt Process',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Warehouse receives goods
$workflow->steps()->create([
    'name' => 'Log Receipt',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Goods Receipt - PO {{po_number}}',
        'users' => [['role' => 'warehouse_clerk']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'delivery_date', 'type' => 'datetime', 'label' => 'Delivery Date/Time', 'required' => true],
                    ['name' => 'carrier', 'type' => 'text', 'label' => 'Carrier Name', 'required' => true],
                    ['name' => 'tracking_number', 'type' => 'text', 'label' => 'Tracking Number'],
                    ['name' => 'number_of_packages', 'type' => 'number', 'label' => 'Number of Packages', 'required' => true],
                    ['name' => 'package_condition', 'type' => 'select', 'label' => 'Package Condition',
                     'options' => ['Excellent', 'Good', 'Damaged'], 'required' => true],
                    ['name' => 'received_by', 'type' => 'text', 'label' => 'Received By', 'required' => true],
                    ['name' => 'notes', 'type' => 'textarea', 'label' => 'Receipt Notes'],
                ]
            ]
        ],
    ],
]);

// Step 2: Photo documentation if damaged
$damagedCheck = $workflow->steps()->create([
    'name' => 'Check if Damaged',
    'type' => StepType::CONDITION,
    'position' => 2,
    'configuration' => [
        'field' => 'package_condition',
        'operator' => '==',
        'value' => 'Damaged',
    ],
]);

$workflow->steps()->create([
    'name' => 'Upload Damage Photos',
    'type' => StepType::USER_ACTION,
    'position' => 3,
    'parent_step_id' => $damagedCheck->id,
    'configuration' => [
        'action_type' => 'document_upload',
        'title' => 'Upload Damage Photos',
        'users' => [['role' => 'warehouse_clerk']],
        'action_config' => [
            'allowed_types' => ['jpg', 'png'],
            'min_files' => 3,
            'max_files' => 10,
            'instructions' => 'Take clear photos showing all damage from multiple angles'
        ],
    ],
]);

// Step 3: Quantity verification
$workflow->steps()->create([
    'name' => 'Verify Quantities',
    'type' => StepType::USER_ACTION,
    'position' => 4,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Quantity Verification',
        'users' => [['role' => 'warehouse_clerk']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    [
                        'name' => 'line_items',
                        'type' => 'repeater',
                        'label' => 'Verify Each Line Item',
                        'data_source' => '{{po_line_items}}', // Pre-populate from PO
                        'fields' => [
                            ['name' => 'sku', 'type' => 'text', 'label' => 'SKU', 'readonly' => true],
                            ['name' => 'ordered_quantity', 'type' => 'number', 'label' => 'Ordered', 'readonly' => true],
                            ['name' => 'received_quantity', 'type' => 'number', 'label' => 'Received', 'required' => true],
                            ['name' => 'variance', 'type' => 'number', 'label' => 'Variance', 'calculated' => true],
                            ['name' => 'variance_reason', 'type' => 'textarea', 'label' => 'Variance Reason',
                             'required_if' => 'variance != 0'],
                        ]
                    ]
                ]
            ]
        ],
    ],
]);

// Step 4: Quality inspection (parallel if multiple inspectors)
$workflow->steps()->create([
    'name' => 'Quality Inspection',
    'type' => StepType::USER_ACTION,
    'position' => 5,
    'configuration' => [
        'action_type' => 'data_validation',
        'title' => 'Quality Inspection - PO {{po_number}}',
        'users' => [['role' => 'quality_inspector']],
        'action_config' => [
            'validation_checklist' => [
                'Products match specifications',
                'No visible defects',
                'Packaging is intact',
                'Labels are correct and legible',
                'Batch/lot numbers recorded',
                'Expiry dates checked (if applicable)',
                'Quantity matches packing slip',
                'Temperature controlled items within range (if applicable)',
            ],
            'require_all_checked' => false, // Allow partial pass
            'allow_notes' => true,
            'allow_photos' => true,
            'decisions' => ['accept', 'reject', 'partial_accept'],
            'sample_size_required' => true,
        ],
    ],
]);

// Step 5: Decision on acceptance
$acceptanceCheck = $workflow->steps()->create([
    'name' => 'Check Acceptance Decision',
    'type' => StepType::CONDITION,
    'position' => 6,
    'configuration' => [
        'field' => 'qc_decision',
        'operator' => '==',
        'value' => 'accept',
    ],
]);

// Path A: Accepted - update inventory
$workflow->steps()->create([
    'name' => 'Update Inventory',
    'type' => StepType::ACTION,
    'position' => 7,
    'parent_step_id' => $acceptanceCheck->id,
    'configuration' => [
        'action_class' => \App\Actions\UpdateInventoryFromReceipt::class,
        'parameters' => [
            'po_id' => '{{po_id}}',
            'received_items' => '{{line_items}}',
            'location' => '{{delivery_location}}',
        ],
    ],
]);

// Path B: Rejected or partial - create RMA
$rejectionCheck = $workflow->steps()->create([
    'name' => 'Check Rejection',
    'type' => StepType::CONDITION,
    'position' => 7,
    'configuration' => [
        'operator' => 'or',
        'rules' => [
            ['field' => 'qc_decision', 'operator' => '==', 'value' => 'reject'],
            ['field' => 'qc_decision', 'operator' => '==', 'value' => 'partial_accept'],
        ],
    ],
]);

$workflow->steps()->create([
    'name' => 'Create Return Request',
    'type' => StepType::ACTION,
    'position' => 8,
    'parent_step_id' => $rejectionCheck->id,
    'configuration' => [
        'action_class' => \App\Actions\CreateReturnMerchandiseAuthorization::class,
        'parameters' => [
            'po_id' => '{{po_id}}',
            'rejected_items' => '{{rejected_items}}',
            'reason' => '{{qc_notes}}',
        ],
    ],
]);

// Step 6: Notify procurement
$workflow->steps()->create([
    'name' => 'Notify Procurement',
    'type' => StepType::NOTIFICATION,
    'position' => 9,
    'configuration' => [
        'notification_class' => \App\Notifications\GoodsReceiptCompleted::class,
        'recipients_by_role' => ['procurement_specialist'],
    ],
]);
```

---

## 5. Invoice Matching & Payment Approval

### 5.1 Business Process

Invoice received → 3-way match (PO, Receipt, Invoice) → Discrepancy resolution → Payment approval → Payment scheduled

### 5.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Invoice Approval & Payment',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Invoice entry
$workflow->steps()->create([
    'name' => 'Enter Invoice Details',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Enter Supplier Invoice',
        'users' => [['role' => 'accounts_payable_clerk']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'invoice_number', 'type' => 'text', 'required' => true],
                    ['name' => 'invoice_date', 'type' => 'date', 'required' => true],
                    ['name' => 'due_date', 'type' => 'date', 'required' => true],
                    ['name' => 'po_number', 'type' => 'select', 'label' => 'Related PO', 
                     'options' => 'open_purchase_orders', 'required' => true],
                    ['name' => 'invoice_total', 'type' => 'number', 'required' => true],
                    ['name' => 'tax_amount', 'type' => 'number', 'required' => true],
                    ['name' => 'line_items', 'type' => 'repeater', 'label' => 'Invoice Line Items'],
                ]
            ]
        ],
    ],
]);

// Step 2: Upload invoice document
$workflow->steps()->create([
    'name' => 'Upload Invoice PDF',
    'type' => StepType::USER_ACTION,
    'position' => 2,
    'configuration' => [
        'action_type' => 'document_upload',
        'title' => 'Upload Invoice Document',
        'users' => [['role' => 'accounts_payable_clerk']],
        'action_config' => [
            'allowed_types' => ['pdf'],
            'min_files' => 1,
            'max_files' => 1,
        ],
    ],
]);

// Step 3: Automated 3-way match
$workflow->steps()->create([
    'name' => 'Three-Way Match',
    'type' => StepType::ACTION,
    'position' => 3,
    'configuration' => [
        'action_class' => \App\Actions\ThreeWayMatch::class,
        'parameters' => [
            'invoice_id' => '{{invoice_id}}',
            'po_number' => '{{po_number}}',
        ],
        'tolerance' => [
            'quantity_variance' => 2, // 2%
            'price_variance' => 5, // 5%
        ],
    ],
]);

// Step 4: Check if matched
$matchedCheck = $workflow->steps()->create([
    'name' => 'Check Match Status',
    'type' => StepType::CONDITION,
    'position' => 4,
    'configuration' => [
        'field' => 'match_status',
        'operator' => '==',
        'value' => 'matched',
    ],
]);

// Path A: Matched - proceed to approval
$workflow->steps()->create([
    'name' => 'Payment Approval',
    'type' => StepType::USER_ACTION,
    'position' => 5,
    'parent_step_id' => $matchedCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Payment for Invoice {{invoice_number}}',
        'users' => [['role' => 'accounts_payable_manager']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject'],
            'show_context' => [
                'supplier_name',
                'invoice_number',
                'invoice_total',
                'due_date',
                'match_status',
            ],
        ],
    ],
]);

// Path B: Discrepancy - resolve
$mismatchCheck = $workflow->steps()->create([
    'name' => 'Check Mismatch',
    'type' => StepType::CONDITION,
    'position' => 5,
    'configuration' => [
        'field' => 'match_status',
        'operator' => '==',
        'value' => 'mismatch',
    ],
]);

$workflow->steps()->create([
    'name' => 'Resolve Discrepancy',
    'type' => StepType::USER_ACTION,
    'position' => 6,
    'parent_step_id' => $mismatchCheck->id,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Resolve Invoice Discrepancy',
        'description' => 'Discrepancies found: {{mismatch_details}}',
        'users' => [['role' => 'accounts_payable_clerk']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'discrepancy_type', 'type' => 'select',
                     'options' => ['Quantity Difference', 'Price Difference', 'Missing Items', 'Extra Items', 'Other']],
                    ['name' => 'resolution_action', 'type' => 'select',
                     'options' => ['Contact Supplier', 'Adjust Invoice', 'Create Credit Note', 'Accept Variance']],
                    ['name' => 'adjusted_amount', 'type' => 'number', 'label' => 'Adjusted Invoice Amount'],
                    ['name' => 'notes', 'type' => 'textarea', 'required' => true],
                ]
            ]
        ],
    ],
]);

// After resolution, manager approval required
$workflow->steps()->create([
    'name' => 'Manager Approval (Adjusted)',
    'type' => StepType::USER_ACTION,
    'position' => 7,
    'parent_step_id' => $mismatchCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Adjusted Invoice Payment',
        'users' => [['role' => 'accounts_payable_manager']],
        'action_config' => [
            'decisions' => ['approve', 'reject'],
            'show_context' => [
                'original_amount',
                'adjusted_amount',
                'discrepancy_type',
                'resolution_notes',
            ],
        ],
    ],
]);

// Step 5: Schedule payment
$workflow->steps()->create([
    'name' => 'Schedule Payment',
    'type' => StepType::ACTION,
    'position' => 8,
    'configuration' => [
        'action_class' => \App\Actions\SchedulePayment::class,
        'parameters' => [
            'invoice_id' => '{{invoice_id}}',
            'amount' => '{{approved_amount}}',
            'payment_date' => '{{due_date}}',
            'payment_method' => '{{payment_method}}',
        ],
    ],
]);

// Step 6: Update PO status
$workflow->steps()->create([
    'name' => 'Update PO Status',
    'type' => StepType::ACTION,
    'position' => 9,
    'configuration' => [
        'action_class' => \App\Actions\UpdatePurchaseOrderStatus::class,
        'parameters' => [
            'po_number' => '{{po_number}}',
            'status' => 'invoiced',
        ],
    ],
]);
```

---

## 6. Inventory Adjustment Approval

### 6.1 Business Process

Adjustment identified → Reason documented → Count verification → Manager approval → Inventory updated

### 6.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Inventory Adjustment',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Document adjustment
$workflow->steps()->create([
    'name' => 'Document Inventory Adjustment',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Inventory Adjustment Request',
        'users' => [['role' => 'warehouse_clerk']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'adjustment_type', 'type' => 'select',
                     'options' => ['Physical Count Correction', 'Damage/Loss', 'Found Stock', 'System Error', 'Theft', 'Expiry', 'Other'],
                     'required' => true],
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Items to Adjust',
                     'fields' => [
                         ['name' => 'sku', 'type' => 'select', 'label' => 'Product', 'required' => true],
                         ['name' => 'current_quantity', 'type' => 'number', 'label' => 'System Quantity', 'readonly' => true],
                         ['name' => 'actual_quantity', 'type' => 'number', 'label' => 'Actual Quantity', 'required' => true],
                         ['name' => 'variance', 'type' => 'number', 'label' => 'Variance', 'calculated' => true],
                         ['name' => 'location', 'type' => 'select', 'label' => 'Warehouse Location', 'required' => true],
                     ]],
                    ['name' => 'reason_details', 'type' => 'textarea', 'label' => 'Detailed Reason', 'required' => true],
                    ['name' => 'total_value_impact', 'type' => 'number', 'label' => 'Estimated Value Impact', 'calculated' => true],
                ]
            ]
        ],
    ],
]);

// Step 2: Photo evidence (if damage/loss/theft)
$evidenceRequiredCheck = $workflow->steps()->create([
    'name' => 'Check if Evidence Required',
    'type' => StepType::CONDITION,
    'position' => 2,
    'configuration' => [
        'field' => 'adjustment_type',
        'operator' => 'in',
        'value' => ['Damage/Loss', 'Theft', 'Expiry'],
    ],
]);

$workflow->steps()->create([
    'name' => 'Upload Evidence Photos',
    'type' => StepType::USER_ACTION,
    'position' => 3,
    'parent_step_id' => $evidenceRequiredCheck->id,
    'configuration' => [
        'action_type' => 'document_upload',
        'title' => 'Upload Evidence',
        'users' => [['role' => 'warehouse_clerk']],
        'action_config' => [
            'allowed_types' => ['jpg', 'png', 'pdf'],
            'min_files' => 1,
            'max_files' => 10,
        ],
    ],
]);

// Step 3: Second count verification (if variance > threshold)
$significantVarianceCheck = $workflow->steps()->create([
    'name' => 'Check Variance Significance',
    'type' => StepType::CONDITION,
    'position' => 4,
    'configuration' => [
        'operator' => 'or',
        'rules' => [
            ['field' => 'abs(variance_quantity)', 'operator' => '>', 'value' => 100],
            ['field' => 'total_value_impact', 'operator' => '>', 'value' => 1000],
        ],
    ],
]);

$workflow->steps()->create([
    'name' => 'Second Count Verification',
    'type' => StepType::USER_ACTION,
    'position' => 5,
    'parent_step_id' => $significantVarianceCheck->id,
    'configuration' => [
        'action_type' => 'data_validation',
        'title' => 'Verify Physical Count',
        'description' => 'Significant variance detected - please perform second count',
        'users' => [['role' => 'warehouse_supervisor']],
        'action_config' => [
            'require_recount' => true,
            'items_to_verify' => '{{items}}',
            'decisions' => ['confirmed', 'discrepancy_found'],
        ],
    ],
]);

// Step 4: Approval based on value
$lowValueCheck = $workflow->steps()->create([
    'name' => 'Check Adjustment Value',
    'type' => StepType::CONDITION,
    'position' => 6,
    'configuration' => [
        'field' => 'abs(total_value_impact)',
        'operator' => '<=',
        'value' => 500,
    ],
]);

// Path A: Low value - supervisor approval
$workflow->steps()->create([
    'name' => 'Supervisor Approval (Low Value)',
    'type' => StepType::USER_ACTION,
    'position' => 7,
    'parent_step_id' => $lowValueCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Inventory Adjustment',
        'users' => [['role' => 'warehouse_supervisor']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject', 'investigate_further'],
        ],
    ],
]);

// Path B: High value - manager approval
$highValueCheck = $workflow->steps()->create([
    'name' => 'High Value Check',
    'type' => StepType::CONDITION,
    'position' => 7,
    'configuration' => [
        'field' => 'abs(total_value_impact)',
        'operator' => '>',
        'value' => 500,
    ],
]);

$workflow->steps()->create([
    'name' => 'Manager Approval (High Value)',
    'type' => StepType::USER_ACTION,
    'position' => 8,
    'parent_step_id' => $highValueCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve High-Value Inventory Adjustment',
        'users' => [['role' => 'warehouse_manager']],
        'action_mode' => 'any',
        'action_config' => [
            'decisions' => ['approve', 'reject', 'request_audit'],
            'require_notes' => true,
        ],
    ],
]);

// Step 5: Apply adjustment
$workflow->steps()->create([
    'name' => 'Apply Inventory Adjustment',
    'type' => StepType::ACTION,
    'position' => 9,
    'configuration' => [
        'action_class' => \App\Actions\ApplyInventoryAdjustment::class,
        'parameters' => [
            'adjustment_id' => '{{adjustment_id}}',
            'items' => '{{items}}',
            'reason' => '{{adjustment_type}}',
        ],
    ],
]);

// Step 6: Notify finance (if value > threshold)
$financeNotificationCheck = $workflow->steps()->create([
    'name' => 'Check if Finance Notification Required',
    'type' => StepType::CONDITION,
    'position' => 10,
    'configuration' => [
        'field' => 'abs(total_value_impact)',
        'operator' => '>',
        'value' => 5000,
    ],
]);

$workflow->steps()->create([
    'name' => 'Notify Finance Team',
    'type' => StepType::NOTIFICATION,
    'position' => 11,
    'parent_step_id' => $financeNotificationCheck->id,
    'configuration' => [
        'notification_class' => \App\Notifications\HighValueInventoryAdjustment::class,
        'recipients_by_role' => ['finance_controller'],
    ],
]);
```

---

## 7. Stock Transfer Request

### 7.1 Business Process

Transfer request → Approval → Pick items → Ship → Receive at destination → Update inventory

### 7.2 Workflow Configuration

```php
$workflow = Workflow::create([
    'name' => 'Stock Transfer Between Locations',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Request transfer
$workflow->steps()->create([
    'name' => 'Stock Transfer Request',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Request Stock Transfer',
        'users' => [['role' => 'warehouse_clerk']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'from_location', 'type' => 'select', 'options' => 'warehouse_locations', 'required' => true],
                    ['name' => 'to_location', 'type' => 'select', 'options' => 'warehouse_locations', 'required' => true],
                    ['name' => 'transfer_reason', 'type' => 'select',
                     'options' => ['Stock Balancing', 'Customer Order', 'Production Need', 'Store Replenishment', 'Other'],
                     'required' => true],
                    ['name' => 'items', 'type' => 'repeater', 'label' => 'Items to Transfer',
                     'fields' => [
                         ['name' => 'sku', 'type' => 'select', 'required' => true],
                         ['name' => 'quantity', 'type' => 'number', 'required' => true],
                         ['name' => 'available_stock', 'type' => 'number', 'readonly' => true],
                     ]],
                    ['name' => 'urgency', 'type' => 'select', 'options' => ['Normal', 'Urgent', 'Emergency'], 'required' => true],
                    ['name' => 'required_date', 'type' => 'date', 'label' => 'Required By Date'],
                ]
            ]
        ],
    ],
]);

// Step 2: Check stock availability
$workflow->steps()->create([
    'name' => 'Verify Stock Availability',
    'type' => StepType::ACTION,
    'position' => 2,
    'configuration' => [
        'action_class' => \App\Actions\CheckStockAvailability::class,
        'fail_if_insufficient' => false,
    ],
]);

// Step 3: Approval (conditional based on urgency and value)
$normalTransferCheck = $workflow->steps()->create([
    'name' => 'Normal Transfer Check',
    'type' => StepType::CONDITION,
    'position' => 3,
    'configuration' => [
        'field' => 'urgency',
        'operator' => '==',
        'value' => 'Normal',
    ],
]);

$workflow->steps()->create([
    'name' => 'Supervisor Approval',
    'type' => StepType::USER_ACTION,
    'position' => 4,
    'parent_step_id' => $normalTransferCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Stock Transfer',
        'users' => [['role' => 'warehouse_supervisor', 'location' => '{{from_location}}']],
        'action_config' => [
            'decisions' => ['approve', 'reject'],
        ],
    ],
]);

$urgentTransferCheck = $workflow->steps()->create([
    'name' => 'Urgent Transfer Check',
    'type' => StepType::CONDITION,
    'position' => 4,
    'configuration' => [
        'operator' => 'or',
        'rules' => [
            ['field' => 'urgency', 'operator' => '==', 'value' => 'Urgent'],
            ['field' => 'urgency', 'operator' => '==', 'value' => 'Emergency'],
        ],
    ],
]);

$workflow->steps()->create([
    'name' => 'Manager Approval (Urgent)',
    'type' => StepType::USER_ACTION,
    'position' => 5,
    'parent_step_id' => $urgentTransferCheck->id,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve URGENT Stock Transfer',
        'users' => [['role' => 'warehouse_manager']],
        'action_config' => [
            'decisions' => ['approve', 'reject'],
        ],
        'escalation_minutes' => 120, // 2 hours for urgent
    ],
]);

// Step 4: Pick items at source
$workflow->steps()->create([
    'name' => 'Pick Items',
    'type' => StepType::USER_ACTION,
    'position' => 6,
    'configuration' => [
        'action_type' => 'data_validation',
        'title' => 'Pick Items for Transfer {{transfer_id}}',
        'users' => [['role' => 'warehouse_picker', 'location' => '{{from_location}}']],
        'action_config' => [
            'picking_list' => '{{items}}',
            'require_barcode_scan' => true,
            'validate_quantities' => true,
        ],
    ],
]);

// Step 5: Pack and ship
$workflow->steps()->create([
    'name' => 'Pack and Ship',
    'type' => StepType::USER_ACTION,
    'position' => 7,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Pack and Ship Transfer',
        'users' => [['role' => 'warehouse_clerk', 'location' => '{{from_location}}']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'carrier', 'type' => 'select', 'options' => 'carriers', 'required' => true],
                    ['name' => 'tracking_number', 'type' => 'text', 'required' => true],
                    ['name' => 'ship_date', 'type' => 'datetime', 'required' => true],
                    ['name' => 'number_of_packages', 'type' => 'number', 'required' => true],
                    ['name' => 'total_weight', 'type' => 'number', 'label' => 'Total Weight (kg)'],
                ]
            ]
        ],
    ],
]);

// Step 6: Update source inventory
$workflow->steps()->create([
    'name' => 'Decrement Source Inventory',
    'type' => StepType::ACTION,
    'position' => 8,
    'configuration' => [
        'action_class' => \App\Actions\UpdateInventory::class,
        'parameters' => [
            'location' => '{{from_location}}',
            'items' => '{{items}}',
            'operation' => 'decrement',
            'reason' => 'transfer_out',
        ],
    ],
]);

// Step 7: Notify destination
$workflow->steps()->create([
    'name' => 'Notify Destination',
    'type' => StepType::NOTIFICATION,
    'position' => 9,
    'configuration' => [
        'notification_class' => \App\Notifications\StockTransferInTransit::class,
        'recipients_by_role' => ['warehouse_clerk'],
        'filter_by_location' => '{{to_location}}',
    ],
]);

// Step 8: Receive at destination
$workflow->steps()->create([
    'name' => 'Receive at Destination',
    'type' => StepType::USER_ACTION,
    'position' => 10,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Receive Stock Transfer',
        'users' => [['role' => 'warehouse_clerk', 'location' => '{{to_location}}']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'received_date', 'type' => 'datetime', 'required' => true],
                    ['name' => 'condition', 'type' => 'select', 'options' => ['Good', 'Damaged'], 'required' => true],
                    ['name' => 'items_received', 'type' => 'repeater', 'label' => 'Verify Items',
                     'data_source' => '{{items}}',
                     'fields' => [
                         ['name' => 'sku', 'type' => 'text', 'readonly' => true],
                         ['name' => 'expected_quantity', 'type' => 'number', 'readonly' => true],
                         ['name' => 'received_quantity', 'type' => 'number', 'required' => true],
                         ['name' => 'variance', 'type' => 'number', 'calculated' => true],
                     ]],
                ]
            ]
        ],
    ],
]);

// Step 9: Update destination inventory
$workflow->steps()->create([
    'name' => 'Increment Destination Inventory',
    'type' => StepType::ACTION,
    'position' => 11,
    'configuration' => [
        'action_class' => \App\Actions\UpdateInventory::class,
        'parameters' => [
            'location' => '{{to_location}}',
            'items' => '{{items_received}}',
            'operation' => 'increment',
            'reason' => 'transfer_in',
        ],
    ],
]);

// Step 10: Complete transfer
$workflow->steps()->create([
    'name' => 'Close Transfer',
    'type' => StepType::ACTION,
    'position' => 12,
    'configuration' => [
        'action_class' => \App\Actions\CompleteStockTransfer::class,
    ],
]);
```

---

## 8. Return Merchandise Authorization (RMA)

### 8.1 Business Process

Return request → Approval → Generate RMA → Receive returned goods → Inspection → Credit/refund

### 8.2 Workflow Configuration (Simplified)

```php
$workflow = Workflow::create([
    'name' => 'Return Merchandise Authorization',
    'status' => WorkflowStatus::ACTIVE,
]);

// Step 1: Submit return request
$workflow->steps()->create([
    'name' => 'Submit Return Request',
    'type' => StepType::USER_ACTION,
    'position' => 1,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Request RMA',
        'users' => [['role' => 'customer_service_rep']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'original_po_number', 'type' => 'select', 'required' => true],
                    ['name' => 'return_reason', 'type' => 'select',
                     'options' => ['Defective', 'Wrong Item', 'Damaged in Transit', 'Not as Described', 'Buyer Remorse', 'Other'],
                     'required' => true],
                    ['name' => 'items_to_return', 'type' => 'repeater'],
                    ['name' => 'customer_comments', 'type' => 'textarea'],
                ]
            ]
        ],
    ],
]);

// Step 2: Upload photos
$workflow->steps()->create([
    'name' => 'Upload Photos of Items',
    'type' => StepType::USER_ACTION,
    'position' => 2,
    'configuration' => [
        'action_type' => 'document_upload',
        'title' => 'Upload Photos of Returned Items',
        'users' => [['role' => 'customer_service_rep']],
        'action_config' => [
            'allowed_types' => ['jpg', 'png'],
            'min_files' => 2,
        ],
    ],
]);

// Step 3: Approval
$workflow->steps()->create([
    'name' => 'RMA Approval',
    'type' => StepType::USER_ACTION,
    'position' => 3,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve RMA Request',
        'users' => [['role' => 'customer_service_manager']],
        'action_config' => [
            'decisions' => ['approve', 'reject'],
        ],
    ],
]);

// Continue with receive, inspect, credit steps...
```

---

## 9. Budget Request & Allocation

```php
$workflow = Workflow::create(['name' => 'Budget Request Workflow']);

// Step 1: Submit budget request
$workflow->steps()->create([
    'name' => 'Submit Budget Request',
    'type' => StepType::USER_ACTION,
    'configuration' => [
        'action_type' => 'form_submission',
        'title' => 'Budget Request',
        'users' => [['user_id' => '{{requester_id}}']],
        'action_config' => [
            'form_schema' => [
                'fields' => [
                    ['name' => 'fiscal_year', 'type' => 'select', 'required' => true],
                    ['name' => 'cost_center', 'type' => 'select', 'required' => true],
                    ['name' => 'category', 'type' => 'select',
                     'options' => ['Capex', 'Opex', 'R&D', 'Marketing', 'IT', 'Other'], 'required' => true],
                    ['name' => 'amount_requested', 'type' => 'number', 'required' => true],
                    ['name' => 'justification', 'type' => 'textarea', 'required' => true, 'validation' => 'min:200'],
                    ['name' => 'expected_roi', 'type' => 'textarea', 'label' => 'Expected ROI/Benefits'],
                    ['name' => 'timeline', 'type' => 'textarea', 'label' => 'Implementation Timeline'],
                ]
            ]
        ],
    ],
]);

// Step 2: Department head approval
$workflow->steps()->create([
    'name' => 'Department Head Approval',
    'type' => StepType::USER_ACTION,
    'configuration' => [
        'action_type' => 'approval',
        'title' => 'Approve Budget Request',
        'users' => [['role' => 'department_head', 'department' => '{{requester_department}}']],
        'action_config' => [
            'decisions' => ['approve', 'reject', 'request_revision'],
        ],
    ],
]);

// Step 3: Finance review and approval
$workflow->steps()->create([
    'name' => 'Finance Approval',
    'type' => StepType::USER_ACTION_SEQUENTIAL,
    'configuration' => [
        'action_type' => 'approval',
        'action_chain' => [
            ['name' => 'Finance Controller', 'users' => [['role' => 'finance_controller']]],
            ['name' => 'CFO', 'users' => [['role' => 'cfo']],
             'conditions' => [
                 'operator' => 'and',
                 'rules' => [['field' => 'amount_requested', 'operator' => '>', 'value' => 100000]]
             ]],
        ],
    ],
]);

// Step 4: Allocate budget
$workflow->steps()->create([
    'name' => 'Allocate Budget',
    'type' => StepType::ACTION,
    'configuration' => [
        'action_class' => \App\Actions\AllocateBudget::class,
    ],
]);
```

---

## 10. Summary: User Action Usage Across Procurement

| Workflow | User Action Types Used | Key Benefits |
|----------|----------------------|--------------|
| Purchase Requisition | form_submission, approval | Structured data collection + multi-level approval |
| Supplier Onboarding | form_submission, document_upload, data_validation, approval | Complete verification process |
| PO Approval | approval | Amount-based routing, budget compliance |
| Goods Receipt | form_submission, document_upload, data_validation | Quality control + evidence trail |
| Invoice Matching | form_submission, document_upload, approval | 3-way match + discrepancy resolution |
| Inventory Adjustment | form_submission, document_upload, data_validation, approval | Audit trail + value-based approval |
| Stock Transfer | form_submission, data_validation, approval | Cross-location coordination |
| RMA Process | form_submission, document_upload, approval | Customer satisfaction + cost control |
| Budget Request | form_submission, approval | Financial control + ROI justification |

---

## 11. Key Patterns

### 11.1 Amount/Value-Based Routing

```php
// Low value → simple approval
// High value → multi-level approval
// Very high value → executive approval
```

### 11.2 Conditional Approvals

```php
// If budget available → manager approval
// If no budget → finance director + CFO approval
```

### 11.3 Evidence Collection

```php
// Form submission → Document upload → Verification
// Used in: RMA, inventory adjustments, quality checks
```

### 11.4 Sequential Reviews

```php
// Department → Procurement → Finance → Executive
// Each level adds context and validation
```

### 11.5 Parallel Approvals

```php
// Legal + Finance + Compliance
// All must approve (or majority)
```

---

## 12. Integration Points

### 12.1 ERP Integration

- Create/update purchase orders
- Update inventory levels
- Record financial transactions
- Budget allocation/tracking

### 12.2 Supplier Portal

- Supplier onboarding forms
- PO acknowledgement
- Invoice submission
- Document uploads

### 12.3 Notifications

- Email approvers
- SMS for urgent approvals
- Slack/Teams integrations
- Mobile push notifications

### 12.4 Analytics

- Approval cycle times
- Bottleneck identification
- User performance metrics
- Compliance reporting

---

## Conclusion

The UserAction engine provides a flexible foundation for automating procurement and inventory workflows while maintaining proper controls, audit trails, and user interactions. The generalized approach means the same infrastructure handles approvals, form submissions, document uploads, quality checks, and more - reducing complexity while maximizing flexibility.

---

**End of Document**
