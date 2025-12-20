# Creating Branching Workflows in One Request

With ForgePulse v1.3+, you can now create complete branching workflows in a single API request using **user-defined identifiers**.

## The Problem We Solved

Previously, creating branching workflows required multiple API requests because child steps needed to reference their parent's database ID, which didn't exist until after creation.

## The Solution: Step Identifiers

Use `step_identifier` and `parent_step_identifier` to create parent-child relationships without knowing database IDs.

## Quick Example

```bash
curl -X POST https://your-app.com/api/forgepulse/workflows \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Order Processing",
    "status": "active",
    "steps": [
      {
        "step_identifier": "check_amount",
        "name": "Check Order Amount",
        "type": "condition",
        "configuration": {},
        "position": 1
      },
      {
        "step_identifier": "small_order",
        "parent_step_identifier": "check_amount",
        "name": "Process Small Order",
        "type": "action",
        "configuration": {
          "action_class": "App\\Actions\\ProcessSmallOrder"
        },
        "position": 1,
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "order.total", "operator": "<", "value": 100}
          ]
        }
      },
      {
        "step_identifier": "large_order",
        "parent_step_identifier": "check_amount",
        "name": "Process Large Order",
        "type": "action",
        "configuration": {
          "action_class": "App\\Actions\\ProcessLargeOrder"
        },
        "position": 2,
        "conditions": {
          "operator": "and",
          "rules": [
            {"field": "order.total", "operator": ">=", "value": 100}
          ]
        }
      }
    ]
  }'
```

## How It Works

1. **Assign identifiers**: Give each step a unique `step_identifier`
2. **Reference parents**: Use `parent_step_identifier` to reference the parent's identifier
3. **Send in one request**: The API resolves identifiers to actual database IDs automatically

### Workflow Structure Created:

```
Check Order Amount (check_amount)
├─ Process Small Order (small_order) [if total < 100]
└─ Process Large Order (large_order) [if total >= 100]
```

## Complete Examples

### Example 1: Simple If/Else Branch

```json
{
  "name": "User Onboarding",
  "status": "active",
  "steps": [
    {
      "step_identifier": "check_role",
      "name": "Check User Role",
      "type": "condition",
      "configuration": {},
      "position": 1
    },
    {
      "step_identifier": "premium_path",
      "parent_step_identifier": "check_role",
      "name": "Premium Welcome",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\PremiumWelcome"
      },
      "position": 1,
      "conditions": {
        "operator": "and",
        "rules": [
          {"field": "user.role", "operator": "==", "value": "premium"}
        ]
      }
    },
    {
      "step_identifier": "free_path",
      "parent_step_identifier": "check_role",
      "name": "Free Welcome",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\FreeWelcome"
      },
      "position": 2,
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

### Example 2: Three-Way Branch

```json
{
  "name": "Payment Processing",
  "status": "active",
  "steps": [
    {
      "step_identifier": "start",
      "name": "Start Payment",
      "type": "action",
      "configuration": {"action_class": "App\\Actions\\StartPayment"},
      "position": 1
    },
    {
      "step_identifier": "check_method",
      "parent_step_identifier": "start",
      "name": "Check Payment Method",
      "type": "condition",
      "configuration": {},
      "position": 1
    },
    {
      "step_identifier": "card",
      "parent_step_identifier": "check_method",
      "name": "Process Card",
      "type": "action",
      "configuration": {"action_class": "App\\Actions\\ProcessCard"},
      "position": 1,
      "conditions": {
        "rules": [{"field": "payment.method", "operator": "==", "value": "card"}]
      }
    },
    {
      "step_identifier": "paypal",
      "parent_step_identifier": "check_method",
      "name": "Process PayPal",
      "type": "webhook",
      "configuration": {
        "url": "https://api.paypal.com/process",
        "method": "POST"
      },
      "position": 2,
      "conditions": {
        "rules": [{"field": "payment.method", "operator": "==", "value": "paypal"}]
      }
    },
    {
      "step_identifier": "bank",
      "parent_step_identifier": "check_method",
      "name": "Process Bank Transfer",
      "type": "action",
      "configuration": {"action_class": "App\\Actions\\ProcessBank"},
      "position": 3,
      "conditions": {
        "rules": [{"field": "payment.method", "operator": "==", "value": "bank"}]
      }
    },
    {
      "step_identifier": "receipt",
      "name": "Send Receipt",
      "type": "notification",
      "configuration": {"notification_class": "App\\Notifications\\Receipt"},
      "position": 2
    }
  ]
}
```

### Example 3: Nested Branching

```json
{
  "name": "Complex Approval",
  "status": "active",
  "steps": [
    {
      "step_identifier": "root",
      "name": "Check User Type",
      "type": "condition",
      "configuration": {},
      "position": 1
    },
    {
      "step_identifier": "premium",
      "parent_step_identifier": "root",
      "name": "Premium Processing",
      "type": "action",
      "configuration": {"action_class": "App\\Actions\\ProcessPremium"},
      "position": 1,
      "conditions": {
        "rules": [{"field": "user.type", "operator": "==", "value": "premium"}]
      }
    },
    {
      "step_identifier": "annual",
      "parent_step_identifier": "premium",
      "name": "Annual Subscription",
      "type": "action",
      "configuration": {"action_class": "App\\Actions\\ProcessAnnual"},
      "position": 1,
      "conditions": {
        "rules": [{"field": "plan", "operator": "==", "value": "annual"}]
      }
    },
    {
      "step_identifier": "monthly",
      "parent_step_identifier": "premium",
      "name": "Monthly Subscription",
      "type": "action",
      "configuration": {"action_class": "App\\Actions\\ProcessMonthly"},
      "position": 2,
      "conditions": {
        "rules": [{"field": "plan", "operator": "==", "value": "monthly"}]
      }
    }
  ]
}
```

## Using with PHP

```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)->post('/api/forgepulse/workflows', [
    'name' => 'Branching Workflow',
    'status' => 'active',
    'steps' => [
        [
            'step_identifier' => 'parent',
            'name' => 'Parent Step',
            'type' => 'condition',
            'configuration' => [],
            'position' => 1,
        ],
        [
            'step_identifier' => 'child_a',
            'parent_step_identifier' => 'parent',
            'name' => 'Child A',
            'type' => 'action',
            'configuration' => ['action_class' => 'App\\Actions\\ProcessA'],
            'position' => 1,
            'conditions' => [
                'rules' => [['field' => 'type', 'operator' => '==', 'value' => 'A']]
            ],
        ],
        [
            'step_identifier' => 'child_b',
            'parent_step_identifier' => 'parent',
            'name' => 'Child B',
            'type' => 'action',
            'configuration' => ['action_class' => 'App\\Actions\\ProcessB'],
            'position' => 2,
            'conditions' => [
                'rules' => [['field' => 'type', 'operator' => '==', 'value' => 'B']]
            ],
        ],
    ],
]);

$workflow = $response->json('data');
```

## Using with JavaScript

```javascript
const response = await fetch('/api/forgepulse/workflows', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'Branching Workflow',
    status: 'active',
    steps: [
      {
        step_identifier: 'parent',
        name: 'Parent Step',
        type: 'condition',
        configuration: {},
        position: 1
      },
      {
        step_identifier: 'child_a',
        parent_step_identifier: 'parent',
        name: 'Child A',
        type: 'action',
        configuration: { action_class: 'App\\Actions\\ProcessA' },
        position: 1,
        conditions: {
          rules: [{ field: 'type', operator: '==', value: 'A' }]
        }
      },
      {
        step_identifier: 'child_b',
        parent_step_identifier: 'parent',
        name: 'Child B',
        type: 'action',
        configuration: { action_class: 'App\\Actions\\ProcessB' },
        position: 2,
        conditions: {
          rules: [{ field: 'type', operator: '==', value: 'B' }]
        }
      }
    ]
  })
});

const workflow = await response.json();
```

## Field Reference

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `step_identifier` | string | No | Unique identifier for this step (within this request) |
| `parent_step_identifier` | string | No | References another step's `step_identifier` to set as parent |
| `parent_step_id` | integer | No | Alternative: Direct database ID (for updating existing workflows) |

**Note**: You can use either `parent_step_identifier` OR `parent_step_id`, but `parent_step_identifier` is recommended for new workflows.

## Validation Rules

1. **Uniqueness**: Each `step_identifier` must be unique within the request
2. **Valid References**: Each `parent_step_identifier` must reference an existing `step_identifier` in the same request
3. **No Circular References**: Steps cannot reference themselves or create cycles

### Validation Examples

**✅ Valid**:
```json
{
  "steps": [
    {"step_identifier": "a", "name": "A", ...},
    {"step_identifier": "b", "parent_step_identifier": "a", "name": "B", ...}
  ]
}
```

**❌ Duplicate Identifier**:
```json
{
  "steps": [
    {"step_identifier": "same", "name": "A", ...},
    {"step_identifier": "same", "name": "B", ...}  // Error!
  ]
}
```

**❌ Invalid Reference**:
```json
{
  "steps": [
    {"step_identifier": "child", "parent_step_identifier": "non_existent", ...}  // Error!
  ]
}
```

## Advantages Over Two-Step Approach

| Aspect | Two-Step Approach | Identifier Approach |
|--------|-------------------|---------------------|
| Number of requests | 2+ | 1 |
| Need to parse response | Yes | No |
| Complexity | High | Low |
| Error handling | Multiple points | Single transaction |
| Readability | Poor | Excellent |
| Maintenance | Difficult | Easy |

## Backward Compatibility

The identifier approach is **fully backward compatible**:
- Existing workflows using `parent_step_id` continue to work
- You can mix identifiers and IDs in the same request
- Update requests support both approaches

## Best Practices

1. **Use descriptive identifiers**: `"check_amount"` not `"step1"`
2. **Keep identifiers unique**: Within each request
3. **Order logically**: Place parent steps before children in the array
4. **Document branches**: Use step descriptions to explain branching logic
5. **Test conditions**: Verify conditional logic before deploying

## Common Patterns

### If/Then/Else
```json
[
  {"step_identifier": "if", ...},
  {"step_identifier": "then", "parent_step_identifier": "if", "conditions": ...},
  {"step_identifier": "else", "parent_step_identifier": "if", "conditions": ...}
]
```

### Switch/Case
```json
[
  {"step_identifier": "switch", ...},
  {"step_identifier": "case_a", "parent_step_identifier": "switch", "conditions": ...},
  {"step_identifier": "case_b", "parent_step_identifier": "switch", "conditions": ...},
  {"step_identifier": "default", "parent_step_identifier": "switch"}
]
```

### Fan-Out/Fan-In
```json
[
  {"step_identifier": "start", ...},
  {"step_identifier": "branch_1", "parent_step_identifier": "start", ...},
  {"step_identifier": "branch_2", "parent_step_identifier": "start", ...},
  {"step_identifier": "merge", ...}  // Root level = convergence
]
```

## Related Documentation

- [Workflow Branching Guide](workflow-branching.md)
- [API Reference](api-reference.md)
- [Conditional Logic](advanced-conditionals.md)

## Version History

- **v1.3.0**: Added `step_identifier` and `parent_step_identifier` support
- **v1.2.0**: Initial branching support with `parent_step_id`
