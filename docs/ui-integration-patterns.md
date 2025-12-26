# Integrating Workflow UIs into Existing Applications

**Version:** 1.0  
**Date:** 2025-12-25  
**Context:** UI Integration Patterns for ForgePulse UserAction Workflows

---

## Executive Summary

This document outlines practical patterns for integrating ForgePulse workflow UIs into existing applications, whether they're Laravel Blade apps, React SPAs, Vue applications, or mobile apps. It covers both embedded components and API-driven approaches.

---

## Table of Contents

1. [Integration Approaches](#1-integration-approaches)
2. [Livewire Components (Laravel)](#2-livewire-components-laravel)
3. [REST API Integration](#3-rest-api-integration)
4. [React/Vue Components](#4-reactvue-components)
5. [Mobile Integration](#5-mobile-integration)
6. [Iframe Embedding](#6-iframe-embedding)
7. [Form Schema Rendering](#7-form-schema-rendering)
8. [Notification Integration](#8-notification-integration)
9. [Real-time Updates](#9-real-time-updates)
10. [Customization & Theming](#10-customization--theming)

---

## 1. Integration Approaches

### 1.1 Integration Strategy Matrix

| Approach | Use Case | Pros | Cons |
|----------|----------|------|------|
| **Livewire Components** | Laravel Blade apps | Native integration, reactive, minimal JS | Laravel-only |
| **REST API + Custom UI** | Any frontend framework | Full control, framework-agnostic | More development work |
| **React/Vue Components** | SPA applications | Rich interactivity, reusable | Requires build process |
| **Iframe Embedding** | Quick integration | Isolation, minimal code | Limited customization |
| **Mobile SDK** | Native mobile apps | Native experience | Platform-specific code |
| **Webhooks + Push** | External systems | Async, decoupled | Requires webhook endpoint |

### 1.2 Decision Tree

```
Do you use Laravel with Blade?
├─ YES → Use Livewire Components (Section 2)
└─ NO → Continue

Is your frontend a SPA (React/Vue/Angular)?
├─ YES → Use REST API + Components (Sections 3-4)
└─ NO → Continue

Is this a mobile app?
├─ YES → Use REST API + Native Components (Section 5)
└─ NO → Consider Iframe (Section 6)
```

---

## 2. Livewire Components (Laravel)

### 2.1 Architecture

```
┌─────────────────────────────────────────────────────┐
│              Your Laravel Application                │
│  ┌───────────────────────────────────────────────┐  │
│  │         Existing Blade Views/Pages            │  │
│  │                                               │  │
│  │  ┌─────────────────────────────────────────┐ │  │
│  │  │  <livewire:user-action-inbox />         │ │  │
│  │  │  <livewire:user-action-form :action=""/> │ │  │
│  │  │  <livewire:approval-card :action=""/>    │ │  │
│  │  └─────────────────────────────────────────┘ │  │
│  │                                               │  │
│  └───────────────────────────────────────────────┘  │
│                         ▲                            │
│                         │                            │
│  ┌───────────────────────────────────────────────┐  │
│  │       ForgePulse Livewire Components          │  │
│  │  - UserActionInbox                            │  │
│  │  - UserActionForm                             │  │
│  │  - ApprovalCard                               │  │
│  │  - WorkflowTimeline                           │  │
│  └───────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────┘
```

### 2.2 Example: Embedding User Action Inbox

#### In Your Existing Blade View

```blade
{{-- resources/views/dashboard.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <h1>My Dashboard</h1>
    
    {{-- Your existing dashboard content --}}
    <div class="row">
        <div class="col-md-8">
            {{-- Your widgets --}}
            <div class="card">
                <div class="card-header">Recent Activity</div>
                <div class="card-body">
                    <!-- Your content -->
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            {{-- Embed ForgePulse User Action Inbox --}}
            <livewire:forgepulse::user-action-inbox 
                :userId="auth()->id()"
                :limit="5"
                :showFilters="false"
                theme="compact"
            />
        </div>
    </div>
</div>
@endsection
```

#### Standalone Approval Page

```blade
{{-- resources/views/approvals/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container">
    <div class="page-header">
        <h1>Pending Approvals</h1>
        <p class="text-muted">Review and approve pending requests</p>
    </div>
    
    {{-- Full-featured inbox with filters --}}
    <livewire:forgepulse::user-action-inbox 
        :userId="auth()->id()"
        :actionTypes="['approval']"
        :showFilters="true"
        :showSearch="true"
        :perPage="20"
    />
</div>
@endsection
```

#### Inline Approval Card

```blade
{{-- In your requisition detail page --}}
@if($requisition->needsMyApproval())
    <div class="approval-section">
        <h3>Your Approval Required</h3>
        
        <livewire:forgepulse::approval-card 
            :userAction="$requisition->pendingApprovalForUser(auth()->id())"
            :showContext="true"
            :compactMode="false"
            @approved="handleApproved"
            @rejected="handleRejected"
        />
    </div>
@endif

@push('scripts')
<script>
    Livewire.on('approved', (data) => {
        // Refresh page or show success message
        toastr.success('Requisition approved successfully');
        setTimeout(() => window.location.reload(), 1000);
    });
    
    Livewire.on('rejected', (data) => {
        toastr.info('Requisition rejected');
        setTimeout(() => window.location.reload(), 1000);
    });
</script>
@endpush
```

### 2.3 Dynamic Form Rendering

```blade
{{-- User action form dynamically renders based on action_type --}}
<livewire:forgepulse::user-action-form 
    :userAction="$userAction"
    :showTitle="true"
    :showDescription="true"
    :validationMode="'client'"
/>

{{-- This single component handles:
     - Approval forms (approve/reject buttons)
     - Data entry forms (dynamic fields based on schema)
     - Document uploads (drag-drop interface)
     - Reviews/ratings (star ratings, feedback)
     - Confirmations (checklists, confirmation phrases)
--}}
```

### 2.4 Livewire Component Properties

```php
// UserActionInbox Component
<livewire:forgepulse::user-action-inbox 
    :userId="auth()->id()"              // Filter by user
    :actionTypes="['approval']"          // Filter by action types
    :status="['pending']"                // Filter by status
    :limit="10"                          // Number of items to show
    :showFilters="true"                  // Show filter UI
    :showSearch="true"                   // Show search box
    :perPage="20"                        // Pagination
    :sortBy="'due_at'"                   // Default sort field
    :sortOrder="'asc'"                   // Sort direction
    :theme="'default'"                   // Theme: default, compact, card
    :groupBy="null"                      // Group by: type, workflow, date
    :showDelegateButton="true"           // Show delegation options
    :showBulkActions="true"              // Enable bulk approve/reject
/>

// UserActionForm Component
<livewire:forgepulse::user-action-form 
    :userAction="$userAction"            // The user action instance
    :showTitle="true"                    // Show form title
    :showDescription="true"              // Show description
    :showContext="true"                  // Show workflow context data
    :validationMode="'client'"           // client, server, or both
    :submitButtonText="'Submit'"         // Custom button text
    :cancelUrl="route('dashboard')"      // Cancel redirect URL
    :successRedirect="route('approvals')" // Success redirect
    :theme="'default'"                   // Form theme
/>

// ApprovalCard Component
<livewire:forgepulse::approval-card 
    :userAction="$userAction"
    :compactMode="false"                 // Compact vs full view
    :showContext="true"                  // Show approval context
    :showTimeline="false"                // Show approval history
    :allowDelegate="true"                // Show delegate button
    :customButtons="[]"                  // Add custom action buttons
/>

// WorkflowTimeline Component
<livewire:forgepulse::workflow-timeline 
    :execution="$execution"              // WorkflowExecution instance
    :showUserActions="true"              // Highlight user action steps
    :compactMode="false"                 // Compact vs full timeline
    :expandable="true"                   // Allow expanding step details
/>
```

### 2.5 Component Events

```blade
{{-- Listen to component events --}}
<livewire:forgepulse::user-action-form 
    :userAction="$userAction"
    wire:loading.class="opacity-50"
/>

@push('scripts')
<script>
    // Success events
    Livewire.on('actionSubmitted', (data) => {
        console.log('Action submitted', data);
    });
    
    Livewire.on('approvalGranted', (data) => {
        // Handle approval
        showNotification('Approved successfully');
    });
    
    Livewire.on('approvalRejected', (data) => {
        // Handle rejection
        showNotification('Rejected', data.reason);
    });
    
    Livewire.on('formValidationError', (errors) => {
        // Handle validation errors
        console.error('Validation errors', errors);
    });
    
    // Delegation events
    Livewire.on('actionDelegated', (data) => {
        showNotification(`Delegated to ${data.delegateUserName}`);
    });
</script>
@endpush
```

---

## 3. REST API Integration

### 3.1 API Architecture

```
┌─────────────────────────────────────────────────────┐
│           Your Application (Any Framework)           │
│                                                       │
│  ┌─────────────────────────────────────────────┐    │
│  │         Your Frontend Components             │    │
│  │  (React, Vue, Angular, Vanilla JS, Mobile)  │    │
│  └─────────────────────────────────────────────┘    │
│                      │                                │
│                      │ HTTP/HTTPS                     │
│                      ▼                                │
│  ┌─────────────────────────────────────────────┐    │
│  │       ForgePulse REST API Layer              │    │
│  │  GET  /api/forgepulse/user-actions/pending  │    │
│  │  POST /api/forgepulse/user-actions/{id}/respond│ │
│  │  POST /api/forgepulse/user-actions/{id}/delegate│││
│  └─────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────┘
```

### 3.2 API Endpoints Reference

#### Get Pending Actions

```http
GET /api/forgepulse/user-actions/pending?per_page=20&action_type=approval
Authorization: Bearer {token}

Response 200:
{
  "data": [
    {
      "id": 123,
      "workflow_execution_id": 456,
      "workflow_step_id": 789,
      "action_type": "approval",
      "status": "pending",
      "assigned_at": "2025-12-25T10:00:00Z",
      "due_at": "2025-12-26T10:00:00Z",
      "workflow": {
        "id": 10,
        "name": "Purchase Order Approval"
      },
      "action_config": {
        "title": "Approve PO-2025-001",
        "description": "Please review and approve this purchase request",
        "decisions": ["approve", "reject"],
        "require_notes_on_reject": true,
        "show_context": ["amount", "supplier", "items"]
      },
      "context_data": {
        "po_number": "PO-2025-001",
        "amount": 15000,
        "currency": "USD",
        "supplier": "Acme Corp",
        "items": [...]
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 45
  }
}
```

#### Get Single User Action

```http
GET /api/forgepulse/user-actions/123
Authorization: Bearer {token}

Response 200:
{
  "id": 123,
  "action_type": "form_submission",
  "status": "pending",
  "action_config": {
    "title": "Purchase Requisition Form",
    "form_schema": {
      "fields": [
        {
          "name": "business_justification",
          "type": "textarea",
          "label": "Business Justification",
          "required": true,
          "validation": "min:50",
          "placeholder": "Explain why this purchase is necessary..."
        },
        {
          "name": "cost_center",
          "type": "select",
          "label": "Cost Center",
          "options": [
            {"value": "IT", "label": "Information Technology"},
            {"value": "MKT", "label": "Marketing"}
          ],
          "required": true
        }
      ]
    }
  }
}
```

#### Submit Response

```http
POST /api/forgepulse/user-actions/123/respond
Authorization: Bearer {token}
Content-Type: application/json

{
  "response_data": {
    "decision": "approve",
    "custom_field": "value"
  },
  "notes": "Approved - within budget"
}

Response 200:
{
  "success": true,
  "message": "User action completed successfully",
  "data": {
    "id": 123,
    "status": "completed",
    "completed_at": "2025-12-25T14:30:00Z"
  }
}
```

#### Delegate Action

```http
POST /api/forgepulse/user-actions/123/delegate
Authorization: Bearer {token}
Content-Type: application/json

{
  "delegate_to_user_id": 456,
  "reason": "On vacation until Jan 10"
}

Response 200:
{
  "success": true,
  "message": "User action delegated successfully",
  "data": {
    "original_action_id": 123,
    "new_action_id": 124,
    "delegated_to": {
      "id": 456,
      "name": "Jane Smith",
      "email": "jane@company.com"
    }
  }
}
```

#### Upload Files

```http
POST /api/forgepulse/user-actions/123/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

files[]: (binary)
files[]: (binary)

Response 200:
{
  "success": true,
  "uploaded_files": [
    {
      "id": 1,
      "file_name": "invoice.pdf",
      "file_path": "user-actions/123/invoice.pdf",
      "file_size": 204800
    }
  ]
}
```

### 3.3 API Client Example (JavaScript)

```javascript
// api/forgepulse-client.js

class ForgePulseClient {
  constructor(baseUrl, token) {
    this.baseUrl = baseUrl;
    this.token = token;
  }

  async getPendingActions(filters = {}) {
    const params = new URLSearchParams(filters);
    const response = await fetch(
      `${this.baseUrl}/api/forgepulse/user-actions/pending?${params}`,
      {
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/json',
        }
      }
    );
    return response.json();
  }

  async getUserAction(actionId) {
    const response = await fetch(
      `${this.baseUrl}/api/forgepulse/user-actions/${actionId}`,
      {
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/json',
        }
      }
    );
    return response.json();
  }

  async submitResponse(actionId, responseData, notes = null) {
    const response = await fetch(
      `${this.baseUrl}/api/forgepulse/user-actions/${actionId}/respond`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({ response_data: responseData, notes })
      }
    );
    return response.json();
  }

  async delegateAction(actionId, delegateToUserId, reason = null) {
    const response = await fetch(
      `${this.baseUrl}/api/forgepulse/user-actions/${actionId}/delegate`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          delegate_to_user_id: delegateToUserId,
          reason
        })
      }
    );
    return response.json();
  }

  async uploadFiles(actionId, files) {
    const formData = new FormData();
    files.forEach((file, index) => {
      formData.append(`files[${index}]`, file);
    });

    const response = await fetch(
      `${this.baseUrl}/api/forgepulse/user-actions/${actionId}/upload`,
      {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/json',
        },
        body: formData
      }
    );
    return response.json();
  }
}

export default ForgePulseClient;
```

---

## 4. React/Vue Components

### 4.1 React Component Architecture

```
┌────────────────────────────────────────────────────┐
│              React Application                      │
│                                                     │
│  ┌──────────────────────────────────────────────┐ │
│  │         <App />                              │ │
│  │  ┌────────────────────────────────────────┐ │ │
│  │  │  <Dashboard />                         │ │ │
│  │  │    <UserActionInbox />                 │ │ │
│  │  │    <UserActionCard />                  │ │ │
│  │  └────────────────────────────────────────┘ │ │
│  │  ┌────────────────────────────────────────┐ │ │
│  │  │  <ApprovalPage />                      │ │ │
│  │  │    <UserActionForm />                  │ │ │
│  │  │    <WorkflowTimeline />                │ │ │
│  │  └────────────────────────────────────────┘ │ │
│  └──────────────────────────────────────────────┘ │
│                                                     │
│  ┌──────────────────────────────────────────────┐ │
│  │      ForgePulse React Components             │ │
│  │  - UserActionInbox                           │ │
│  │  - UserActionForm (dynamic renderer)         │ │
│  │  - ApprovalCard                              │ │
│  │  - FormFieldRenderer                         │ │
│  └──────────────────────────────────────────────┘ │
└────────────────────────────────────────────────────┘
```

### 4.2 React Example: User Action Inbox

```jsx
// components/UserActionInbox.jsx
import React, { useEffect, useState } from 'react';
import { ForgePulseClient } from '../api/forgepulse-client';
import UserActionCard from './UserActionCard';

const UserActionInbox = ({ 
  userId, 
  actionTypes = null, 
  limit = 10,
  onActionComplete = null 
}) => {
  const [actions, setActions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('all');
  
  const client = new ForgePulseClient(
    process.env.REACT_APP_API_URL,
    localStorage.getItem('auth_token')
  );

  useEffect(() => {
    loadActions();
  }, [filter, actionTypes]);

  const loadActions = async () => {
    setLoading(true);
    try {
      const filters = { 
        status: filter === 'all' ? null : filter,
        action_type: actionTypes,
        per_page: limit 
      };
      const response = await client.getPendingActions(filters);
      setActions(response.data);
    } catch (error) {
      console.error('Failed to load actions', error);
    } finally {
      setLoading(false);
    }
  };

  const handleActionComplete = (actionId) => {
    // Remove from list
    setActions(actions.filter(a => a.id !== actionId));
    
    // Call parent callback
    if (onActionComplete) {
      onActionComplete(actionId);
    }
  };

  if (loading) {
    return <div className="spinner">Loading pending actions...</div>;
  }

  return (
    <div className="user-action-inbox">
      <div className="inbox-header">
        <h2>Pending Actions ({actions.length})</h2>
        
        <div className="filters">
          <button 
            onClick={() => setFilter('all')}
            className={filter === 'all' ? 'active' : ''}
          >
            All
          </button>
          <button 
            onClick={() => setFilter('pending')}
            className={filter === 'pending' ? 'active' : ''}
          >
            Pending
          </button>
          <button 
            onClick={() => setFilter('overdue')}
            className={filter === 'overdue' ? 'active' : ''}
          >
            Overdue
          </button>
        </div>
      </div>

      <div className="action-list">
        {actions.length === 0 ? (
          <div className="empty-state">
            <p>No pending actions</p>
          </div>
        ) : (
          actions.map(action => (
            <UserActionCard
              key={action.id}
              action={action}
              onComplete={handleActionComplete}
            />
          ))
        )}
      </div>
    </div>
  );
};

export default UserActionInbox;
```

### 4.3 React Example: Dynamic Form Renderer

```jsx
// components/UserActionForm.jsx
import React, { useState } from 'react';
import FormFieldRenderer from './FormFieldRenderer';
import ApprovalButtons from './ApprovalButtons';
import DocumentUploader from './DocumentUploader';

const UserActionForm = ({ action, onSubmit, onCancel }) => {
  const [formData, setFormData] = useState({});
  const [errors, setErrors] = useState({});
  const [submitting, setSubmitting] = useState(false);

  const handleFieldChange = (fieldName, value) => {
    setFormData({
      ...formData,
      [fieldName]: value
    });
    
    // Clear error for this field
    if (errors[fieldName]) {
      setErrors({
        ...errors,
        [fieldName]: null
      });
    }
  };

  const validateForm = () => {
    const newErrors = {};
    const schema = action.action_config.form_schema;
    
    if (schema && schema.fields) {
      schema.fields.forEach(field => {
        if (field.required && !formData[field.name]) {
          newErrors[field.name] = `${field.label} is required`;
        }
        
        // Add more validation based on field.validation
        if (field.validation && formData[field.name]) {
          // e.g., min:50 for textarea
          if (field.validation.includes('min:')) {
            const minLength = parseInt(field.validation.split(':')[1]);
            if (formData[field.name].length < minLength) {
              newErrors[field.name] = 
                `${field.label} must be at least ${minLength} characters`;
            }
          }
        }
      });
    }
    
    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    
    if (!validateForm()) {
      return;
    }
    
    setSubmitting(true);
    try {
      await onSubmit(formData);
    } catch (error) {
      console.error('Submission failed', error);
      setErrors({ _form: error.message });
    } finally {
      setSubmitting(false);
    }
  };

  // Render different UI based on action_type
  const renderActionContent = () => {
    switch (action.action_type) {
      case 'approval':
        return (
          <ApprovalButtons
            config={action.action_config}
            context={action.context_data}
            onApprove={(notes) => onSubmit({ decision: 'approve' }, notes)}
            onReject={(notes) => onSubmit({ decision: 'reject' }, notes)}
            disabled={submitting}
          />
        );
      
      case 'form_submission':
        return (
          <div className="form-fields">
            {action.action_config.form_schema.fields.map(field => (
              <FormFieldRenderer
                key={field.name}
                field={field}
                value={formData[field.name]}
                onChange={(value) => handleFieldChange(field.name, value)}
                error={errors[field.name]}
              />
            ))}
          </div>
        );
      
      case 'document_upload':
        return (
          <DocumentUploader
            config={action.action_config}
            onUpload={(files) => handleFieldChange('files', files)}
            error={errors.files}
          />
        );
      
      case 'review':
        return (
          <ReviewForm
            config={action.action_config}
            onChange={setFormData}
            errors={errors}
          />
        );
      
      default:
        return <div>Unsupported action type: {action.action_type}</div>;
    }
  };

  return (
    <div className="user-action-form">
      <div className="form-header">
        <h2>{action.action_config.title}</h2>
        {action.action_config.description && (
          <p className="text-muted">{action.action_config.description}</p>
        )}
      </div>

      <form onSubmit={handleSubmit}>
        {renderActionContent()}
        
        {errors._form && (
          <div className="alert alert-danger">{errors._form}</div>
        )}
        
        {action.action_type === 'form_submission' && (
          <div className="form-actions">
            <button 
              type="submit" 
              className="btn btn-primary"
              disabled={submitting}
            >
              {submitting ? 'Submitting...' : 'Submit'}
            </button>
            <button 
              type="button" 
              className="btn btn-secondary"
              onClick={onCancel}
            >
              Cancel
            </button>
          </div>
        )}
      </form>
    </div>
  );
};

export default UserActionForm;
```

### 4.4 React Example: Form Field Renderer

```jsx
// components/FormFieldRenderer.jsx
import React from 'react';

const FormFieldRenderer = ({ field, value, onChange, error }) => {
  const renderInput = () => {
    switch (field.type) {
      case 'text':
      case 'email':
      case 'tel':
        return (
          <input
            type={field.type}
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            placeholder={field.placeholder}
            required={field.required}
            readOnly={field.readonly}
          />
        );
      
      case 'number':
        return (
          <input
            type="number"
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={value || ''}
            onChange={(e) => onChange(parseFloat(e.target.value))}
            required={field.required}
            readOnly={field.readonly}
          />
        );
      
      case 'textarea':
        return (
          <textarea
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            placeholder={field.placeholder}
            required={field.required}
            rows={field.rows || 4}
          />
        );
      
      case 'select':
        return (
          <select
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            required={field.required}
          >
            <option value="">Select {field.label}...</option>
            {field.options.map(option => (
              <option 
                key={typeof option === 'string' ? option : option.value}
                value={typeof option === 'string' ? option : option.value}
              >
                {typeof option === 'string' ? option : option.label}
              </option>
            ))}
          </select>
        );
      
      case 'radio':
        return (
          <div className="radio-group">
            {field.options.map(option => (
              <label key={option} className="radio-label">
                <input
                  type="radio"
                  name={field.name}
                  value={option}
                  checked={value === option}
                  onChange={(e) => onChange(e.target.value)}
                  required={field.required}
                />
                {option}
              </label>
            ))}
          </div>
        );
      
      case 'date':
        return (
          <input
            type="date"
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            required={field.required}
          />
        );
      
      case 'datetime':
        return (
          <input
            type="datetime-local"
            className={`form-control ${error ? 'is-invalid' : ''}`}
            value={value || ''}
            onChange={(e) => onChange(e.target.value)}
            required={field.required}
          />
        );
      
      case 'repeater':
        return (
          <RepeaterField
            field={field}
            value={value || []}
            onChange={onChange}
            error={error}
          />
        );
      
      default:
        return <div>Unsupported field type: {field.type}</div>;
    }
  };

  return (
    <div className="form-group">
      <label htmlFor={field.name}>
        {field.label}
        {field.required && <span className="text-danger">*</span>}
      </label>
      {renderInput()}
      {error && <div className="invalid-feedback d-block">{error}</div>}
      {field.help_text && (
        <small className="form-text text-muted">{field.help_text}</small>
      )}
    </div>
  );
};

export default FormFieldRenderer;
```

### 4.5 Vue Component Example

```vue
<!-- components/UserActionInbox.vue -->
<template>
  <div class="user-action-inbox">
    <div class="inbox-header">
      <h2>Pending Actions ({{ actions.length }})</h2>
      
      <div class="filters">
        <button 
          @click="filter = 'all'"
          :class="{ active: filter === 'all' }"
        >
          All
        </button>
        <button 
          @click="filter = 'pending'"
          :class="{ active: filter === 'pending' }"
        >
          Pending
        </button>
      </div>
    </div>

    <div v-if="loading" class="spinner">
      Loading...
    </div>

    <div v-else class="action-list">
      <UserActionCard
        v-for="action in actions"
        :key="action.id"
        :action="action"
        @complete="handleActionComplete"
      />
    </div>
  </div>
</template>

<script>
import { ref, onMounted, watch } from 'vue';
import { ForgePulseClient } from '../api/forgepulse-client';
import UserActionCard from './UserActionCard.vue';

export default {
  name: 'UserActionInbox',
  components: { UserActionCard },
  props: {
    userId: {
      type: Number,
      required: true
    },
    actionTypes: {
      type: Array,
      default: null
    },
    limit: {
      type: Number,
      default: 10
    }
  },
  setup(props, { emit }) {
    const actions = ref([]);
    const loading = ref(true);
    const filter = ref('all');
    
    const client = new ForgePulseClient(
      process.env.VUE_APP_API_URL,
      localStorage.getItem('auth_token')
    );

    const loadActions = async () => {
      loading.value = true;
      try {
        const filters = {
          status: filter.value === 'all' ? null : filter.value,
          action_type: props.actionTypes,
          per_page: props.limit
        };
        const response = await client.getPendingActions(filters);
        actions.value = response.data;
      } catch (error) {
        console.error('Failed to load actions', error);
      } finally {
        loading.value = false;
      }
    };

    const handleActionComplete = (actionId) => {
      actions.value = actions.value.filter(a => a.id !== actionId);
      emit('action-complete', actionId);
    };

    onMounted(() => {
      loadActions();
    });

    watch(filter, () => {
      loadActions();
    });

    return {
      actions,
      loading,
      filter,
      handleActionComplete
    };
  }
};
</script>

<style scoped>
.user-action-inbox {
  /* Your styles */
}
</style>
```

---

## 5. Mobile Integration

### 5.1 React Native Example

```jsx
// components/UserActionInbox.native.jsx
import React, { useEffect, useState } from 'react';
import {
  View,
  Text,
  FlatList,
  TouchableOpacity,
  ActivityIndicator,
  StyleSheet
} from 'react-native';
import { ForgePulseClient } from '../api/forgepulse-client';
import UserActionCard from './UserActionCard.native';

const UserActionInbox = ({ navigation }) => {
  const [actions, setActions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);

  const client = new ForgePulseClient(
    process.env.API_URL,
    // Get token from secure storage
    await SecureStore.getItemAsync('auth_token')
  );

  useEffect(() => {
    loadActions();
  }, []);

  const loadActions = async () => {
    try {
      const response = await client.getPendingActions();
      setActions(response.data);
    } catch (error) {
      console.error('Failed to load actions', error);
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  };

  const handleRefresh = () => {
    setRefreshing(true);
    loadActions();
  };

  const handleActionPress = (action) => {
    navigation.navigate('UserActionDetail', { actionId: action.id });
  };

  if (loading) {
    return (
      <View style={styles.centerContainer}>
        <ActivityIndicator size="large" color="#007bff" />
      </View>
    );
  }

  return (
    <View style={styles.container}>
      <View style={styles.header}>
        <Text style={styles.title}>Pending Actions</Text>
        <Text style={styles.count}>{actions.length} items</Text>
      </View>

      <FlatList
        data={actions}
        keyExtractor={(item) => item.id.toString()}
        renderItem={({ item }) => (
          <UserActionCard
            action={item}
            onPress={() => handleActionPress(item)}
          />
        )}
        refreshing={refreshing}
        onRefresh={handleRefresh}
        ListEmptyComponent={
          <View style={styles.emptyState}>
            <Text style={styles.emptyText}>No pending actions</Text>
          </View>
        }
      />
    </View>
  );
};

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#f5f5f5',
  },
  header: {
    padding: 16,
    backgroundColor: '#fff',
    borderBottomWidth: 1,
    borderBottomColor: '#e0e0e0',
  },
  title: {
    fontSize: 24,
    fontWeight: 'bold',
  },
  count: {
    fontSize: 14,
    color: '#666',
    marginTop: 4,
  },
  centerContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  emptyState: {
    padding: 32,
    alignItems: 'center',
  },
  emptyText: {
    fontSize: 16,
    color: '#999',
  },
});

export default UserActionInbox;
```

### 5.2 Flutter Example

```dart
// lib/widgets/user_action_inbox.dart
import 'package:flutter/material.dart';
import '../api/forgepulse_client.dart';
import '../models/user_action.dart';
import 'user_action_card.dart';

class UserActionInbox extends StatefulWidget {
  final int userId;
  
  const UserActionInbox({Key? key, required this.userId}) : super(key: key);

  @override
  _UserActionInboxState createState() => _UserActionInboxState();
}

class _UserActionInboxState extends State<UserActionInbox> {
  final ForgePulseClient _client = ForgePulseClient();
  List<UserAction> _actions = [];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _loadActions();
  }

  Future<void> _loadActions() async {
    setState(() => _loading = true);
    
    try {
      final actions = await _client.getPendingActions();
      setState(() {
        _actions = actions;
        _loading = false;
      });
    } catch (e) {
      setState(() => _loading = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Failed to load actions: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Pending Actions'),
        actions: [
          IconButton(
            icon: Icon(Icons.refresh),
            onPressed: _loadActions,
          ),
        ],
      ),
      body: _loading
          ? Center(child: CircularProgressIndicator())
          : _actions.isEmpty
              ? Center(
                  child: Text(
                    'No pending actions',
                    style: TextStyle(fontSize: 16, color: Colors.grey),
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _loadActions,
                  child: ListView.builder(
                    itemCount: _actions.length,
                    itemBuilder: (context, index) {
                      return UserActionCard(
                        action: _actions[index],
                        onTap: () => _navigateToDetail(_actions[index]),
                      );
                    },
                  ),
                ),
    );
  }

  void _navigateToDetail(UserAction action) {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => UserActionDetailScreen(action: action),
      ),
    ).then((_) => _loadActions());
  }
}
```

---

## 6. Iframe Embedding

### 6.1 Simple Iframe Integration

```html
<!-- Your existing application -->
<!DOCTYPE html>
<html>
<head>
    <title>Your Application</title>
</head>
<body>
    <div class="dashboard">
        <!-- Your existing content -->
        
        <div class="pending-approvals-widget">
            <h2>Pending Approvals</h2>
            
            <!-- Embed ForgePulse user action inbox via iframe -->
            <iframe 
                src="https://your-app.com/forgepulse/embed/inbox?user_id=123&theme=compact"
                width="100%"
                height="500"
                frameborder="0"
                scrolling="auto"
                id="forgepulse-inbox"
            ></iframe>
        </div>
    </div>

    <script>
        // Post-message communication with iframe
        window.addEventListener('message', function(event) {
            // Verify origin
            if (event.origin !== 'https://your-app.com') return;
            
            const data = event.data;
            
            if (data.type === 'forgepulse.actionCompleted') {
                console.log('Action completed', data.actionId);
                // Refresh your UI, show notification, etc.
                showNotification('Action completed successfully');
            }
            
            if (data.type === 'forgepulse.heightChange') {
                // Adjust iframe height dynamically
                document.getElementById('forgepulse-inbox').style.height = 
                    data.height + 'px';
            }
        });
    </script>
</body>
</html>
```

### 6.2 Embedded Route Configuration

```php
// In your Laravel routes (routes/web.php)

Route::get('/forgepulse/embed/inbox', function (Request $request) {
    $userId = $request->query('user_id');
    $theme = $request->query('theme', 'default');
    
    return view('forgepulse::embed.inbox', [
        'userId' => $userId,
        'theme' => $theme,
        'isEmbedded' => true,
    ]);
})->middleware('auth');

Route::get('/forgepulse/embed/action/{actionId}', function ($actionId) {
    $action = UserAction::findOrFail($actionId);
    
    return view('forgepulse::embed.action-form', [
        'action' => $action,
        'isEmbedded' => true,
    ]);
})->middleware('auth');
```

### 6.3 Iframe Communication Script

```javascript
// resources/js/forgepulse-iframe.js
// Include this in your embedded pages

(function() {
    // Send height updates to parent
    function sendHeightUpdate() {
        const height = document.documentElement.scrollHeight;
        window.parent.postMessage({
            type: 'forgepulse.heightChange',
            height: height
        }, '*');
    }

    // Send action completed event
    function notifyActionCompleted(actionId) {
        window.parent.postMessage({
            type: 'forgepulse.actionCompleted',
            actionId: actionId
        }, '*');
    }

    // Listen for Livewire events and notify parent
    document.addEventListener('livewire:load', function() {
        Livewire.on('actionCompleted', function(actionId) {
            notifyActionCompleted(actionId);
        });
        
        // Send initial height
        sendHeightUpdate();
        
        // Update height on content change
        const observer = new MutationObserver(sendHeightUpdate);
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true
        });
    });
})();
```

---

## 7. Form Schema Rendering

### 7.1 Schema-Driven Form Builder

```javascript
// utils/form-schema-renderer.js

/**
 * Dynamically render form fields based on JSON schema
 */
class FormSchemaRenderer {
  constructor(schema, data = {}, errors = {}) {
    this.schema = schema;
    this.data = data;
    this.errors = errors;
  }

  render(container) {
    const form = document.createElement('form');
    form.className = 'forgepulse-form';
    
    if (this.schema.fields) {
      this.schema.fields.forEach(field => {
        const fieldElement = this.renderField(field);
        form.appendChild(fieldElement);
      });
    }
    
    container.appendChild(form);
    return form;
  }

  renderField(field) {
    const wrapper = document.createElement('div');
    wrapper.className = 'form-group';
    
    // Label
    if (field.label) {
      const label = document.createElement('label');
      label.textContent = field.label;
      label.htmlFor = field.name;
      if (field.required) {
        const required = document.createElement('span');
        required.className = 'text-danger';
        required.textContent = ' *';
        label.appendChild(required);
      }
      wrapper.appendChild(label);
    }
    
    // Input
    const input = this.createInput(field);
    wrapper.appendChild(input);
    
    // Error message
    if (this.errors[field.name]) {
      const error = document.createElement('div');
      error.className = 'invalid-feedback d-block';
      error.textContent = this.errors[field.name];
      wrapper.appendChild(error);
    }
    
    // Help text
    if (field.help_text) {
      const help = document.createElement('small');
      help.className = 'form-text text-muted';
      help.textContent = field.help_text;
      wrapper.appendChild(help);
    }
    
    return wrapper;
  }

  createInput(field) {
    switch (field.type) {
      case 'text':
      case 'email':
      case 'tel':
      case 'number':
        return this.createTextInput(field);
      
      case 'textarea':
        return this.createTextarea(field);
      
      case 'select':
        return this.createSelect(field);
      
      case 'radio':
        return this.createRadioGroup(field);
      
      case 'checkbox':
        return this.createCheckbox(field);
      
      case 'date':
      case 'datetime':
        return this.createDateInput(field);
      
      case 'file':
        return this.createFileInput(field);
      
      case 'repeater':
        return this.createRepeater(field);
      
      default:
        console.warn(`Unsupported field type: ${field.type}`);
        return document.createTextNode('Unsupported field type');
    }
  }

  createTextInput(field) {
    const input = document.createElement('input');
    input.type = field.type;
    input.name = field.name;
    input.id = field.name;
    input.className = 'form-control';
    input.value = this.data[field.name] || '';
    input.placeholder = field.placeholder || '';
    input.required = field.required || false;
    input.readOnly = field.readonly || false;
    
    return input;
  }

  createTextarea(field) {
    const textarea = document.createElement('textarea');
    textarea.name = field.name;
    textarea.id = field.name;
    textarea.className = 'form-control';
    textarea.rows = field.rows || 4;
    textarea.value = this.data[field.name] || '';
    textarea.placeholder = field.placeholder || '';
    textarea.required = field.required || false;
    
    return textarea;
  }

  createSelect(field) {
    const select = document.createElement('select');
    select.name = field.name;
    select.id = field.name;
    select.className = 'form-control';
    select.required = field.required || false;
    
    // Empty option
    const emptyOption = document.createElement('option');
    emptyOption.value = '';
    emptyOption.textContent = `Select ${field.label}...`;
    select.appendChild(emptyOption);
    
    // Options
    field.options.forEach(option => {
      const optElement = document.createElement('option');
      if (typeof option === 'string') {
        optElement.value = option;
        optElement.textContent = option;
      } else {
        optElement.value = option.value;
        optElement.textContent = option.label;
      }
      if (this.data[field.name] === optElement.value) {
        optElement.selected = true;
      }
      select.appendChild(optElement);
    });
    
    return select;
  }

  createRadioGroup(field) {
    const container = document.createElement('div');
    container.className = 'radio-group';
    
    field.options.forEach(option => {
      const label = document.createElement('label');
      label.className = 'radio-label';
      
      const radio = document.createElement('input');
      radio.type = 'radio';
      radio.name = field.name;
      radio.value = option;
      radio.required = field.required || false;
      radio.checked = this.data[field.name] === option;
      
      label.appendChild(radio);
      label.appendChild(document.createTextNode(' ' + option));
      container.appendChild(label);
    });
    
    return container;
  }

  // ... other input creators
}

export default FormSchemaRenderer;
```

### 7.2 Usage Example

```javascript
// In your application
import FormSchemaRenderer from './utils/form-schema-renderer';

// Fetch user action
fetch('/api/forgepulse/user-actions/123')
  .then(res => res.json())
  .then(action => {
    const schema = action.action_config.form_schema;
    const renderer = new FormSchemaRenderer(schema);
    
    const container = document.getElementById('form-container');
    const form = renderer.render(container);
    
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      const formData = new FormData(form);
      
      // Submit to API
      fetch(`/api/forgepulse/user-actions/${action.id}/respond`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${token}`
        },
        body: JSON.stringify({
          response_data: Object.fromEntries(formData)
        })
      });
    });
  });
```

---

## 8. Notification Integration

### 8.1 Email Notifications with Action Links

```php
// app/Notifications/UserActionAssignedNotification.php

class UserActionAssignedNotification extends Notification
{
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable)
    {
        $actionUrl = route('user-actions.show', $this->userAction->id);
        
        return (new MailMessage)
            ->subject("Action Required: {$this->userAction->action_config['title']}")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have a new action awaiting your response:")
            ->line("**{$this->userAction->action_config['title']}**")
            ->line($this->userAction->action_config['description'] ?? '')
            ->action('Take Action', $actionUrl)
            ->line('Due Date: ' . $this->userAction->due_at?->format('M d, Y H:i'))
            ->line('Thank you!');
    }

    public function toArray($notifiable)
    {
        return [
            'user_action_id' => $this->userAction->id,
            'action_type' => $this->userAction->action_type->value,
            'title' => $this->userAction->action_config['title'],
            'due_at' => $this->userAction->due_at,
        ];
    }
}
```

### 8.2 In-App Notification Badge

```blade
{{-- In your layout header --}}
<nav class="navbar">
    <ul class="nav-items">
        <li class="nav-item dropdown">
            <a href="#" class="nav-link" data-toggle="dropdown">
                <i class="icon-bell"></i>
                @if(auth()->user()->unreadUserActions()->count() > 0)
                    <span class="badge badge-danger">
                        {{ auth()->user()->unreadUserActions()->count() }}
                    </span>
                @endif
            </a>
            
            <div class="dropdown-menu dropdown-menu-right">
                <div class="dropdown-header">Pending Actions</div>
                
                @forelse(auth()->user()->unreadUserActions()->limit(5)->get() as $action)
                    <a href="{{ route('user-actions.show', $action) }}" 
                       class="dropdown-item">
                        <strong>{{ $action->action_config['title'] ?? 'Action Required' }}</strong>
                        <br>
                        <small class="text-muted">
                            {{ $action->workflow->name }} • 
                            {{ $action->assigned_at->diffForHumans() }}
                        </small>
                    </a>
                @empty
                    <div class="dropdown-item text-muted">
                        No pending actions
                    </div>
                @endforelse
                
                <div class="dropdown-divider"></div>
                <a href="{{ route('user-actions.inbox') }}" class="dropdown-item text-center">
                    View All
                </a>
            </div>
        </li>
    </ul>
</nav>
```

### 8.3 Push Notifications (Mobile)

```javascript
// React Native - Push notification handling

import messaging from '@react-native-firebase/messaging';
import { useNavigation } from '@react-navigation/native';

const App = () => {
  const navigation = useNavigation();

  useEffect(() => {
    // Handle notification when app is in foreground
    const unsubscribe = messaging().onMessage(async remoteMessage => {
      const { data } = remoteMessage;
      
      if (data.type === 'user_action_assigned') {
        // Show local notification
        showLocalNotification({
          title: data.title,
          body: data.body,
          data: { actionId: data.action_id }
        });
      }
    });

    // Handle notification tap (app opened from notification)
    messaging().onNotificationOpenedApp(remoteMessage => {
      const { data } = remoteMessage;
      
      if (data.type === 'user_action_assigned') {
        navigation.navigate('UserActionDetail', {
          actionId: data.action_id
        });
      }
    });

    // Check if app was opened from notification (app was quit)
    messaging()
      .getInitialNotification()
      .then(remoteMessage => {
        if (remoteMessage && remoteMessage.data.type === 'user_action_assigned') {
          navigation.navigate('UserActionDetail', {
            actionId: remoteMessage.data.action_id
          });
        }
      });

    return unsubscribe;
  }, []);

  return <AppContainer />;
};
```

---

## 9. Real-time Updates

### 9.1 Laravel Echo (WebSockets)

```javascript
// resources/js/bootstrap.js

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: process.env.MIX_PUSHER_APP_KEY,
    cluster: process.env.MIX_PUSHER_APP_CLUSTER,
    forceTLS: true,
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('auth_token')}`
        }
    }
});

// Listen for user action events
window.Echo.private(`user.${userId}`)
    .listen('UserActionAssigned', (e) => {
        console.log('New action assigned', e.userAction);
        
        // Update UI
        updateActionInbox(e.userAction);
        
        // Show notification
        showNotification('New action assigned to you');
        
        // Update badge count
        incrementBadgeCount();
    })
    .listen('UserActionDelegated', (e) => {
        console.log('Action delegated', e.userAction);
        removeActionFromInbox(e.originalActionId);
    })
    .listen('WorkflowStepCompleted', (e) => {
        if (e.workflowExecution.userId === userId) {
            showNotification('Your workflow has progressed');
        }
    });
```

### 9.2 Polling Alternative

```javascript
// Simple polling for environments without WebSockets

class UserActionPoller {
  constructor(interval = 30000) { // 30 seconds
    this.interval = interval;
    this.timer = null;
    this.lastCheck = null;
  }

  start(callback) {
    this.timer = setInterval(async () => {
      try {
        const response = await fetch('/api/forgepulse/user-actions/pending', {
          headers: {
            'Authorization': `Bearer ${token}`,
            'If-Modified-Since': this.lastCheck || ''
          }
        });
        
        if (response.status === 200) {
          const data = await response.json();
          callback(data);
          this.lastCheck = new Date().toUTCString();
        }
      } catch (error) {
        console.error('Polling error', error);
      }
    }, this.interval);
  }

  stop() {
    if (this.timer) {
      clearInterval(this.timer);
      this.timer = null;
    }
  }
}

// Usage
const poller = new UserActionPoller(30000);
poller.start((data) => {
  // Update UI with new actions
  updateInbox(data);
});
```

---

## 10. Customization & Theming

### 10.1 CSS Customization

```css
/* resources/css/custom-forgepulse.css */

/* Override ForgePulse component styles to match your brand */

.forgepulse-inbox {
    --primary-color: #007bff;
    --success-color: #28a745;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --font-family: 'Inter', sans-serif;
}

/* Customize action cards */
.user-action-card {
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
    background: white;
    transition: box-shadow 0.2s;
}

.user-action-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

/* Customize approval buttons */
.approval-buttons .btn-approve {
    background-color: var(--success-color);
    color: white;
}

.approval-buttons .btn-reject {
    background-color: var(--danger-color);
    color: white;
}

/* Customize form fields */
.forgepulse-form .form-control {
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    padding: 10px 14px;
}

.forgepulse-form .form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
}
```

### 10.2 Component Prop Customization

```blade
{{-- Theme customization via props --}}
<livewire:forgepulse::user-action-inbox 
    :userId="auth()->id()"
    
    {{-- Visual customization --}}
    theme="compact"
    cardStyle="bordered"
    colorScheme="light"
    
    {{-- Functional customization --}}
    :showFilters="true"
    :showSearch="true"
    :showBulkActions="true"
    :showDelegateButton="true"
    :showTimeline="false"
    
    {{-- Custom classes --}}
    containerClass="custom-inbox-container"
    cardClass="custom-action-card"
    
    {{-- Custom translations --}}
    :translations="[
        'no_pending_actions' => 'You\'re all caught up!',
        'approve_button' => 'Accept',
        'reject_button' => 'Decline',
    ]"
/>
```

### 10.3 Branded Wrapper Component

```php
// app/View/Components/BrandedUserActionInbox.php

namespace App\View\Components;

use Illuminate\View\Component;

class BrandedUserActionInbox extends Component
{
    public function render()
    {
        return view('components.branded-user-action-inbox');
    }
}
```

```blade
{{-- resources/views/components/branded-user-action-inbox.blade.php --}}
<div class="company-workflow-inbox">
    <div class="company-header">
        <img src="/img/company-logo.svg" alt="Company Logo">
        <h2>My Action Items</h2>
    </div>
    
    <livewire:forgepulse::user-action-inbox 
        :userId="auth()->id()"
        theme="custom"
        containerClass="company-inbox-list"
    />
    
    <div class="company-footer">
        <a href="{{ route('help.workflows') }}">Need Help?</a>
    </div>
</div>
```

---

## 11. Integration Checklist

### 11.1 Before You Integrate

- [ ] Determine integration approach (Livewire, API, iframe)
- [ ] Set up authentication/authorization
- [ ] Configure CORS if using API from different domain
- [ ] Plan notification strategy (email, push, in-app)
- [ ] Define custom styling/theming requirements
- [ ] Set up error handling and logging
- [ ] Plan real-time update strategy (WebSockets vs polling)

### 11.2 Development Phase

- [ ] Install ForgePulse package
- [ ] Run migrations
- [ ] Publish assets and views (if customizing)
- [ ] Set up API routes and middleware
- [ ] Create frontend components
- [ ] Implement form schema renderer
- [ ] Set up notification channels
- [ ] Configure real-time updates
- [ ] Add error boundaries and loading states

### 11.3 Testing Phase

- [ ] Test all user action types (approval, form, upload, etc.)
- [ ] Test delegation functionality
- [ ] Test escalation (if configured)
- [ ] Test mobile responsiveness
- [ ] Test cross-browser compatibility
- [ ] Test notification delivery
- [ ] Test real-time updates
- [ ] Performance testing with large datasets

### 11.4 Production Deployment

- [ ] Configure production API endpoints
- [ ] Set up production WebSocket server (if using)
- [ ] Configure production notification channels
- [ ] Set up monitoring and alerting
- [ ] Document integration for your team
- [ ] Train end users
- [ ] Monitor error rates and performance

---

## 12. Summary

### Integration Approach Recommendations

| Your Stack | Recommended Approach | Effort | Flexibility |
|-----------|---------------------|--------|-------------|
| Laravel Blade | Livewire Components | Low | Medium |
| React SPA | REST API + React Components | Medium | High |
| Vue SPA | REST API + Vue Components | Medium | High |
| Mobile (React Native) | REST API + Native Components | Medium | High |
| Mobile (Flutter) | REST API + Flutter Widgets | Medium | High |
| Legacy System | Iframe Embedding | Very Low | Low |
| Any Framework | REST API + Custom UI | High | Very High |

### Key Takeaways

1. **Livewire (Laravel)**: Drop-in components with minimal code
2. **REST API**: Maximum flexibility for any frontend framework
3. **Dynamic Forms**: JSON schema drives UI rendering
4. **Real-time**: WebSockets or polling for live updates
5. **Notifications**: Email, push, in-app badges
6. **Mobile**: Full API support for native mobile apps
7. **Theming**: CSS variables and props for customization

The beauty of the UserAction architecture is that it's **framework-agnostic at the API level** while providing **first-class Livewire integration** for Laravel apps.

---

**End of Document**
