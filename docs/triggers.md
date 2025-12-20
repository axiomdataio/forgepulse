# Workflow Triggers

ForgePulse supports automatic workflow triggers, allowing workflows to execute in response to various events without manual intervention.

## Available Trigger Types

### 1. Manual Trigger (Default)

Workflows are executed programmatically via code or API.

```php
$workflow->execute(['user_id' => 123]);
```

### 2. Event Trigger

Execute workflows when Laravel events are fired.

```json
{
  "trigger_type": "event",
  "trigger_config": {
    "event_class": "App\\Events\\UserRegistered",
    "conditions": {
      "operator": "and",
      "rules": [
        {
          "field": "user.email_verified",
          "operator": "==",
          "value": true
        }
      ]
    }
  },
  "auto_trigger_enabled": true
}
```

### 3. Schedule Trigger

Execute workflows on a cron schedule.

```json
{
  "trigger_type": "schedule",
  "trigger_config": {
    "cron_expression": "0 9 * * *",
    "timezone": "America/New_York",
    "context": {
      "report_type": "daily_summary"
    }
  },
  "auto_trigger_enabled": true
}
```

### 4. Webhook Trigger

Execute workflows via incoming HTTP webhooks.

```json
{
  "trigger_type": "webhook",
  "trigger_config": {
    "token": "auto-generated-if-not-provided",
    "validation_rules": {
      "user_id": "required|integer",
      "action": "required|string"
    },
    "payload_mapping": {
      "internal_user_id": "user_id",
      "event_type": "action"
    }
  },
  "auto_trigger_enabled": true
}
```

### 5. Model Trigger

Execute workflows when Eloquent models are created, updated, or deleted.

```json
{
  "trigger_type": "model",
  "trigger_config": {
    "model_class": "App\\Models\\Order",
    "events": ["created", "updated"],
    "conditions": {
      "operator": "and",
      "rules": [
        {
          "field": "model.status",
          "operator": "==",
          "value": "completed"
        },
        {
          "field": "model.total",
          "operator": ">",
          "value": 100
        }
      ]
    }
  },
  "auto_trigger_enabled": true
}
```

## API Usage Examples

### Create Workflow with Event Trigger

```bash
POST /api/forgepulse/workflows
```

```json
{
  "name": "User Onboarding Workflow",
  "description": "Automatically triggered when user registers",
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
    },
    {
      "name": "Create User Profile",
      "type": "action",
      "configuration": {
        "action_class": "App\\Actions\\CreateUserProfile",
        "parameters": {
          "user_id": "{{user.id}}"
        }
      },
      "position": 2
    }
  ]
}
```

### Create Workflow with Schedule Trigger

```bash
POST /api/forgepulse/workflows
```

```json
{
  "name": "Daily Report Generation",
  "description": "Generates daily reports at 9 AM",
  "status": "active",
  "trigger_type": "schedule",
  "trigger_config": {
    "cron_expression": "0 9 * * *",
    "timezone": "UTC",
    "context": {
      "report_type": "daily",
      "recipients": ["admin@example.com"]
    }
  },
  "auto_trigger_enabled": true,
  "steps": [
    {
      "name": "Generate Report",
      "type": "action",
      "configuration": {
        "action_class": "App\\Actions\\GenerateDailyReport"
      },
      "position": 1
    },
    {
      "name": "Email Report",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\DailyReport",
        "recipients": ["{{recipients}}"]
      },
      "position": 2
    }
  ]
}
```

### Create Workflow with Webhook Trigger

```bash
POST /api/forgepulse/workflows
```

```json
{
  "name": "External Order Processing",
  "description": "Processes orders from external webhook",
  "status": "active",
  "trigger_type": "webhook",
  "trigger_config": {
    "validation_rules": {
      "order_id": "required|string",
      "customer_email": "required|email",
      "total": "required|numeric"
    },
    "payload_mapping": {
      "internal_order_id": "order_id",
      "user_email": "customer_email",
      "order_total": "total"
    }
  },
  "auto_trigger_enabled": true,
  "steps": [
    {
      "name": "Process Order",
      "type": "action",
      "configuration": {
        "action_class": "App\\Actions\\ProcessExternalOrder",
        "parameters": {
          "order_id": "{{internal_order_id}}",
          "email": "{{user_email}}",
          "total": "{{order_total}}"
        }
      },
      "position": 1
    }
  ]
}
```

**Response includes webhook URL:**

```json
{
  "data": {
    "id": 123,
    "name": "External Order Processing",
    "trigger_type": "webhook",
    "webhook_url": "https://your-app.com/api/forgepulse/webhook/123/{token}",
    "webhook_token": "a1b2c3d4e5f6..."
  }
}
```

**Trigger the workflow:**

```bash
POST https://your-app.com/api/forgepulse/webhook/123/a1b2c3d4e5f6
Content-Type: application/json

{
  "order_id": "ORD-12345",
  "customer_email": "customer@example.com",
  "total": 150.00
}
```

### Create Workflow with Model Trigger

```bash
POST /api/forgepulse/workflows
```

```json
{
  "name": "High-Value Order Alert",
  "description": "Alerts admin when large orders are created",
  "status": "active",
  "trigger_type": "model",
  "trigger_config": {
    "model_class": "App\\Models\\Order",
    "events": ["created"],
    "conditions": {
      "operator": "and",
      "rules": [
        {
          "field": "model.total",
          "operator": ">",
          "value": 1000
        }
      ]
    }
  },
  "auto_trigger_enabled": true,
  "steps": [
    {
      "name": "Notify Admin",
      "type": "notification",
      "configuration": {
        "notification_class": "App\\Notifications\\HighValueOrderAlert",
        "recipients": ["admin@example.com"],
        "data": {
          "order_id": "{{model.id}}",
          "total": "{{model.total}}",
          "customer": "{{model.customer_name}}"
        }
      },
      "position": 1
    }
  ]
}
```

## Cron Expression Examples

Common cron expressions for schedule triggers:

| Expression | Description |
|------------|-------------|
| `* * * * *` | Every minute |
| `0 * * * *` | Every hour |
| `0 9 * * *` | Every day at 9:00 AM |
| `0 9 * * 1` | Every Monday at 9:00 AM |
| `0 0 1 * *` | First day of every month at midnight |
| `*/15 * * * *` | Every 15 minutes |
| `0 */6 * * *` | Every 6 hours |
| `0 9-17 * * 1-5` | Every hour from 9 AM to 5 PM, Monday through Friday |

## Laravel Scheduler Setup

For schedule triggers to work, add this to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Process scheduled workflow triggers
    $schedule->command('forgepulse:execute-scheduled')
        ->everyMinute()
        ->withoutOverlapping();
}
```

Then ensure your cron is configured:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## Update Workflow Trigger

### Enable/Disable Auto-Trigger

```bash
PUT /api/forgepulse/workflows/{id}
```

```json
{
  "auto_trigger_enabled": false
}
```

### Change Trigger Configuration

```bash
PUT /api/forgepulse/workflows/{id}
```

```json
{
  "trigger_config": {
    "cron_expression": "0 10 * * *",
    "timezone": "America/New_York"
  }
}
```

## Context Data Available in Workflow

Different trigger types provide different context data:

### Event Trigger Context

```php
[
  'user' => [...],              // Event public properties
  '_event_class' => 'App\\Events\\UserRegistered',
  '_triggered_at' => '2024-12-20T10:30:00Z'
]
```

### Schedule Trigger Context

```php
[
  'scheduled_execution' => true,
  '_triggered_at' => '2024-12-20T09:00:00Z',
  '_cron_expression' => '0 9 * * *',
  // Plus any custom context from trigger_config
]
```

### Webhook Trigger Context

```php
[
  'order_id' => 'ORD-12345',    // Webhook payload
  '_webhook_ip' => '192.168.1.1',
  '_webhook_user_agent' => 'curl/7.68.0',
  '_triggered_at' => '2024-12-20T10:30:00Z'
]
```

### Model Trigger Context

```php
[
  'model' => [...],             // Model attributes
  'model_id' => 123,
  'model_class' => 'App\\Models\\Order',
  '_event' => 'created',
  '_changes' => [...],          // For 'updated' events
  '_triggered_at' => '2024-12-20T10:30:00Z'
]
```

## Best Practices

1. **Always validate trigger configuration** before enabling auto-triggers
2. **Use conditions** to prevent unnecessary workflow executions
3. **Test webhook endpoints** with proper authentication before production use
4. **Monitor scheduled workflows** to ensure they execute as expected
5. **Use appropriate cron expressions** to avoid overloading the system
6. **Set up proper logging** for trigger events
7. **Implement retry logic** for failed webhook triggers
8. **Use queues** for long-running workflows triggered by events

## Security Considerations

- **Webhook tokens** should be kept secret and rotated regularly
- **Validate all webhook payloads** before processing
- **Use rate limiting** on webhook endpoints
- **Monitor for abuse** of public webhook endpoints
- **Restrict model triggers** to trusted models only
- **Audit trigger registrations** regularly

## Troubleshooting

### Trigger Not Firing

1. Check `auto_trigger_enabled` is `true`
2. Verify workflow `status` is `active`
3. Check trigger configuration is valid
4. For schedules, ensure Laravel scheduler is running
5. For events, verify event is being dispatched
6. Check application logs for errors

### Webhook Returns 401

- Verify webhook token matches configuration
- Check token hasn't been regenerated

### Schedule Not Executing

- Verify cron expression is valid
- Check Laravel scheduler is running (`php artisan schedule:run`)
- Verify timezone configuration
- Check system cron is configured correctly

## Next Steps

- [Conditional Logic](./advanced-conditionals.md) - Configure trigger conditions
- [API Reference](./api-reference.md) - Complete API documentation
- [Examples](./examples.md) - More trigger examples
