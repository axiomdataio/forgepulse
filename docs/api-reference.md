# Workflow API Reference

This document provides a complete reference for the ForgePulse Workflow Management API.

## Overview

The Workflow API enables programmatic creation, management, and deletion of workflows through RESTful endpoints. All endpoints return JSON responses and support Laravel Sanctum authentication.

## Authentication

All API requests require authentication using Laravel Sanctum tokens:

```bash
Authorization: Bearer YOUR_API_TOKEN
```

Generate a token:

```php
$token = $user->createToken('api-access')->plainTextToken;
```

## Endpoints

### List Workflows

Retrieve a paginated list of all workflows.

**Endpoint:** `GET /api/forgepulse/workflows`

**Response:**

```json
{
  "data": [
    {
      "id": 1,
      "name": "User Onboarding",
      "description": "Automated user onboarding process",
      "status": "active",
      "is_template": false,
      "version": "1.0.0",
      "steps_count": 5,
      "executions_count": 142,
      "created_at": "2025-11-26T12:00:00.000Z",
      "updated_at": "2025-11-26T12:00:00.000Z"
    }
  ],
  "links": {...},
  "meta": {...}
}
```

### Get Workflow

Retrieve a specific workflow with all its steps and executions.

**Endpoint:** `GET /api/forgepulse/workflows/{id}`

**Response:**

```json
{
  "data": {
    "id": 1,
    "name": "User Onboarding",
    "description": "Automated user onboarding process",
    "status": "active",
    "is_template": false,
    "version": "1.0.0",
    "steps": [...],
    "executions": [...],
    "created_at": "2025-11-26T12:00:00.000Z",
    "updated_at": "2025-11-26T12:00:00.000Z"
  }
}
```

### Create Workflow

Create a new workflow with optional steps.

**Endpoint:** `POST /api/forgepulse/workflows`

**Request Body:**

```json
{
  "name": "User Onboarding",
  "description": "Automated user onboarding process",
  "status": "active",
  "configuration": {
    "max_retries": 3,
    "timeout": 300
  },
  "is_template": false,
  "version": "1.0.0",
  "steps": [
    {
      "name": "Send Welcome Email",
      "description": "Send welcome email to new user",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\WelcomeEmail",
        "recipients": ["{{user_id}}"]
      },
      "position": 1,
      "is_enabled": true,
      "timeout": 30
    },
    {
      "name": "Wait 24 Hours",
      "type": "delay",
      "configuration": {
        "seconds": 86400
      },
      "position": 2
    }
  ]
}
```

**Required Fields:**
- `name` (string, max 255 characters)
- `status` (enum: `draft`, `active`, `inactive`, `archived`)

**Optional Fields:**
- `description` (string, max 1000 characters)
- `configuration` (object)
- `is_template` (boolean, default: false)
- `version` (string, default: "1.0.0")
- `user_id` (integer, defaults to authenticated user)
- `team_id` (integer)
- `steps` (array of step objects)

**Step Object Fields:**
- `name` (required, string, max 255 characters)
- `type` (required, enum: `action`, `condition`, `delay`, `notification`, `webhook`, `event`, `job`)
- `configuration` (required, object)
- `position` (required, integer)
- `description` (optional, string)
- `conditions` (optional, object)
- `x_position` (optional, integer)
- `y_position` (optional, integer)
- `parent_step_id` (optional, integer)
- `is_enabled` (optional, boolean, default: true)
- `timeout` (optional, integer, seconds)
- `execution_mode` (optional, enum: `sequential`, `parallel`)
- `parallel_group` (optional, string)

**Response:** `201 Created`

```json
{
  "data": {
    "id": 1,
    "name": "User Onboarding",
    "status": "active",
    "steps": [...],
    ...
  }
}
```

### Update Workflow

Update an existing workflow and optionally its steps.

**Endpoint:** `PUT /api/forgepulse/workflows/{id}`

**Request Body:**

```json
{
  "name": "Enhanced User Onboarding",
  "status": "active",
  "steps": [
    {
      "id": 1,
      "name": "Updated Step Name",
      "type": "notification",
      "configuration": {...},
      "position": 1
    },
    {
      "name": "New Step",
      "type": "delay",
      "configuration": {"seconds": 3600},
      "position": 2
    }
  ]
}
```

**Notes:**
- All fields are optional for updates
- Steps with an `id` field will update existing steps
- Steps without an `id` field will create new steps
- Omitted steps will remain unchanged

**Response:** `200 OK`

```json
{
  "data": {
    "id": 1,
    "name": "Enhanced User Onboarding",
    ...
  }
}
```

### Delete Workflow

Soft delete a workflow (can be restored later).

**Endpoint:** `DELETE /api/forgepulse/workflows/{id}`

**Response:** `200 OK`

```json
{
  "message": "Workflow deleted successfully."
}
```

## Step Types

The following step types are supported:

### Action

Execute custom action classes:

```json
{
  "type": "action",
  "configuration": {
    "action_class": "App\\Actions\\ProcessUserData",
    "parameters": {
      "user_id": "{{user_id}}"
    }
  }
}
```

### Notification

Send Laravel notifications:

```json
{
  "type": "notification",
  "configuration": {
    "notification_class": "App\\Notifications\\OrderConfirmation",
    "recipients": ["{{user_id}}"]
  }
}
```

### Webhook

Make HTTP requests:

```json
{
  "type": "webhook",
  "configuration": {
    "url": "https://api.example.com/webhook",
    "method": "POST",
    "headers": {
      "Authorization": "Bearer token"
    },
    "payload": {
      "data": "{{context}}"
    }
  }
}
```

### Delay

Wait for a specified duration:

```json
{
  "type": "delay",
  "configuration": {
    "seconds": 3600
  }
}
```

### Condition

Conditional branching:

```json
{
  "type": "condition",
  "configuration": {
    "operator": "and",
    "rules": [
      {
        "field": "user.role",
        "operator": "==",
        "value": "premium"
      }
    ]
  }
}
```

### Event

Dispatch Laravel events:

```json
{
  "type": "event",
  "configuration": {
    "event_class": "App\\Events\\UserProcessed",
    "payload": {
      "user_id": "{{user_id}}"
    }
  }
}
```

### Job

Dispatch Laravel jobs:

```json
{
  "type": "job",
  "configuration": {
    "job_class": "App\\Jobs\\ProcessData",
    "parameters": {
      "data": "{{context}}"
    }
  }
}
```

## Workflow Branching

Create branching workflows using `parent_step_id` to establish parent-child relationships between steps.

### How Branching Works

1. **Parent-Child Hierarchy**: Steps can have a `parent_step_id` to create tree structures
2. **Conditional Execution**: Each child step can have `conditions` to determine if it runs
3. **Position Ordering**: Steps with the same parent are ordered by `position`

### Simple If/Else Branch

```json
{
  "name": "User Onboarding with Branching",
  "status": "active",
  "steps": [
    {
      "name": "Check User Role",
      "type": "condition",
      "position": 1,
      "parent_step_id": null
    }
  ]
}
```

After creating the workflow and getting the step ID, add child branches:

```json
{
  "steps": [
    {
      "name": "Premium User Path",
      "type": "notification",
      "position": 1,
      "parent_step_id": 1,
      "conditions": {
        "operator": "and",
        "rules": [
          {"field": "user.role", "operator": "==", "value": "premium"}
        ]
      }
    },
    {
      "name": "Free User Path",
      "type": "notification",
      "position": 2,
      "parent_step_id": 1,
      "conditions": {
        "operator": "and",
        "rules": [
          {"field": "user.role", "operator": "==", "value": "free"}
        ]
      }
    }
  ]
}
```

### Multi-Branch Decision

```json
{
  "steps": [
    {
      "name": "Validate Order",
      "type": "action",
      "position": 1,
      "parent_step_id": null
    }
  ]
}
```

Add multiple conditional branches:

```json
{
  "steps": [
    {
      "name": "Small Order Processing",
      "position": 1,
      "parent_step_id": 1,
      "conditions": {
        "rules": [{"field": "order.total", "operator": "<", "value": 100}]
      }
    },
    {
      "name": "Medium Order Processing",
      "position": 2,
      "parent_step_id": 1,
      "conditions": {
        "rules": [
          {"field": "order.total", "operator": ">=", "value": 100},
          {"field": "order.total", "operator": "<=", "value": 1000}
        ]
      }
    },
    {
      "name": "Large Order Processing",
      "position": 3,
      "parent_step_id": 1,
      "conditions": {
        "rules": [{"field": "order.total", "operator": ">", "value": 1000}]
      }
    }
  ]
}
```

### Nested Branching

Create branches within branches by setting `parent_step_id` to a child step:

```json
{
  "steps": [
    {
      "name": "Premium Processing",
      "position": 1,
      "parent_step_id": 1,
      "conditions": {
        "rules": [{"field": "user.type", "operator": "==", "value": "premium"}]
      }
    }
  ]
}
```

Then add nested branches (assuming the Premium step has ID 5):

```json
{
  "steps": [
    {
      "name": "Annual Subscription",
      "position": 1,
      "parent_step_id": 5,
      "conditions": {
        "rules": [{"field": "plan", "operator": "==", "value": "annual"}]
      }
    },
    {
      "name": "Monthly Subscription",
      "position": 2,
      "parent_step_id": 5,
      "conditions": {
        "rules": [{"field": "plan", "operator": "==", "value": "monthly"}]
      }
    }
  ]
}
```

### Convergence (Branches Rejoin)

To make branches converge back to a common path, create a new root-level step:

```json
{
  "steps": [
    {
      "name": "Branch A",
      "position": 1,
      "parent_step_id": 1,
      "conditions": {...}
    },
    {
      "name": "Branch B",
      "position": 2,
      "parent_step_id": 1,
      "conditions": {...}
    },
    {
      "name": "Convergence Point",
      "position": 2,
      "parent_step_id": null
    }
  ]
}
```

The convergence point runs after any branch completes.

### Parallel Execution (No Branching)

All child steps execute when `execution_mode` is set to `parallel`:

```json
{
  "steps": [
    {
      "name": "Send Email",
      "position": 1,
      "parent_step_id": 1,
      "execution_mode": "parallel",
      "parallel_group": "notifications"
    },
    {
      "name": "Send SMS",
      "position": 2,
      "parent_step_id": 1,
      "execution_mode": "parallel",
      "parallel_group": "notifications"
    }
  ]
}
```

## Conditional Logic

Steps can include conditional logic using the `conditions` field:

```json
{
  "conditions": {
    "operator": "and",
    "rules": [
      {
        "field": "user.role",
        "operator": "==",
        "value": "premium"
      },
      {
        "field": "order.total",
        "operator": ">",
        "value": 100
      }
    ]
  }
}
```

**Supported Operators:**
- Equality: `==`, `===`, `!=`, `!==`
- Comparison: `>`, `>=`, `<`, `<=`
- Arrays: `in`, `not_in`, `in_array`, `not_in_array`, `contains_all`, `contains_any`
- Strings: `contains`, `starts_with`, `ends_with`, `regex`, `not_regex`
- Null checks: `is_null`, `is_not_null`, `is_empty`, `is_not_empty`
- Range: `between`, `not_between`
- Length: `length_eq`, `length_gt`, `length_lt`

## Error Handling

### Validation Errors

**Status:** `422 Unprocessable Entity`

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "steps.0.type": ["The steps.0.type field is required."]
  }
}
```

### Not Found

**Status:** `404 Not Found`

```json
{
  "message": "No query results for model [Workflow] 123"
}
```

### Unauthorized

**Status:** `401 Unauthorized`

```json
{
  "message": "Unauthenticated."
}
```

### Forbidden

**Status:** `403 Forbidden`

```json
{
  "message": "This action is unauthorized."
}
```

## Rate Limiting

Default: 60 requests per minute per user.

Configure in `config/forgepulse.php`:

```php
'api' => [
    'rate_limit' => '60,1', // 60 requests per 1 minute
],
```

## Examples

### cURL Examples

**Create workflow:**

```bash
curl -X POST https://your-app.com/api/forgepulse/workflows \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Order Processing",
    "status": "active",
    "steps": [
      {
        "name": "Validate Order",
        "type": "action",
        "configuration": {
          "action_class": "App\\Actions\\ValidateOrder"
        },
        "position": 1
      }
    ]
  }'
```

**Update workflow:**

```bash
curl -X PUT https://your-app.com/api/forgepulse/workflows/1 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Order Processing",
    "status": "inactive"
  }'
```

**Delete workflow:**

```bash
curl -X DELETE https://your-app.com/api/forgepulse/workflows/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### PHP Examples

```php
use Illuminate\Support\Facades\Http;

// Create workflow
$response = Http::withToken($token)
    ->post('https://your-app.com/api/forgepulse/workflows', [
        'name' => 'User Onboarding',
        'status' => 'active',
        'steps' => [
            [
                'name' => 'Send Email',
                'type' => 'notification',
                'configuration' => [
                    'notification_class' => 'App\\Notifications\\Welcome',
                ],
                'position' => 1,
            ],
        ],
    ]);

$workflow = $response->json('data');

// Update workflow
$response = Http::withToken($token)
    ->put("https://your-app.com/api/forgepulse/workflows/{$workflow['id']}", [
        'status' => 'inactive',
    ]);

// Delete workflow
$response = Http::withToken($token)
    ->delete("https://your-app.com/api/forgepulse/workflows/{$workflow['id']}");
```

### JavaScript Examples

```javascript
// Create workflow
const response = await fetch('https://your-app.com/api/forgepulse/workflows', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    name: 'User Onboarding',
    status: 'active',
    steps: [
      {
        name: 'Send Email',
        type: 'notification',
        configuration: {
          notification_class: 'App\\Notifications\\Welcome'
        },
        position: 1
      }
    ]
  })
});

const workflow = await response.json();

// Update workflow
await fetch(`https://your-app.com/api/forgepulse/workflows/${workflow.data.id}`, {
  method: 'PUT',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  },
  body: JSON.stringify({
    status: 'inactive'
  })
});

// Delete workflow
await fetch(`https://your-app.com/api/forgepulse/workflows/${workflow.data.id}`, {
  method: 'DELETE',
  headers: {
    'Authorization': `Bearer ${token}`
  }
});
```

## Permissions

By default, workflow API operations require appropriate permissions:

- **Create:** User must have `create` permission on Workflow model
- **Update:** User must have `update` permission on specific workflow
- **Delete:** User must have `delete` permission on specific workflow

Disable permissions for testing:

```php
// config/forgepulse.php
'permissions' => [
    'enabled' => false,
],
```

## Best Practices

1. **Use Templates:** Create workflows as templates (`is_template: true`) for reusability
2. **Version Control:** Use semantic versioning for workflow versions
3. **Validation:** Always validate workflows after creation/update
4. **Error Handling:** Implement proper error handling for API responses
5. **Rate Limiting:** Respect rate limits and implement retry logic
6. **Security:** Always use HTTPS and keep API tokens secure
7. **Pagination:** Handle pagination when listing workflows
8. **Async Operations:** Consider async workflow execution for long-running processes

## Related Documentation

- [Workflow Execution API](executions.md)
- [Step Types Reference](step-types.md)
- [Conditional Logic Guide](conditionals.md)
- [Authentication Setup](authentication.md)
