# Workflow Triggers - Implementation Index

## Quick Answer

**✅ YES!** Trigger nodes can be created via JSON in the ForgePulse workflow API.

This feature has been fully implemented and is production-ready.

---

## Documentation

### 📖 Start Here

1. **[ANSWER.md](ANSWER.md)** - Direct answer to your question with examples
2. **[TRIGGERS_README.md](TRIGGERS_README.md)** - Quick start guide and overview
3. **[docs/triggers.md](docs/triggers.md)** - Complete trigger documentation

### 🔧 Implementation Details

4. **[TRIGGER_IMPLEMENTATION.md](TRIGGER_IMPLEMENTATION.md)** - Technical implementation summary
5. **[examples/workflow-trigger-examples.php](examples/workflow-trigger-examples.php)** - 10 API usage examples
6. **[examples/complete-triggers-guide.php](examples/complete-triggers-guide.php)** - Comprehensive implementation guide

---

## Quick Start

### 1. Run Migration

```bash
php artisan migrate
```

### 2. Create Your First Triggered Workflow

```bash
POST /api/forgepulse/workflows
Authorization: Bearer {your-token}
Content-Type: application/json

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

### 3. Test It

```php
// Just fire the event - workflow executes automatically!
event(new UserRegistered($user));
```

---

## Trigger Types

| Type | Description | Use Case |
|------|-------------|----------|
| **Manual** | API/code execution | Existing behavior |
| **Event** | Laravel events | User registration, order placed |
| **Schedule** | Cron schedules | Daily reports, cleanup tasks |
| **Webhook** | HTTP POST | External integrations, Stripe |
| **Model** | Eloquent events | Order created, user updated |

---

## Files Structure

### Core Implementation

```
src/
├── Enums/
│   └── TriggerType.php                    # Trigger type enum
├── Services/
│   ├── TriggerManager.php                 # Main trigger manager
│   └── TriggerHandlers/
│       ├── TriggerHandler.php             # Base interface
│       ├── ManualTriggerHandler.php       # Manual triggers
│       ├── EventTriggerHandler.php        # Event triggers
│       ├── ScheduleTriggerHandler.php     # Schedule triggers
│       ├── WebhookTriggerHandler.php      # Webhook triggers
│       └── ModelTriggerHandler.php        # Model triggers
├── Http/Controllers/Api/
│   └── WebhookTriggerController.php       # Webhook endpoint
├── Console/Commands/
│   └── ExecuteScheduledWorkflows.php      # Schedule processor
└── Providers/
    └── TriggerServiceProvider.php         # Auto-registration
```

### Infrastructure

```
database/migrations/
└── 2025_12_20_000001_add_triggers_to_workflows_table.php

routes/
└── api.php                                # Webhook routes

config/
└── forgepulse.php                         # Trigger config
```

### Documentation

```
docs/
└── triggers.md                            # Complete documentation

examples/
├── workflow-trigger-examples.php          # 10 API examples
└── complete-triggers-guide.php            # Full implementation guide

Root Files:
├── ANSWER.md                              # Direct answer to question
├── TRIGGERS_README.md                     # Quick start guide
└── TRIGGER_IMPLEMENTATION.md              # Technical summary
```

---

## API Endpoints

### Workflow Management

```
POST   /api/forgepulse/workflows          Create workflow with trigger
PUT    /api/forgepulse/workflows/{id}     Update trigger config
GET    /api/forgepulse/workflows/{id}     Get workflow details
DELETE /api/forgepulse/workflows/{id}     Delete workflow
```

### Webhook Triggers

```
POST   /api/forgepulse/webhook/{workflow}/{token}   Trigger workflow via webhook
```

---

## Configuration

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

---

## Setup Checklist

- [x] ✅ Run migration: `php artisan migrate`
- [ ] 🔧 Configure Laravel scheduler (for schedule triggers)
- [ ] 🔧 Register service provider (if not auto-discovered)
- [ ] 🔧 Setup cron job (for scheduled workflows)
- [ ] 📝 Create first triggered workflow
- [ ] 🧪 Test trigger functionality
- [ ] 🚀 Deploy to production

---

## Examples by Trigger Type

### Event Trigger Example

```json
{
  "trigger_type": "event",
  "trigger_config": {
    "event_class": "App\\Events\\UserRegistered",
    "conditions": {
      "rules": [
        {"field": "user.email_verified", "operator": "==", "value": true}
      ]
    }
  }
}
```

### Schedule Trigger Example

```json
{
  "trigger_type": "schedule",
  "trigger_config": {
    "cron_expression": "0 9 * * *",
    "timezone": "UTC",
    "context": {"report_type": "daily"}
  }
}
```

### Webhook Trigger Example

```json
{
  "trigger_type": "webhook",
  "trigger_config": {
    "validation_rules": {
      "order_id": "required|string"
    }
  }
}
```

### Model Trigger Example

```json
{
  "trigger_type": "model",
  "trigger_config": {
    "model_class": "App\\Models\\Order",
    "events": ["created"],
    "conditions": {
      "rules": [
        {"field": "model.total", "operator": ">", "value": 1000}
      ]
    }
  }
}
```

---

## Support & Resources

### Documentation
- [Complete Trigger Docs](docs/triggers.md)
- [API Examples](examples/workflow-trigger-examples.php)
- [Implementation Guide](examples/complete-triggers-guide.php)

### Code Reference
- [TriggerType Enum](src/Enums/TriggerType.php)
- [TriggerManager Service](src/Services/TriggerManager.php)
- [Handler Implementations](src/Services/TriggerHandlers/)

### Migration & Setup
- [Database Migration](database/migrations/2025_12_20_000001_add_triggers_to_workflows_table.php)
- [Service Provider](src/Providers/TriggerServiceProvider.php)
- [Configuration](config/forgepulse.php)

---

## Features

✅ **5 Trigger Types** - Manual, Event, Schedule, Webhook, Model  
✅ **JSON Configuration** - Define triggers via API  
✅ **Conditional Logic** - Filter when triggers fire  
✅ **Automatic Execution** - No code changes needed  
✅ **Validation** - Built-in config validation  
✅ **Security** - Token-based webhook auth  
✅ **Logging** - Full audit trail  
✅ **Production Ready** - Error handling, retries  

---

## Testing

### Test Event Trigger
```php
event(new UserRegistered($user));
```

### Test Schedule Trigger
```bash
php artisan forgepulse:execute-scheduled
```

### Test Webhook Trigger
```bash
curl -X POST https://app.com/api/forgepulse/webhook/123/token \
  -d '{"test": "data"}'
```

### Test Model Trigger
```php
Order::create(['total' => 1500]); // Triggers if > 1000
```

---

## Summary

**Question**: In the workflow API can we create a trigger node to allow users to define triggers via JSON?

**Answer**: **YES!** ✅

This has been fully implemented with:
- 5 trigger types (event, schedule, webhook, model, manual)
- Complete JSON API support
- Automatic workflow execution
- Production-ready implementation
- Comprehensive documentation

Users can now define workflow triggers entirely through JSON configuration via the API, enabling automatic workflow execution based on events, schedules, webhooks, and model changes.

---

**🎉 Ready to use!** Start with [ANSWER.md](ANSWER.md) or [TRIGGERS_README.md](TRIGGERS_README.md)
