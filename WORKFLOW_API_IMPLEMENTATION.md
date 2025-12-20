# Workflow API Implementation Summary

## Implementation Completed

This document summarizes the workflow creation API that has been added to the ForgePulse package.

## Files Created

### 1. Request Validation Classes
- **`src/Http/Requests/CreateWorkflowRequest.php`** - Validates workflow creation requests
- **`src/Http/Requests/UpdateWorkflowRequest.php`** - Validates workflow update requests

Both classes include:
- Complete field validation rules
- Authorization checks (respects config settings)
- Custom error messages
- Default value preparation

### 2. Controller Methods
**`src/Http/Controllers/Api/WorkflowApiController.php`** - Extended with:
- `store()` - Create new workflow with steps
- `update()` - Update workflow and steps
- `destroy()` - Soft delete workflow

Features:
- Database transactions for data integrity
- Support for creating workflows with nested steps
- Validation integration
- Authorization checks
- Proper HTTP status codes (201 for create, 200 for update/delete)

### 3. API Routes
**`routes/api.php`** - Added routes:
- `POST /api/forgepulse/workflows` - Create workflow
- `PUT /api/forgepulse/workflows/{workflow}` - Update workflow
- `DELETE /api/forgepulse/workflows/{workflow}` - Delete workflow

### 4. Tests
**`tests/Feature/WorkflowApiTest.php`** - Comprehensive test suite with 15 tests:
- List workflows
- Show single workflow
- Create workflow without steps
- Create workflow with steps
- Validate required fields
- Validate step fields
- Update workflow
- Update workflow steps
- Add new steps when updating
- Delete workflow
- Handle 404 errors
- Set default values
- Create conditional steps
- Create template workflows

### 5. Documentation
**Updated files:**
- `docs/features.md` - Added create/update/delete endpoint documentation
- `README.md` - Updated API section with workflow management endpoints
- `docs/api-reference.md` - Complete API reference guide (NEW)

## API Capabilities

### Create Workflows
```bash
POST /api/forgepulse/workflows
```
- Create workflows with or without steps
- Support all step types (action, notification, webhook, delay, etc.)
- Support conditional logic
- Support parallel execution
- Set as template
- Configure timeouts
- Position steps on canvas (x/y coordinates)

### Update Workflows
```bash
PUT /api/forgepulse/workflows/{id}
```
- Update workflow metadata
- Update existing steps (by ID)
- Add new steps
- Partial updates (only send fields to change)

### Delete Workflows
```bash
DELETE /api/forgepulse/workflows/{id}
```
- Soft delete (can be restored)
- Authorization checks

## Request/Response Examples

### Create Workflow Request
```json
{
  "name": "User Onboarding",
  "description": "Automated user onboarding",
  "status": "active",
  "steps": [
    {
      "name": "Send Welcome Email",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\WelcomeEmail"
      },
      "position": 1
    }
  ]
}
```

### Create Workflow Response (201 Created)
```json
{
  "data": {
    "id": 1,
    "name": "User Onboarding",
    "description": "Automated user onboarding",
    "status": "active",
    "is_template": false,
    "version": "1.0.0",
    "steps": [...],
    "created_at": "2025-11-26T12:00:00.000Z"
  }
}
```

### Update Workflow Request
```json
{
  "name": "Enhanced Onboarding",
  "status": "inactive"
}
```

### Delete Workflow Response (200 OK)
```json
{
  "message": "Workflow deleted successfully."
}
```

## Validation Rules

### Workflow Fields
- `name` - Required, string, max 255 characters
- `status` - Required, enum (draft, active, inactive, archived)
- `description` - Optional, string, max 1000 characters
- `configuration` - Optional, array
- `is_template` - Optional, boolean (default: false)
- `version` - Optional, string (default: "1.0.0")
- `user_id` - Optional, integer (defaults to authenticated user)
- `team_id` - Optional, integer

### Step Fields
- `name` - Required, string, max 255 characters
- `type` - Required, enum (action, condition, delay, notification, webhook, event, job)
- `configuration` - Required, array
- `position` - Required, integer, min 0
- `description` - Optional, string, max 1000 characters
- `conditions` - Optional, array
- `x_position`, `y_position` - Optional, integer
- `parent_step_id` - Optional, integer
- `is_enabled` - Optional, boolean (default: true)
- `timeout` - Optional, integer, min 1
- `execution_mode` - Optional, enum (sequential, parallel)
- `parallel_group` - Optional, string

## Authorization & Permissions

The API respects the package's permission system:

```php
// config/forgepulse.php
'permissions' => [
    'enabled' => true, // Set to false to disable
],
```

When enabled:
- Create: Requires `create` permission on Workflow model
- Update: Requires `update` permission on specific workflow
- Delete: Requires `delete` permission on specific workflow

## Error Handling

### Validation Errors (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": ["The name field is required."],
    "steps.0.type": ["The steps.0.type field is required."]
  }
}
```

### Not Found (404)
```json
{
  "message": "No query results for model [Workflow] 123"
}
```

### Unauthorized (401)
```json
{
  "message": "Unauthenticated."
}
```

### Forbidden (403)
```json
{
  "message": "This action is unauthorized."
}
```

## Advanced Features

### Conditional Steps
```json
{
  "conditions": {
    "operator": "and",
    "rules": [
      {"field": "user.role", "operator": "==", "value": "admin"}
    ]
  }
}
```

### Parallel Execution
```json
{
  "execution_mode": "parallel",
  "parallel_group": "email-notifications"
}
```

### Step Timeouts
```json
{
  "timeout": 30
}
```

### Canvas Positioning
```json
{
  "x_position": 100,
  "y_position": 200
}
```

## Testing

Run the test suite:
```bash
vendor/bin/pest tests/Feature/WorkflowApiTest.php
```

15 comprehensive tests covering:
- CRUD operations
- Validation
- Error handling
- Edge cases
- Conditional logic
- Templates

## Integration Examples

### PHP/Laravel
```php
use Illuminate\Support\Facades\Http;

$response = Http::withToken($token)
    ->post('https://api.example.com/api/forgepulse/workflows', [
        'name' => 'My Workflow',
        'status' => 'active',
        'steps' => [...]
    ]);
```

### JavaScript
```javascript
const response = await fetch('/api/forgepulse/workflows', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    name: 'My Workflow',
    status: 'active'
  })
});
```

### cURL
```bash
curl -X POST https://api.example.com/api/forgepulse/workflows \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name":"My Workflow","status":"active"}'
```

## Migration Path

For existing ForgePulse users:
1. No database migrations required
2. Routes are automatically registered
3. Existing workflows remain unchanged
4. No breaking changes to existing functionality

## Security Considerations

1. **Authentication:** All endpoints require authentication via Laravel Sanctum
2. **Authorization:** Respects Laravel policies and package permissions
3. **Validation:** Comprehensive input validation
4. **SQL Injection:** Protected via Eloquent ORM
5. **Rate Limiting:** Configurable rate limits
6. **HTTPS:** Should be used in production

## Performance Considerations

1. **Transactions:** All create/update operations use database transactions
2. **Eager Loading:** Related models are eager loaded to prevent N+1 queries
3. **Pagination:** List endpoints return paginated results (20 per page)
4. **Validation:** Validation occurs before database operations

## Next Steps

Suggested enhancements:
1. Add bulk import endpoint for multiple workflows
2. Add workflow duplication endpoint
3. Add step reordering endpoint
4. Add workflow search/filter parameters
5. Add workflow export/import functionality
6. Add webhook notifications for workflow changes

## Support

For issues or questions:
- GitHub Issues: https://github.com/AlizHarb/forgepulse/issues
- Email: harbzali@gmail.com
- Documentation: https://alizharb.github.io/forgepulse/

## Version

- **Package Version:** 1.2.0+
- **Laravel Version:** 12.x
- **PHP Version:** 8.3+
- **API Version:** v1

## License

MIT License - Same as ForgePulse package
