# Single-Request Branching Feature - Summary

## What We Implemented

Added support for creating complete branching workflows in a single API request using user-defined identifiers.

## New Fields

### `step_identifier` (string, optional)
- User-defined identifier for a step
- Must be unique within the request
- Used to reference steps without knowing database IDs

### `parent_step_identifier` (string, optional)
- References another step's `step_identifier`
- Creates parent-child relationship
- Resolved to actual `parent_step_id` automatically

## Files Changed

### 1. Request Validation
- **src/Http/Requests/CreateWorkflowRequest.php**
  - Added validation for `step_identifier` and `parent_step_identifier`
  - Added custom validator to check for duplicate identifiers
  - Added validation for non-existent parent references

- **src/Http/Requests/UpdateWorkflowRequest.php**
  - Same enhancements for update requests

### 2. Controller Logic
- **src/Http/Controllers/Api/WorkflowApiController.php**
  - `store()` method: Two-pass algorithm to resolve identifiers
    - First pass: Create all steps, build identifier → ID map
    - Second pass: Update `parent_step_id` based on identifiers
  - `update()` method: Same logic for adding new steps

### 3. Tests
- **tests/Feature/WorkflowApiTest.php**
  - Test creating branching workflow with identifiers
  - Test duplicate identifier validation
  - Test non-existent parent identifier validation
  - Test nested branching with identifiers

### 4. Documentation
- **docs/single-request-branching.md** (NEW)
  - Complete guide with examples
  - Validation rules
  - Best practices
  - Comparison with two-step approach

- **docs/api-reference.md** (UPDATED)
  - Added field documentation
  - Added single-request branching section
  - Links to detailed guide

## Example Usage

### Before (Two Requests Required)
```bash
# Request 1: Create parent
POST /api/forgepulse/workflows
{"steps": [{"name": "Parent", ...}]}

# Response: {"data": {"steps": [{"id": 123}]}}

# Request 2: Add children
PUT /api/forgepulse/workflows/1
{"steps": [{"parent_step_id": 123, ...}]}
```

### After (Single Request)
```bash
POST /api/forgepulse/workflows
{
  "steps": [
    {
      "step_identifier": "parent",
      "name": "Parent",
      ...
    },
    {
      "step_identifier": "child",
      "parent_step_identifier": "parent",
      "name": "Child",
      ...
    }
  ]
}
```

## Benefits

1. **Single Transaction**: All steps created atomically
2. **Simpler Code**: No need to parse response and make second request
3. **Better UX**: Faster workflow creation
4. **More Readable**: Identifiers are semantic, not numeric IDs
5. **Easier Testing**: Complete workflows in test fixtures
6. **Backward Compatible**: Existing code continues to work

## Validation

- Duplicate `step_identifier` → 422 error
- Non-existent `parent_step_identifier` → 422 error
- Can mix identifiers and database IDs in same request

## Version

- **Added in**: v1.3.0
- **Backward compatible**: Yes
- **Breaking changes**: None

## Related Documentation

- [Single-Request Branching Guide](../docs/single-request-branching.md)
- [Workflow Branching Guide](../docs/workflow-branching.md)
- [API Reference](../docs/api-reference.md)
