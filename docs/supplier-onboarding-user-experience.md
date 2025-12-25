# Supplier Onboarding: User Experience Walkthrough

**Version:** 1.0  
**Date:** 2025-12-25  
**Context:** Step-by-step user journey through a real workflow

---

## Overview

This document walks through the **actual user experience** of a supplier onboarding workflow, showing what each user sees at each step, from initial submission to final approval.

---

## Workflow Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                   Supplier Onboarding Workflow                   │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  1. Supplier submits application form                           │
│  2. Supplier uploads required documents                          │
│  3. Procurement specialist verifies information                  │
│  4. Credit controller assesses creditworthiness                  │
│  5. Compliance & Legal review (parallel)                         │
│  6. Procurement manager gives final approval                     │
│  7. Supplier record created in system                            │
│  8. Welcome email sent to supplier                               │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Step-by-Step User Experience

### Trigger: Procurement Manager Initiates Workflow

**Where:** In the existing procurement application

**What Happens:**

```blade
{{-- In your existing supplier management page --}}
{{-- resources/views/suppliers/index.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-header">
        <h1>Supplier Management</h1>
        <div class="actions">
            {{-- This button starts the workflow --}}
            <button class="btn btn-primary" onclick="initiateSupplierOnboarding()">
                <i class="icon-plus"></i> Add New Supplier
            </button>
        </div>
    </div>
    
    {{-- Existing supplier list --}}
    <table class="table">
        <!-- Your existing suppliers -->
    </table>
</div>
@endsection

@push('scripts')
<script>
function initiateSupplierOnboarding() {
    // Show modal to enter supplier contact email
    $('#supplierEmailModal').modal('show');
}

function startWorkflow() {
    const supplierEmail = $('#supplier_email').val();
    
    // Call your backend to start the workflow
    fetch('/api/suppliers/onboarding/start', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({
            supplier_contact_email: supplierEmail
        })
    })
    .then(response => response.json())
    .then(data => {
        toastr.success('Supplier onboarding initiated. Email sent to supplier.');
        $('#supplierEmailModal').modal('hide');
    });
}
</script>
@endpush
```

**Backend Route:**

```php
// routes/api.php or routes/web.php

Route::post('/suppliers/onboarding/start', function (Request $request) {
    $validated = $request->validate([
        'supplier_contact_email' => 'required|email',
    ]);
    
    // Find or create the supplier onboarding workflow
    $workflow = Workflow::where('name', 'Supplier Onboarding')->firstOrFail();
    
    // Execute the workflow
    $execution = $workflow->execute([
        'supplier_contact_email' => $validated['supplier_contact_email'],
        'initiated_by' => auth()->id(),
        'initiated_at' => now(),
    ]);
    
    return response()->json([
        'success' => true,
        'execution_id' => $execution->id,
        'message' => 'Supplier onboarding workflow initiated'
    ]);
})->middleware('auth');
```

---

## STEP 1: Supplier Receives Email & Submits Application

### 1.1 Email Notification to Supplier

**Sent automatically when workflow starts**

```
From: procurement@yourcompany.com
To: supplier@acmecorp.com
Subject: Complete Your Supplier Application - YourCompany

Hello,

You have been invited to become a supplier for YourCompany. To complete 
your application, please click the link below:

[Complete Application] → https://yourcompany.com/supplier-application/abc123xyz

This link will expire in 7 days.

If you have any questions, please contact our procurement team.

Best regards,
YourCompany Procurement Team
```

### 1.2 Supplier Clicks Link and Sees Application Form

**URL:** `https://yourcompany.com/supplier-application/abc123xyz`

**What the Supplier Sees:**

```blade
{{-- resources/views/supplier-onboarding/application.blade.php --}}

@extends('layouts.public')

@section('content')
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">Supplier Application Form</h2>
                    <p class="mb-0">YourCompany</p>
                </div>
                
                <div class="card-body">
                    {{-- This is the UserAction form component --}}
                    <livewire:forgepulse::user-action-form 
                        :userAction="$userAction"
                        :showTitle="false"
                        successRedirect="/supplier-application/documents"
                    />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

**What the Form Looks Like (Rendered from JSON schema):**

```html
<form class="supplier-application-form">
    <div class="alert alert-info">
        <strong>Step 1 of 2:</strong> Company Information
    </div>
    
    <!-- Company Name -->
    <div class="form-group">
        <label>Company Name <span class="text-danger">*</span></label>
        <input 
            type="text" 
            name="company_name" 
            class="form-control" 
            placeholder="Enter your company name"
            required
        />
    </div>
    
    <!-- Tax ID -->
    <div class="form-group">
        <label>Tax ID / VAT Number <span class="text-danger">*</span></label>
        <input 
            type="text" 
            name="tax_id" 
            class="form-control" 
            placeholder="e.g., 12-3456789"
            required
        />
    </div>
    
    <!-- Business Address -->
    <div class="form-group">
        <label>Business Address <span class="text-danger">*</span></label>
        <textarea 
            name="business_address" 
            class="form-control" 
            rows="3"
            placeholder="Full business address including country"
            required
        ></textarea>
    </div>
    
    <!-- Primary Contact -->
    <h4 class="mt-4">Primary Contact</h4>
    
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <label>Contact Name <span class="text-danger">*</span></label>
                <input type="text" name="primary_contact_name" class="form-control" required />
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <label>Contact Email <span class="text-danger">*</span></label>
                <input type="email" name="primary_contact_email" class="form-control" required />
            </div>
        </div>
    </div>
    
    <div class="form-group">
        <label>Contact Phone <span class="text-danger">*</span></label>
        <input type="tel" name="primary_contact_phone" class="form-control" required />
    </div>
    
    <!-- Banking Information -->
    <h4 class="mt-4">Banking Information</h4>
    
    <div class="form-group">
        <label>Bank Name <span class="text-danger">*</span></label>
        <input type="text" name="bank_name" class="form-control" required />
    </div>
    
    <div class="form-group">
        <label>Bank Account Number <span class="text-danger">*</span></label>
        <input type="text" name="bank_account_number" class="form-control" required />
        <small class="form-text text-muted">
            Your banking information is encrypted and secure
        </small>
    </div>
    
    <!-- Categories -->
    <div class="form-group">
        <label>Product/Service Categories <span class="text-danger">*</span></label>
        <select name="categories" class="form-control" multiple required>
            <option value="office_supplies">Office Supplies</option>
            <option value="it_equipment">IT Equipment</option>
            <option value="cleaning_services">Cleaning Services</option>
            <option value="catering">Catering</option>
            <option value="consulting">Consulting</option>
            <option value="maintenance">Maintenance</option>
        </select>
        <small class="form-text text-muted">
            Hold Ctrl/Cmd to select multiple categories
        </small>
    </div>
    
    <!-- Certifications -->
    <div class="form-group">
        <label>Certifications (ISO, etc.)</label>
        <textarea 
            name="certifications" 
            class="form-control" 
            rows="3"
            placeholder="List any relevant certifications (ISO 9001, ISO 14001, etc.)"
        ></textarea>
    </div>
    
    <!-- Payment Terms -->
    <div class="form-group">
        <label>Requested Payment Terms <span class="text-danger">*</span></label>
        <select name="payment_terms_requested" class="form-control" required>
            <option value="">Select payment terms...</option>
            <option value="Net 30">Net 30</option>
            <option value="Net 60">Net 60</option>
            <option value="Net 90">Net 90</option>
        </select>
    </div>
    
    <!-- Submit Button -->
    <div class="form-actions mt-4">
        <button type="submit" class="btn btn-primary btn-lg">
            Submit Application
        </button>
    </div>
</form>
```

**User Fills Out Form and Clicks Submit:**

After submission, they see:

```html
<div class="alert alert-success">
    <i class="icon-check"></i>
    <strong>Application Submitted Successfully!</strong>
    <p>Thank you for submitting your application. Next, please upload the required documents.</p>
</div>
```

---

## STEP 2: Supplier Uploads Documents

**Automatically redirected to document upload page**

**What the Supplier Sees:**

```html
<div class="card">
    <div class="card-header bg-primary text-white">
        <h2>Upload Required Documents</h2>
        <p class="mb-0">Step 2 of 2</p>
    </div>
    
    <div class="card-body">
        <div class="alert alert-info">
            <strong>Required Documents:</strong>
            <ul class="mb-0">
                <li>Business Registration Certificate</li>
                <li>Tax Clearance Certificate</li>
                <li>Bank Reference Letter</li>
                <li>Insurance Certificate</li>
                <li>Product/Service Catalog</li>
            </ul>
        </div>
        
        {{-- File Upload Component --}}
        <div class="document-uploader">
            <div class="upload-zone" id="dropzone">
                <i class="icon-cloud-upload icon-4x text-muted"></i>
                <h4>Drag & Drop Files Here</h4>
                <p class="text-muted">or click to browse</p>
                <input type="file" multiple hidden id="file-input" />
                <button class="btn btn-outline-primary" onclick="$('#file-input').click()">
                    Browse Files
                </button>
            </div>
            
            <div class="file-requirements mt-3">
                <small class="text-muted">
                    <strong>Requirements:</strong> PDF, DOCX, JPG, or PNG. 
                    Max 10MB per file. Minimum 3 files required.
                </small>
            </div>
            
            <!-- Uploaded files list -->
            <div class="uploaded-files-list mt-4" id="uploaded-files">
                <!-- Dynamically populated as files are uploaded -->
            </div>
        </div>
        
        <div class="form-actions mt-4">
            <button 
                type="button" 
                class="btn btn-primary btn-lg" 
                id="submit-documents"
                disabled
            >
                Submit Documents
            </button>
        </div>
    </div>
</div>
```

**After Uploading Files:**

```html
<div class="uploaded-files-list">
    <div class="file-item">
        <i class="icon-file-pdf text-danger"></i>
        <span class="file-name">Business_Registration.pdf</span>
        <span class="file-size text-muted">2.4 MB</span>
        <button class="btn btn-sm btn-link text-danger" onclick="removeFile(1)">
            <i class="icon-trash"></i>
        </button>
    </div>
    
    <div class="file-item">
        <i class="icon-file-pdf text-danger"></i>
        <span class="file-name">Tax_Certificate.pdf</span>
        <span class="file-size text-muted">1.8 MB</span>
        <button class="btn btn-sm btn-link text-danger" onclick="removeFile(2)">
            <i class="icon-trash"></i>
        </button>
    </div>
    
    <div class="file-item">
        <i class="icon-file-pdf text-danger"></i>
        <span class="file-name">Bank_Reference.pdf</span>
        <span class="file-size text-muted">0.9 MB</span>
        <button class="btn btn-sm btn-link text-danger" onclick="removeFile(3)">
            <i class="icon-trash"></i>
        </button>
    </div>
</div>
```

**User Clicks "Submit Documents":**

```html
<div class="alert alert-success">
    <i class="icon-check-circle"></i>
    <h4>Documents Submitted Successfully!</h4>
    <p>
        Thank you for completing your supplier application. Our procurement 
        team will review your submission and contact you within 5-7 business days.
    </p>
    <p class="mb-0">
        <strong>Application Reference:</strong> SUP-2025-00123
    </p>
</div>
```

**At this point, the workflow is now paused, waiting for internal staff to review.**

---

## STEP 3: Procurement Specialist Reviews Application

### 3.1 Notification Email

**Sent to:** procurement@yourcompany.com

```
From: workflow@yourcompany.com
To: procurement@yourcompany.com
Subject: Action Required: Verify Supplier Application - Acme Corp

Hello Procurement Team,

A new supplier application requires verification.

Supplier: Acme Corp
Application Date: Dec 25, 2025
Reference: SUP-2025-00123

[Review Application] → https://yourcompany.com/approvals/456

This action is assigned to: Procurement Specialist role
Due: Dec 27, 2025

---
This is an automated notification from ForgePulse Workflow System
```

### 3.2 Procurement Specialist Logs In

**Navigation:** User logs into your existing application and sees notification badge

```blade
{{-- In your application's top navigation bar --}}
<nav class="navbar">
    <ul class="navbar-nav">
        <!-- Other nav items -->
        
        <li class="nav-item">
            <a href="/approvals" class="nav-link">
                <i class="icon-bell"></i>
                <span class="badge badge-danger">3</span> {{-- 3 pending actions --}}
            </a>
        </li>
    </ul>
</nav>
```

### 3.3 Viewing the Approvals/Actions Page

**URL:** `https://yourcompany.com/approvals`

**What They See:**

```blade
{{-- resources/views/approvals/index.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-header">
        <h1>My Action Items</h1>
        <p class="text-muted">Review and complete pending workflow actions</p>
    </div>
    
    {{-- ForgePulse User Action Inbox Component --}}
    <livewire:forgepulse::user-action-inbox 
        :userId="auth()->id()"
        :showFilters="true"
        :showSearch="true"
        :perPage="20"
    />
</div>
@endsection
```

**Rendered Inbox:**

```html
<div class="user-action-inbox">
    <!-- Filters -->
    <div class="inbox-filters mb-3">
        <button class="btn btn-sm btn-outline-primary active">All (3)</button>
        <button class="btn btn-sm btn-outline-primary">Approvals (1)</button>
        <button class="btn btn-sm btn-outline-primary">Forms (1)</button>
        <button class="btn btn-sm btn-outline-primary">Reviews (1)</button>
        <button class="btn btn-sm btn-outline-danger">Overdue (0)</button>
    </div>
    
    <!-- Search -->
    <div class="inbox-search mb-3">
        <input 
            type="text" 
            class="form-control" 
            placeholder="Search actions..."
        />
    </div>
    
    <!-- Action Cards -->
    <div class="action-cards">
        
        <!-- Card 1: Supplier Verification (This one!) -->
        <div class="action-card urgent">
            <div class="card-header">
                <div class="action-type-badge badge-warning">
                    <i class="icon-check-square"></i> Data Verification
                </div>
                <div class="action-workflow">
                    <small class="text-muted">Supplier Onboarding</small>
                </div>
            </div>
            
            <div class="card-body">
                <h4>Verify Supplier Information</h4>
                <p class="text-muted">Supplier: Acme Corp</p>
                
                <div class="action-meta">
                    <span class="badge badge-warning">
                        <i class="icon-clock"></i> Due in 2 days
                    </span>
                    <span class="text-muted">
                        <i class="icon-calendar"></i> Assigned Dec 25, 2025
                    </span>
                </div>
            </div>
            
            <div class="card-footer">
                <a href="/approvals/456" class="btn btn-primary">
                    Review Now
                </a>
            </div>
        </div>
        
        <!-- Card 2: Another action -->
        <div class="action-card">
            <div class="card-header">
                <div class="action-type-badge badge-success">
                    <i class="icon-thumbs-up"></i> Approval
                </div>
                <div class="action-workflow">
                    <small class="text-muted">Purchase Requisition</small>
                </div>
            </div>
            
            <div class="card-body">
                <h4>Approve Purchase Order PO-2025-123</h4>
                <p class="text-muted">Amount: $15,000 USD</p>
                
                <div class="action-meta">
                    <span class="badge badge-info">
                        <i class="icon-clock"></i> Due in 1 day
                    </span>
                </div>
            </div>
            
            <div class="card-footer">
                <a href="/approvals/457" class="btn btn-primary">
                    Review Now
                </a>
            </div>
        </div>
        
        <!-- Card 3: Another action -->
        <div class="action-card">
            <!-- ... -->
        </div>
        
    </div>
</div>
```

### 3.4 Clicking "Review Now" - Detail Page

**URL:** `https://yourcompany.com/approvals/456`

**What They See:**

```blade
{{-- resources/views/approvals/show.blade.php --}}

@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <!-- Main Content -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-warning text-white">
                    <h2 class="mb-0">Verify Supplier Information</h2>
                    <p class="mb-0">Supplier Onboarding Workflow</p>
                </div>
                
                <div class="card-body">
                    {{-- Show Context Data --}}
                    <div class="context-section mb-4">
                        <h4>Supplier Details</h4>
                        
                        <table class="table table-bordered">
                            <tr>
                                <th>Company Name:</th>
                                <td>Acme Corp</td>
                            </tr>
                            <tr>
                                <th>Tax ID:</th>
                                <td>12-3456789</td>
                            </tr>
                            <tr>
                                <th>Business Address:</th>
                                <td>
                                    123 Main Street<br>
                                    New York, NY 10001<br>
                                    United States
                                </td>
                            </tr>
                            <tr>
                                <th>Primary Contact:</th>
                                <td>
                                    John Smith<br>
                                    john@acmecorp.com<br>
                                    +1 (555) 123-4567
                                </td>
                            </tr>
                            <tr>
                                <th>Bank Name:</th>
                                <td>First National Bank</td>
                            </tr>
                            <tr>
                                <th>Account Number:</th>
                                <td>****6789 (masked)</td>
                            </tr>
                            <tr>
                                <th>Categories:</th>
                                <td>
                                    <span class="badge badge-secondary">Office Supplies</span>
                                    <span class="badge badge-secondary">IT Equipment</span>
                                </td>
                            </tr>
                            <tr>
                                <th>Certifications:</th>
                                <td>ISO 9001:2015, ISO 14001:2015</td>
                            </tr>
                            <tr>
                                <th>Payment Terms:</th>
                                <td>Net 30</td>
                            </tr>
                        </table>
                    </div>
                    
                    {{-- Show Uploaded Documents --}}
                    <div class="documents-section mb-4">
                        <h4>Uploaded Documents</h4>
                        
                        <div class="document-list">
                            <div class="document-item">
                                <i class="icon-file-pdf text-danger"></i>
                                <span>Business_Registration.pdf</span>
                                <a href="/download/doc1" class="btn btn-sm btn-link" target="_blank">
                                    <i class="icon-eye"></i> View
                                </a>
                            </div>
                            <div class="document-item">
                                <i class="icon-file-pdf text-danger"></i>
                                <span>Tax_Certificate.pdf</span>
                                <a href="/download/doc2" class="btn btn-sm btn-link" target="_blank">
                                    <i class="icon-eye"></i> View
                                </a>
                            </div>
                            <div class="document-item">
                                <i class="icon-file-pdf text-danger"></i>
                                <span>Bank_Reference.pdf</span>
                                <a href="/download/doc3" class="btn btn-sm btn-link" target="_blank">
                                    <i class="icon-eye"></i> View
                                </a>
                            </div>
                            <div class="document-item">
                                <i class="icon-file-pdf text-danger"></i>
                                <span>Insurance_Certificate.pdf</span>
                                <a href="/download/doc4" class="btn btn-sm btn-link" target="_blank">
                                    <i class="icon-eye"></i> View
                                </a>
                            </div>
                            <div class="document-item">
                                <i class="icon-file-pdf text-danger"></i>
                                <span>Product_Catalog.pdf</span>
                                <a href="/download/doc5" class="btn btn-sm btn-link" target="_blank">
                                    <i class="icon-eye"></i> View
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    {{-- The Verification Form (UserAction Form) --}}
                    <div class="verification-form">
                        <h4>Verification Checklist</h4>
                        
                        <livewire:forgepulse::user-action-form 
                            :userAction="$userAction"
                            :showContext="false"
                        />
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Sidebar -->
        <div class="col-md-4">
            <!-- Workflow Timeline -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">Workflow Progress</h5>
                </div>
                <div class="card-body">
                    <livewire:forgepulse::workflow-timeline 
                        :execution="$userAction->workflowExecution"
                        :compactMode="true"
                    />
                </div>
            </div>
            
            <!-- Actions -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <button class="btn btn-secondary btn-block mb-2">
                        <i class="icon-user"></i> Delegate
                    </button>
                    <button class="btn btn-outline-secondary btn-block">
                        <i class="icon-comment"></i> Add Comment
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
```

**The Verification Form (rendered from UserAction config):**

```html
<form class="verification-checklist-form">
    <div class="alert alert-info">
        <strong>Instructions:</strong> Please verify all information matches the 
        submitted documents. Check each item below.
    </div>
    
    <div class="checklist">
        <div class="form-check">
            <input 
                type="checkbox" 
                class="form-check-input" 
                id="check1"
                name="checks[]"
                value="company_matches"
                required
            />
            <label class="form-check-label" for="check1">
                Company name matches registration documents
            </label>
        </div>
        
        <div class="form-check">
            <input 
                type="checkbox" 
                class="form-check-input" 
                id="check2"
                name="checks[]"
                value="tax_id_valid"
                required
            />
            <label class="form-check-label" for="check2">
                Tax ID is valid
            </label>
        </div>
        
        <div class="form-check">
            <input 
                type="checkbox" 
                class="form-check-input" 
                id="check3"
                name="checks[]"
                value="bank_verified"
                required
            />
            <label class="form-check-label" for="check3">
                Bank details verified
            </label>
        </div>
        
        <div class="form-check">
            <input 
                type="checkbox" 
                class="form-check-input" 
                id="check4"
                name="checks[]"
                value="contact_confirmed"
                required
            />
            <label class="form-check-label" for="check4">
                Contact information confirmed
            </label>
        </div>
        
        <div class="form-check">
            <input 
                type="checkbox" 
                class="form-check-input" 
                id="check5"
                name="checks[]"
                value="documents_uploaded"
                required
            />
            <label class="form-check-label" for="check5">
                All required documents uploaded
            </label>
        </div>
        
        <div class="form-check">
            <input 
                type="checkbox" 
                class="form-check-input" 
                id="check6"
                name="checks[]"
                value="documents_current"
                required
            />
            <label class="form-check-label" for="check6">
                Documents are current and valid
            </label>
        </div>
    </div>
    
    <!-- Notes -->
    <div class="form-group mt-4">
        <label>Verification Notes (Optional)</label>
        <textarea 
            name="notes" 
            class="form-control" 
            rows="3"
            placeholder="Add any notes about this verification..."
        ></textarea>
    </div>
    
    <!-- Decision Buttons -->
    <div class="form-actions mt-4">
        <button type="submit" name="decision" value="verified" class="btn btn-success btn-lg">
            <i class="icon-check"></i> Verified - Proceed
        </button>
        <button type="submit" name="decision" value="request_corrections" class="btn btn-warning btn-lg">
            <i class="icon-edit"></i> Request Corrections
        </button>
    </div>
</form>
```

**User Checks All Boxes and Clicks "Verified - Proceed":**

```html
<div class="alert alert-success">
    <i class="icon-check-circle"></i>
    <strong>Verification Complete!</strong>
    <p>The supplier application has been verified and will now proceed to credit assessment.</p>
</div>
```

**The page refreshes and the action is removed from their inbox.**

---

## STEP 4: Credit Controller Reviews

### 4.1 Credit Controller Receives Notification

Similar email notification sent to credit@yourcompany.com

### 4.2 Credit Controller Views Action

**In their inbox, they see:**

```html
<div class="action-card">
    <div class="card-header">
        <div class="action-type-badge badge-info">
            <i class="icon-edit"></i> Form Submission
        </div>
        <div class="action-workflow">
            <small class="text-muted">Supplier Onboarding</small>
        </div>
    </div>
    
    <div class="card-body">
        <h4>Credit Assessment</h4>
        <p class="text-muted">Supplier: Acme Corp</p>
    </div>
    
    <div class="card-footer">
        <a href="/approvals/458" class="btn btn-primary">Review Now</a>
    </div>
</div>
```

### 4.3 Credit Assessment Form

**When they click "Review Now":**

```html
<div class="card">
    <div class="card-header bg-info text-white">
        <h2>Credit Assessment - Acme Corp</h2>
    </div>
    
    <div class="card-body">
        <!-- Context: Supplier info displayed -->
        
        <!-- Credit Assessment Form -->
        <form class="credit-assessment-form">
            <div class="form-group">
                <label>Credit Score <span class="text-danger">*</span></label>
                <input 
                    type="number" 
                    name="credit_score" 
                    class="form-control" 
                    placeholder="e.g., 720"
                    min="300" 
                    max="850"
                    required
                />
            </div>
            
            <div class="form-group">
                <label>Credit Rating <span class="text-danger">*</span></label>
                <select name="credit_rating" class="form-control" required>
                    <option value="">Select rating...</option>
                    <option value="Excellent">Excellent</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Recommended Credit Limit <span class="text-danger">*</span></label>
                <div class="input-group">
                    <div class="input-group-prepend">
                        <span class="input-group-text">$</span>
                    </div>
                    <input 
                        type="number" 
                        name="recommended_credit_limit" 
                        class="form-control"
                        placeholder="50000"
                        required
                    />
                </div>
            </div>
            
            <div class="form-group">
                <label>Approved Payment Terms <span class="text-danger">*</span></label>
                <select name="payment_terms_approved" class="form-control" required>
                    <option value="">Select payment terms...</option>
                    <option value="Net 30">Net 30</option>
                    <option value="Net 60">Net 60</option>
                    <option value="Net 90">Net 90</option>
                    <option value="Prepayment Only">Prepayment Only</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Credit Assessment Notes</label>
                <textarea 
                    name="credit_notes" 
                    class="form-control" 
                    rows="4"
                    placeholder="Notes about credit assessment..."
                ></textarea>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    Submit Assessment
                </button>
            </div>
        </form>
    </div>
</div>
```

---

## STEP 5: Parallel Compliance & Legal Review

### 5.1 Two Users Receive Notifications Simultaneously

**Email sent to:**
- compliance@yourcompany.com
- legal@yourcompany.com

### 5.2 Both See the Action in Their Inbox

**Compliance Officer's Inbox:**

```html
<div class="action-card">
    <div class="card-header">
        <div class="action-type-badge badge-success">
            <i class="icon-thumbs-up"></i> Approval
        </div>
        <div class="action-workflow">
            <small class="text-muted">Supplier Onboarding</small>
        </div>
    </div>
    
    <div class="card-body">
        <h4>Supplier Compliance & Legal Review</h4>
        <p class="text-muted">Supplier: Acme Corp</p>
        
        <div class="alert alert-warning">
            <i class="icon-users"></i> 
            <strong>Parallel Approval:</strong> Both compliance and legal approval required
        </div>
    </div>
    
    <div class="card-footer">
        <a href="/approvals/459" class="btn btn-primary">Review Now</a>
    </div>
</div>
```

**Legal Counsel sees the same action (ID 460) in their inbox.**

### 5.3 Approval Interface

**When either user clicks "Review Now":**

```html
<div class="card">
    <div class="card-header bg-success text-white">
        <h2>Compliance & Legal Review</h2>
        <p class="mb-0">Supplier Onboarding - Acme Corp</p>
    </div>
    
    <div class="card-body">
        <!-- Show all supplier context -->
        <div class="context-section mb-4">
            <!-- Supplier details, credit rating, etc. -->
        </div>
        
        <!-- Compliance Checklist -->
        <div class="compliance-section mb-4">
            <h4>Compliance Checklist</h4>
            <div class="checklist">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="comp1" />
                    <label class="form-check-label" for="comp1">
                        No sanctions or embargoes
                    </label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="comp2" />
                    <label class="form-check-label" for="comp2">
                        Anti-bribery compliance verified
                    </label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="comp3" />
                    <label class="form-check-label" for="comp3">
                        Data protection compliance (GDPR, etc.)
                    </label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="comp4" />
                    <label class="form-check-label" for="comp4">
                        Industry-specific regulations met
                    </label>
                </div>
            </div>
        </div>
        
        <!-- Decision Form -->
        <form class="approval-form">
            <div class="form-group">
                <label>Decision <span class="text-danger">*</span></label>
                <div class="btn-group btn-group-toggle d-block" data-toggle="buttons">
                    <label class="btn btn-outline-success">
                        <input type="radio" name="decision" value="approve" required />
                        <i class="icon-check"></i> Approve
                    </label>
                    <label class="btn btn-outline-danger">
                        <input type="radio" name="decision" value="reject" required />
                        <i class="icon-times"></i> Reject
                    </label>
                    <label class="btn btn-outline-warning">
                        <input type="radio" name="decision" value="request_additional_info" />
                        <i class="icon-info-circle"></i> Request Additional Info
                    </label>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes <span class="text-danger">*</span></label>
                <textarea 
                    name="notes" 
                    class="form-control" 
                    rows="4"
                    placeholder="Provide notes for your decision..."
                    required
                ></textarea>
            </div>
            
            <div class="form-actions mt-4">
                <button type="submit" class="btn btn-primary btn-lg">
                    Submit Decision
                </button>
            </div>
        </form>
    </div>
</div>
```

**Both users approve independently:**

```html
<!-- After Compliance Officer approves -->
<div class="alert alert-success">
    <i class="icon-check"></i>
    <strong>Decision Submitted!</strong>
    <p>You have approved this supplier application.</p>
    <p class="mb-0">
        <small class="text-muted">
            Waiting for legal counsel approval to proceed...
        </small>
    </p>
</div>

<!-- After Legal Counsel also approves -->
<div class="alert alert-success">
    <i class="icon-check"></i>
    <strong>Decision Submitted!</strong>
    <p>You have approved this supplier application.</p>
    <p class="mb-0">
        <strong>All approvals received!</strong> The workflow will now proceed to final approval.
    </p>
</div>
```

---

## STEP 6: Procurement Manager Final Approval

### 6.1 Manager Receives Notification

### 6.2 Manager Views Summary

**In their inbox:**

```html
<div class="action-card urgent-priority">
    <div class="card-header">
        <div class="action-type-badge badge-primary">
            <i class="icon-star"></i> Final Approval
        </div>
        <div class="action-workflow">
            <small class="text-muted">Supplier Onboarding</small>
        </div>
    </div>
    
    <div class="card-body">
        <h4>Final Supplier Onboarding Approval</h4>
        <p class="text-muted">Supplier: Acme Corp</p>
        
        <div class="progress-indicators">
            <span class="badge badge-success">
                <i class="icon-check"></i> Verified
            </span>
            <span class="badge badge-success">
                <i class="icon-check"></i> Credit: Good
            </span>
            <span class="badge badge-success">
                <i class="icon-check"></i> Compliance Approved
            </span>
            <span class="badge badge-success">
                <i class="icon-check"></i> Legal Approved
            </span>
        </div>
    </div>
    
    <div class="card-footer">
        <a href="/approvals/461" class="btn btn-primary">
            Review for Final Approval
        </a>
    </div>
</div>
```

### 6.3 Final Approval Page

```html
<div class="card">
    <div class="card-header bg-primary text-white">
        <h2>Final Approval - Acme Corp</h2>
        <p class="mb-0">All reviews completed</p>
    </div>
    
    <div class="card-body">
        <!-- Full Summary -->
        <div class="approval-summary">
            <h4>Application Summary</h4>
            
            <div class="row">
                <div class="col-md-6">
                    <h5>Company Information</h5>
                    <dl>
                        <dt>Company Name:</dt>
                        <dd>Acme Corp</dd>
                        
                        <dt>Tax ID:</dt>
                        <dd>12-3456789</dd>
                        
                        <dt>Categories:</dt>
                        <dd>Office Supplies, IT Equipment</dd>
                    </dl>
                </div>
                
                <div class="col-md-6">
                    <h5>Credit Assessment</h5>
                    <dl>
                        <dt>Credit Rating:</dt>
                        <dd><span class="badge badge-success">Good</span></dd>
                        
                        <dt>Credit Limit:</dt>
                        <dd>$50,000</dd>
                        
                        <dt>Payment Terms:</dt>
                        <dd>Net 30</dd>
                    </dl>
                </div>
            </div>
            
            <h5 class="mt-4">Review History</h5>
            <div class="timeline">
                <div class="timeline-item">
                    <i class="icon-check text-success"></i>
                    <strong>Procurement Verification</strong>
                    <br>
                    <small class="text-muted">
                        Verified by Sarah Johnson on Dec 25, 2025 at 10:30 AM
                    </small>
                </div>
                
                <div class="timeline-item">
                    <i class="icon-check text-success"></i>
                    <strong>Credit Assessment</strong>
                    <br>
                    <small class="text-muted">
                        Completed by Mike Chen on Dec 25, 2025 at 2:15 PM<br>
                        Rating: Good | Limit: $50,000
                    </small>
                </div>
                
                <div class="timeline-item">
                    <i class="icon-check text-success"></i>
                    <strong>Compliance Review</strong>
                    <br>
                    <small class="text-muted">
                        Approved by Lisa Anderson on Dec 26, 2025 at 9:00 AM
                    </small>
                </div>
                
                <div class="timeline-item">
                    <i class="icon-check text-success"></i>
                    <strong>Legal Review</strong>
                    <br>
                    <small class="text-muted">
                        Approved by Robert Taylor on Dec 26, 2025 at 10:45 AM
                    </small>
                </div>
            </div>
        </div>
        
        <!-- Final Decision Form -->
        <div class="final-approval-form mt-4">
            <form>
                <div class="form-group">
                    <label>Final Decision <span class="text-danger">*</span></label>
                    <div class="btn-group btn-group-lg d-block" data-toggle="buttons">
                        <label class="btn btn-outline-success btn-lg">
                            <input type="radio" name="decision" value="approve" required />
                            <i class="icon-check"></i> Approve Supplier
                        </label>
                        <label class="btn btn-outline-danger btn-lg">
                            <input type="radio" name="decision" value="reject" />
                            <i class="icon-times"></i> Reject
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Notes</label>
                    <textarea 
                        name="notes" 
                        class="form-control" 
                        rows="3"
                        placeholder="Optional notes..."
                    ></textarea>
                </div>
                
                <div class="form-actions mt-4">
                    <button type="submit" class="btn btn-success btn-lg btn-block">
                        <i class="icon-check"></i> Submit Final Decision
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
```

**Manager Clicks "Approve Supplier":**

```html
<div class="alert alert-success">
    <i class="icon-check-circle"></i>
    <h4>Supplier Approved!</h4>
    <p>
        Acme Corp has been approved and will now be created in the system.
        A welcome email will be sent to the supplier.
    </p>
</div>
```

---

## STEP 7 & 8: Automated System Actions

### System automatically:

1. **Creates supplier record in database**
2. **Sends welcome email to supplier**

**Email to supplier@acmecorp.com:**

```
From: procurement@yourcompany.com
To: supplier@acmecorp.com
Subject: Welcome to YourCompany Supplier Network!

Hello Acme Corp,

Congratulations! Your supplier application has been approved.

You are now an approved supplier for YourCompany.

Supplier ID: SUP-00123
Credit Limit: $50,000
Payment Terms: Net 30

Next Steps:
1. Log in to the supplier portal: https://suppliers.yourcompany.com
2. Complete your product catalog
3. Set up your payment details

Your login credentials will be sent in a separate email.

If you have any questions, please contact our procurement team.

Welcome aboard!

YourCompany Procurement Team
```

---

## Summary: Complete User Journey

### Timeline View

```
Day 1 (Dec 25):
├─ 09:00 → Manager initiates workflow
├─ 09:01 → Supplier receives email
├─ 09:30 → Supplier submits application form
├─ 09:45 → Supplier uploads documents
├─ 10:00 → Procurement specialist receives notification
├─ 10:30 → Procurement specialist verifies and approves
├─ 14:00 → Credit controller receives notification
└─ 14:30 → Credit assessment completed

Day 2 (Dec 26):
├─ 09:00 → Compliance officer approves
├─ 10:45 → Legal counsel approves
├─ 11:00 → Procurement manager receives notification
├─ 11:30 → Procurement manager gives final approval
├─ 11:31 → System creates supplier record
└─ 11:32 → Welcome email sent to supplier
```

### User Interfaces Used

1. **Supplier** (External):
   - Public application form page
   - Document upload page
   - Receives emails

2. **Internal Staff**:
   - Existing application with embedded workflow components
   - `/approvals` - User action inbox
   - `/approvals/{id}` - Detail pages for each action
   - Email notifications with direct links
   - In-app notification badges

3. **Manager**:
   - Dashboard with pending actions widget
   - Full approval inbox
   - Workflow timeline visibility

---

## Key Integration Points in Your Existing App

### 1. Top Navigation (Notification Badge)

```blade
<li class="nav-item">
    <a href="/approvals" class="nav-link">
        <i class="icon-bell"></i>
        @if(auth()->user()->pendingActionsCount() > 0)
            <span class="badge badge-danger">
                {{ auth()->user()->pendingActionsCount() }}
            </span>
        @endif
    </a>
</li>
```

### 2. Dashboard Widget

```blade
<div class="dashboard-widget">
    <h3>Pending Actions</h3>
    <livewire:forgepulse::user-action-inbox 
        :userId="auth()->id()"
        :limit="5"
        theme="compact"
    />
</div>
```

### 3. Dedicated Approvals Page

```blade
{{-- routes/web.php --}}
Route::get('/approvals', function() {
    return view('approvals.index');
})->middleware('auth');

{{-- resources/views/approvals/index.blade.php --}}
<livewire:forgepulse::user-action-inbox :userId="auth()->id()" />
```

### 4. Detail Page

```blade
{{-- routes/web.php --}}
Route::get('/approvals/{userAction}', function(UserAction $userAction) {
    return view('approvals.show', compact('userAction'));
})->middleware('auth');

{{-- resources/views/approvals/show.blade.php --}}
<livewire:forgepulse::user-action-form :userAction="$userAction" />
```

---

## Mobile Experience

### Push Notification

```
📱 Notification appears on phone:
────────────────────────────
Action Required
Verify Supplier Information
Supplier Onboarding • Due in 2 days
Tap to review →
────────────────────────────
```

### Mobile App Screen

```
┌─────────────────────────────────┐
│ ← My Actions              [···] │
├─────────────────────────────────┤
│                                 │
│ ┌─────────────────────────────┐ │
│ │ 🔔 Verification             │ │
│ │ Verify Supplier Information │ │
│ │ Supplier Onboarding         │ │
│ │                             │ │
│ │ ⚠️ Due in 2 days            │ │
│ │ Assigned Dec 25, 2025       │ │
│ │                             │ │
│ │        [Review Now]         │ │
│ └─────────────────────────────┘ │
│                                 │
│ ┌─────────────────────────────┐ │
│ │ ✅ Approval                 │ │
│ │ Approve PO-2025-123         │ │
│ │ $15,000 USD                 │ │
│ └─────────────────────────────┘ │
│                                 │
└─────────────────────────────────┘
```

---

**End of Document**

This is the actual user experience - no abstract concepts, just what users see and click!
