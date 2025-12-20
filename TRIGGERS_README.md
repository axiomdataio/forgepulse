# Workflow Triggers - Feature Complete! 🎉

## Summary

**YES!** You can now create trigger nodes in the ForgePulse Workflow API to allow users to define workflow triggers via JSON.

This feature has been **fully implemented** and is ready for use.

## What Was Added

### Core Functionality

✅ **5 Trigger Types**
- Manual (existing behavior)
- Event (Laravel events)
- Schedule (cron-based)
- Webhook (HTTP endpoints)
- Model (Eloquent events)

✅ **Database Schema**
- New migration adds trigger configuration columns to workflows table

✅ **Handler Architecture**
- Base `TriggerHandler` interface
- 5 concrete handler implementations
- Validation and context extraction

✅ **API Integration**
- Create workflows with triggers via JSON API
- Webhook endpoint for external triggers
- Update trigger configuration dynamically

✅ **Automatic Registration**
- Service provider registers triggers on boot
- Conditional execution support
- Error handling and logging

## Quick Start

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Create Event-Triggered Workflow

```bash
POST /api/forgepulse/workflows
```

```json
{
  "name": "User Onboarding",
  "status": "active",
  "trigger_type": "event",
  "trigger_config": {
    "event_class": "App\\Events\\UserRegistered"
  },
  "auto_trigger_enabled": true,
  "steps": [
    {
      "name": "Send Welcome Email",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\WelcomeEmail",
        "recipients": ["{{user.email}}"]
      },
      "position": 1
    }
  ]
}
```

### 3. Workflow Executes Automatically!

```php
// Just fire the event
event(new UserRegistered($user));

// Workflow executes automatically - no manual trigger needed!
```

## Trigger Types Overview

| Type | Description | Use Case |
|------|-------------|----------|
| **Manual** | Default programmatic execution | API calls, custom code |
| **Event** | Fires when Laravel events occur | User registration, order placed |
| **Schedule** | Executes on cron schedule | Daily reports, cleanup tasks |
| **Webhook** | Triggered by HTTP POST | External integrations, payment processors |
| **Model** | Fires on Eloquent model events | Order created, user updated |

## Example Use Cases

### 1. User Registration Workflow

```json
{
  "trigger_type": "event",
  "trigger_config": {
    "event_class": "App\\Events\\UserRegistered"
  }
}
```

### 2. Daily Report Generation

```json
{
  "trigger_type": "schedule",
  "trigger_config": {
    "cron_expression": "0 9 * * *",
    "timezone": "UTC"
  }
}
```

### 3. Stripe Payment Processing

```json
{
  "trigger_type": "webhook",
  "trigger_config": {
    "validation_rules": {
      "type": "required|string",
      "data.object.amount": "required|numeric"
    }
  }
}
```

### 4. High-Value Order Alerts

```json
{
  "trigger_type": "model",
  "trigger_config": {
    "model_class": "App\\Models\\Order",
    "events": ["created"],
    "conditions": {
      "operator": "and",
      "rules": [
        {"field": "model.total", "operator": ">", "value": 1000}
      ]
    }
  }
}
```

## Files Created

### Core Implementation
- ✅ `src/Enums/TriggerType.php` - Trigger type enum
- ✅ `src/Services/TriggerManager.php` - Trigger management service
- ✅ `src/Services/TriggerHandlers/TriggerHandler.php` - Base interface
- ✅ `src/Services/TriggerHandlers/ManualTriggerHandler.php`
- ✅ `src/Services/TriggerHandlers/EventTriggerHandler.php`
- ✅ `src/Services/TriggerHandlers/ScheduleTriggerHandler.php`
- ✅ `src/Services/TriggerHandlers/WebhookTriggerHandler.php`
- ✅ `src/Services/TriggerHandlers/ModelTriggerHandler.php`

### API & Controllers
- ✅ `src/Http/Controllers/Api/WebhookTriggerController.php`
- ✅ Updated `routes/api.php` with webhook endpoint

### Infrastructure
- ✅ `database/migrations/2025_12_20_000001_add_triggers_to_workflows_table.php`
- ✅ `src/Console/Commands/ExecuteScheduledWorkflows.php`
- ✅ `src/Providers/TriggerServiceProvider.php`
- ✅ Updated `config/forgepulse.php` with trigger configuration

### Documentation
- ✅ `docs/triggers.md` - Complete trigger documentation
- ✅ `examples/workflow-trigger-examples.php` - 10 practical examples
- ✅ `TRIGGER_IMPLEMENTATION.md` - Implementation summary
- ✅ Updated `src/Models/Workflow.php` with trigger methods

## Setup Instructions

### 1. Database Setup

```bash
php artisan migrate
```

### 2. Register Service Provider

Add to `config/app.php` (if not auto-discovered):

```php
'providers' => [
    \AlizHarb\ForgePulse\Providers\TriggerServiceProvider::class,
],
```

### 3. Configure Laravel Scheduler (for Schedule Triggers)

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->command('forgepulse:execute-scheduled')
        ->everyMinute()
        ->withoutOverlapping();
}
```

Setup cron:

```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

### 4. Install Dependencies (Optional)

For cron expression parsing:

```bash
composer require dragonmantank/cron-expression
```

## API Reference

### Create Workflow with Trigger

```bash
POST /api/forgepulse/workflows
Authorization: Bearer {token}
Content-Type: application/json

{
  "name": "Workflow Name",
  "status": "active",
  "trigger_type": "event|schedule|webhook|model|manual",
  "trigger_config": {
    // Type-specific configuration
  },
  "auto_trigger_enabled": true,
  "steps": [...]
}
```

### Update Trigger Configuration

```bash
PUT /api/forgepulse/workflows/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "trigger_config": {
    "cron_expression": "0 10 * * *"
  }
}
```

### Enable/Disable Auto-Trigger

```bash
PUT /api/forgepulse/workflows/{id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "auto_trigger_enabled": false
}
```

### Trigger Webhook (Public Endpoint)

```bash
POST /api/forgepulse/webhook/{workflow_id}/{token}
Content-Type: application/json

{
  "your": "payload",
  "data": "here"
}
```

## Configuration Options

All settings in `config/forgepulse.php`:

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

## Features

### ✅ JSON-Based Configuration
Define triggers completely via API using JSON

### ✅ Conditional Execution
Add conditions to prevent unnecessary workflow runs

### ✅ Context Extraction
Automatic extraction of relevant data from trigger events

### ✅ Validation
Built-in validation for all trigger configurations

### ✅ Security
Token-based authentication for webhooks

### ✅ Flexibility
Easy to extend with custom trigger handlers

### ✅ Monitoring
Full logging and error handling

## Testing

### Test Event Trigger

```php
// Fire event
event(new UserRegistered($user));

// Check execution was created
$execution = WorkflowExecution::latest()->first();
```

### Test Webhook Trigger

```bash
curl -X POST https://your-app.com/api/forgepulse/webhook/123/token \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

### Test Schedule Trigger

```bash
php artisan forgepulse:execute-scheduled
```

## Advantages

### Before (Manual Only)
```php
// Must write code everywhere
User::created(function ($user) {
    $workflow = Workflow::where('name', 'Onboarding')->first();
    $workflow->execute(['user_id' => $user->id]);
});
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

✨ **Zero code changes needed!**

## Next Steps

1. ✅ Run migration
2. ✅ Create your first triggered workflow
3. ✅ Test the trigger
4. ✅ Monitor execution logs
5. ✅ Scale to production

## Documentation

- 📖 [Complete Trigger Documentation](docs/triggers.md)
- 💻 [Code Examples](examples/workflow-trigger-examples.php)
- 📋 [Implementation Details](TRIGGER_IMPLEMENTATION.md)

## Support

For questions or issues:
1. Check the documentation
2. Review examples
3. Check application logs
4. Open an issue

---

**🎉 Trigger nodes are now available in ForgePulse!**

Users can define workflow triggers via JSON through the API, enabling fully automated workflow execution based on events, schedules, webhooks, and model changes.
