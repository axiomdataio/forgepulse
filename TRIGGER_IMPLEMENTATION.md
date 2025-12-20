# Workflow Triggers Implementation Summary

## Overview

Yes, **trigger nodes can be implemented** in the ForgePulse Workflow API to allow users to define workflow triggers via JSON. This implementation has been completed and is ready for use.

## Implementation Complete ✅

The following components have been created:

### 1. Database Migration
- **File**: `database/migrations/2025_12_20_000001_add_triggers_to_workflows_table.php`
- Adds columns: `trigger_config`, `trigger_type`, `auto_trigger_enabled`

### 2. Trigger Types Enum
- **File**: `src/Enums/TriggerType.php`
- Defines 5 trigger types:
  - `MANUAL` - Default programmatic execution
  - `EVENT` - Laravel event-based triggers
  - `SCHEDULE` - Cron-based scheduled execution
  - `WEBHOOK` - HTTP webhook triggers
  - `MODEL` - Eloquent model event triggers

### 3. Trigger Handlers
All handlers implement the `TriggerHandler` interface:

- `src/Services/TriggerHandlers/TriggerHandler.php` (Interface)
- `src/Services/TriggerHandlers/ManualTriggerHandler.php`
- `src/Services/TriggerHandlers/EventTriggerHandler.php`
- `src/Services/TriggerHandlers/ScheduleTriggerHandler.php`
- `src/Services/TriggerHandlers/WebhookTriggerHandler.php`
- `src/Services/TriggerHandlers/ModelTriggerHandler.php`

### 4. Trigger Manager
- **File**: `src/Services/TriggerManager.php`
- Manages registration/unregistration of triggers
- Validates trigger configurations
- Registers all triggers on application boot

### 5. API Controllers
- **File**: `src/Http/Controllers/Api/WebhookTriggerController.php`
- Handles incoming webhook requests
- Validates tokens and payloads
- Triggers workflow execution

### 6. Console Commands
- **File**: `src/Console/Commands/ExecuteScheduledWorkflows.php`
- Processes scheduled workflow triggers
- Should be run via Laravel scheduler

### 7. Service Provider
- **File**: `src/Providers/TriggerServiceProvider.php`
- Registers triggers on application boot
- Auto-registers all enabled workflow triggers

### 8. Routes
- **File**: `routes/api.php`
- Added webhook trigger route: `POST /api/forgepulse/webhook/{workflow}/{token}`

### 9. Configuration
- **File**: `config/forgepulse.php`
- Added `triggers` configuration section

### 10. Documentation
- **File**: `docs/triggers.md`
- Complete trigger documentation with examples

### 11. Examples
- **File**: `examples/workflow-trigger-examples.php`
- 10 practical examples of trigger usage

## Usage via API

### Create Event-Triggered Workflow

```bash
POST /api/forgepulse/workflows
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "User Onboarding",
  "status": "active",
  "trigger_type": "event",
  "trigger_config": {
    "event_class": "App\\Events\\UserRegistered",
    "conditions": {
      "operator": "and",
      "rules": [
        {"field": "user.email_verified", "operator": "==", "value": true}
      ]
    }
  },
  "auto_trigger_enabled": true,
  "steps": [...]
}
```

### Create Schedule-Triggered Workflow

```bash
POST /api/forgepulse/workflows
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Daily Report",
  "status": "active",
  "trigger_type": "schedule",
  "trigger_config": {
    "cron_expression": "0 9 * * *",
    "timezone": "UTC"
  },
  "auto_trigger_enabled": true,
  "steps": [...]
}
```

### Create Webhook-Triggered Workflow

```bash
POST /api/forgepulse/workflows
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "External Integration",
  "status": "active",
  "trigger_type": "webhook",
  "trigger_config": {
    "validation_rules": {
      "order_id": "required|string"
    }
  },
  "auto_trigger_enabled": true,
  "steps": [...]
}
```

**Response includes webhook URL:**

```json
{
  "data": {
    "id": 123,
    "webhook_url": "https://your-app.com/api/forgepulse/webhook/123/{token}",
    "webhook_token": "abc123..."
  }
}
```

### Create Model-Triggered Workflow

```bash
POST /api/forgepulse/workflows
Content-Type: application/json
Authorization: Bearer {token}

{
  "name": "Order Processing",
  "status": "active",
  "trigger_type": "model",
  "trigger_config": {
    "model_class": "App\\Models\\Order",
    "events": ["created", "updated"],
    "conditions": {
      "operator": "and",
      "rules": [
        {"field": "model.total", "operator": ">", "value": 100}
      ]
    }
  },
  "auto_trigger_enabled": true,
  "steps": [...]
}
```

## Installation Steps

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Register Service Provider

Add to `config/app.php`:

```php
'providers' => [
    // ...
    \AlizHarb\ForgePulse\Providers\TriggerServiceProvider::class,
],
```

### 3. Configure Laravel Scheduler

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('forgepulse:execute-scheduled')
        ->everyMinute()
        ->withoutOverlapping();
}
```

### 4. Update Cron

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 5. Install Cron Expression Parser (Optional)

For schedule triggers:

```bash
composer require dragonmantank/cron-expression
```

## Features

### ✅ Event Triggers
- Automatically execute workflows when Laravel events fire
- Support conditional execution based on event data
- Extract context from event properties

### ✅ Schedule Triggers
- Execute workflows on cron schedules
- Support multiple timezones
- Pass custom context to scheduled executions

### ✅ Webhook Triggers
- Receive HTTP webhooks from external services
- Validate incoming payloads
- Map webhook data to workflow context
- Auto-generate secure tokens

### ✅ Model Triggers
- React to Eloquent model events (created, updated, deleted)
- Filter with conditional logic
- Access model attributes and changes

### ✅ Manual Triggers
- Default behavior (existing functionality)
- Execute via API or code

## Security Features

- ✅ Webhook token authentication
- ✅ Payload validation
- ✅ Rate limiting support
- ✅ Conditional execution
- ✅ Audit logging

## Configuration Options

All trigger settings are in `config/forgepulse.php`:

```php
'triggers' => [
    'enabled' => true,
    'auto_register' => true,
    'types' => [
        'manual' => ManualTriggerHandler::class,
        'event' => EventTriggerHandler::class,
        'schedule' => ScheduleTriggerHandler::class,
        'webhook' => WebhookTriggerHandler::class,
        'model' => ModelTriggerHandler::class,
    ],
],
```

## Advantages Over Manual Triggering

### Before (Manual Only)
```php
// Must write code for every trigger point
public function register(Request $request)
{
    $user = User::create($request->validated());
    
    // Manually find and execute workflow
    $workflow = Workflow::where('name', 'User Onboarding')->first();
    $workflow->execute(['user_id' => $user->id]);
}
```

### After (Automatic Triggers)
```json
{
  "trigger_type": "model",
  "trigger_config": {
    "model_class": "App\\Models\\User",
    "events": ["created"]
  }
}
```

No code changes needed - workflows trigger automatically!

## Testing

### Test Event Trigger

```php
// Dispatch event
event(new UserRegistered($user));

// Workflow executes automatically
```

### Test Webhook Trigger

```bash
curl -X POST https://your-app.com/api/forgepulse/webhook/123/token \
  -H "Content-Type: application/json" \
  -d '{"order_id": "ORD-123", "total": 150.00}'
```

### Test Schedule Trigger

```bash
php artisan forgepulse:execute-scheduled
```

## Migration Path

Existing workflows continue to work as-is. To add triggers:

1. Update workflow via API with `trigger_type` and `trigger_config`
2. Set `auto_trigger_enabled` to `true`
3. Triggers register automatically on next application boot

## Limitations

- Schedule triggers require Laravel scheduler setup
- Model triggers work only with Eloquent models
- Webhook triggers need publicly accessible endpoints
- Event triggers require events to be dispatched

## Future Enhancements

Potential additions:
- Database query triggers (watch for specific database changes)
- File system triggers (react to file uploads/changes)
- Queue triggers (execute when queue depth reaches threshold)
- Custom trigger handlers (plugin architecture)

## Summary

**Yes, trigger nodes have been fully implemented!** Users can now define workflow triggers via JSON through the API, enabling automatic workflow execution based on:

- Laravel events
- Cron schedules  
- HTTP webhooks
- Model events
- Manual execution

The implementation is production-ready and includes:
- Complete handler architecture
- API endpoints
- Documentation
- Examples
- Security features
- Configuration options

All files have been created and are ready for use.
