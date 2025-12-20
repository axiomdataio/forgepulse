# Workflow Branching Guide

This guide explains how to create branching workflows using the ForgePulse API.

## Understanding Workflow Branching

ForgePulse uses a **parent-child tree structure** to create branching workflows. Each step can have:
- A `parent_step_id` linking it to a parent step
- A `position` for ordering among siblings
- Optional `conditions` to determine if it should execute

## Visual Examples

### Simple If/Else Branch

```
Workflow Structure:

┌─────────────────────┐
│  1. Check User Role │  (parent_step_id: null, position: 1)
│     (root step)     │
└──────────┬──────────┘
           │
     ┌─────┴─────┐
     │           │
     v           v
┌────────────┐  ┌────────────┐
│ 2. Premium │  │ 3. Free    │
│    Path    │  │    Path    │
└────────────┘  └────────────┘
parent: 1       parent: 1
position: 1     position: 2
condition:      condition:
role==premium   role==free
```

### Multi-Branch Decision Tree

```
┌──────────────────┐
│  1. Validate     │  (parent_step_id: null)
│     Order        │
└────────┬─────────┘
         │
    ┌────┴────┬────────┐
    │         │        │
    v         v        v
┌───────┐ ┌──────┐ ┌──────┐
│Small  │ │Medium│ │Large │
│Order  │ │Order │ │Order │
└───────┘ └──────┘ └──────┘
parent:1   parent:1  parent:1
pos:1      pos:2     pos:3
total<100  100-1000  total>1000
                     │
                     v
               ┌──────────┐
               │ Manager  │
               │ Approval │
               └──────────┘
               parent: 3
               pos: 1
```

### Convergence (Branches Rejoin)

```
┌─────────────┐
│ 1. Start    │  (position: 1)
└──────┬──────┘
       │
┌──────┴──────┐
│ 2. Decision │  (position: 2)
└──────┬──────┘
       │
  ┌────┴────┐
  │         │
  v         v
┌────┐   ┌────┐
│ 3A │   │ 3B │
└─┬──┘   └──┬─┘
  │         │
  │    ┌────┘
  │    │
  v    v
┌────────────┐
│ 4. Merge   │  (position: 3, parent_step_id: null)
│ (Both lead │  ← Convergence point
│  here)     │
└────────────┘
```

### Nested Branching

```
┌─────────────────┐
│ 1. Check Type   │
└────────┬────────┘
         │
         v
    ┌────────┐
    │ 2.     │ (parent: 1, condition: type==premium)
    │Premium │
    └───┬────┘
        │
    ┌───┴───┐
    │       │
    v       v
 ┌────┐  ┌─────┐
 │ 3A │  │ 3B  │
 │Ann │  │Month│
 └────┘  └─────┘
 parent:2 parent:2
 annual   monthly
```

## Creating Branching Workflows

### Approach 1: Two-Step Creation (Recommended)

**Step 1: Create workflow with root steps**

```bash
POST /api/forgepulse/workflows
{
  "name": "Approval Workflow",
  "status": "active",
  "steps": [
    {
      "name": "Submit Request",
      "type": "action",
      "position": 1,
      "parent_step_id": null
    },
    {
      "name": "Check Amount",
      "type": "condition",
      "position": 2,
      "parent_step_id": null
    }
  ]
}
```

Response includes step IDs:
```json
{
  "data": {
    "id": 123,
    "steps": [
      {"id": 1, "name": "Submit Request"},
      {"id": 2, "name": "Check Amount"}
    ]
  }
}
```

**Step 2: Add child branches using step IDs**

```bash
PUT /api/forgepulse/workflows/123
{
  "steps": [
    {
      "name": "Auto-Approve",
      "type": "action",
      "position": 1,
      "parent_step_id": 2,
      "conditions": {
        "operator": "and",
        "rules": [
          {"field": "amount", "operator": "<", "value": 500}
        ]
      }
    },
    {
      "name": "Manager Approval",
      "type": "notification",
      "position": 2,
      "parent_step_id": 2,
      "conditions": {
        "operator": "and",
        "rules": [
          {"field": "amount", "operator": ">=", "value": 500}
        ]
      }
    }
  ]
}
```

### Approach 2: Progressive Building

Create and extend workflows incrementally:

```php
// 1. Create workflow
$response = Http::withToken($token)->post('/api/forgepulse/workflows', [
    'name' => 'Order Processing',
    'status' => 'draft',
    'steps' => [
        ['name' => 'Validate', 'type' => 'action', 'position' => 1]
    ]
]);

$workflowId = $response->json('data.id');
$validateStepId = $response->json('data.steps.0.id');

// 2. Add first branch
$response = Http::withToken($token)->put("/api/forgepulse/workflows/{$workflowId}", [
    'steps' => [
        [
            'name' => 'Small Order',
            'type' => 'action',
            'position' => 1,
            'parent_step_id' => $validateStepId,
            'conditions' => ['rules' => [['field' => 'total', 'operator' => '<', 'value' => 100]]]
        ]
    ]
]);

// 3. Add more branches
$response = Http::withToken($token)->put("/api/forgepulse/workflows/{$workflowId}", [
    'steps' => [
        [
            'name' => 'Large Order',
            'type' => 'action',
            'position' => 2,
            'parent_step_id' => $validateStepId,
            'conditions' => ['rules' => [['field' => 'total', 'operator' => '>=', 'value' => 100]]]
        ]
    ]
]);

// 4. Activate when complete
Http::withToken($token)->put("/api/forgepulse/workflows/{$workflowId}", [
    'status' => 'active'
]);
```

## Key Concepts

### parent_step_id
- `null`: Root-level step (top of hierarchy)
- `<number>`: Child of step with that ID
- Creates parent-child relationships

### position
- Orders steps at the same level
- Steps with same `parent_step_id` are siblings
- Lower numbers execute first
- Relative to siblings, not global

### conditions
- Determines if a step executes
- Applied to each individual step
- Uses conditional logic (see below)
- Optional (if omitted, step always runs)

## Execution Flow

1. **Start at root steps** (`parent_step_id = null`)
2. **Order by position** among roots
3. **Check conditions** - skip if false
4. **Execute step** if conditions pass
5. **Execute children** recursively
6. **Continue to next sibling**

Example:
```
Root Step 1 (pos: 1)
  └─ Child 1A (pos: 1, condition: X)
  └─ Child 1B (pos: 2, condition: Y)
Root Step 2 (pos: 2)
  └─ Child 2A (pos: 1)
```

Execution order:
1. Root Step 1
2. Child 1A (if condition X is true)
3. Child 1B (if condition Y is true)
4. Root Step 2
5. Child 2A

## Common Patterns

### Pattern 1: If/Then/Else

```json
{
  "steps": [
    {"name": "Decision", "position": 1, "parent_step_id": null},
    {"name": "Then", "position": 1, "parent_step_id": 1, "conditions": {...}},
    {"name": "Else", "position": 2, "parent_step_id": 1, "conditions": {...}}
  ]
}
```

### Pattern 2: Switch/Case

```json
{
  "steps": [
    {"name": "Switch", "position": 1, "parent_step_id": null},
    {"name": "Case A", "position": 1, "parent_step_id": 1, "conditions": {"field": "x", "operator": "==", "value": "A"}},
    {"name": "Case B", "position": 2, "parent_step_id": 1, "conditions": {"field": "x", "operator": "==", "value": "B"}},
    {"name": "Case C", "position": 3, "parent_step_id": 1, "conditions": {"field": "x", "operator": "==", "value": "C"}},
    {"name": "Default", "position": 4, "parent_step_id": 1}
  ]
}
```

### Pattern 3: Try/Catch (Approval/Rejection)

```json
{
  "steps": [
    {"name": "Try Action", "position": 1, "parent_step_id": null},
    {"name": "On Success", "position": 1, "parent_step_id": 1, "conditions": {"field": "result", "operator": "==", "value": "success"}},
    {"name": "On Failure", "position": 2, "parent_step_id": 1, "conditions": {"field": "result", "operator": "==", "value": "failure"}}
  ]
}
```

### Pattern 4: Parallel Then Sequential

```json
{
  "steps": [
    {"name": "Start", "position": 1, "parent_step_id": null},
    {"name": "Email", "position": 1, "parent_step_id": 1, "execution_mode": "parallel"},
    {"name": "SMS", "position": 2, "parent_step_id": 1, "execution_mode": "parallel"},
    {"name": "Continue", "position": 2, "parent_step_id": null}
  ]
}
```

## Best Practices

1. **Start with Draft**: Create workflows with `status: "draft"` while building branches
2. **Build Incrementally**: Create root steps first, then add branches
3. **Test Conditions**: Verify conditional logic before activating
4. **Use Descriptive Names**: Make branching logic clear from step names
5. **Document Complex Flows**: Add descriptions explaining branching logic
6. **Order Matters**: Set positions carefully for predictable execution
7. **Convergence Points**: Use root-level steps for merge points

## Limitations & Workarounds

### Limitation: Forward References
You cannot reference step IDs that don't exist yet in a single request.

**Workaround**: Use multi-step creation (create parent, get ID, create children)

### Limitation: No Automatic Convergence
Branches don't automatically merge back together.

**Workaround**: Create new root-level steps as convergence points

### Limitation: Complex Visualizations
The API creates logical structures but doesn't store visual layouts.

**Workaround**: Use `x_position` and `y_position` for canvas placement

## Examples

See:
- [workflow-branching-examples.php](../examples/workflow-branching-examples.php) - Complete code examples
- [API Reference](api-reference.md) - Full API documentation
- [Advanced Conditionals](advanced-conditionals.md) - Condition operators

## Related Topics

- [Conditional Logic](advanced-conditionals.md)
- [Workflow Execution](workflows.md)
- [Step Types](steps.md)
- [API Reference](api-reference.md)
