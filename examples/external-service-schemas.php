<?php

/**
 * ===============================================
 * Database-Stored Schemas - External Services
 * ===============================================
 *
 * Managing schemas for external APIs like Stripe, Twilio, SendGrid, etc.
 */

use AlizHarb\ForgePulse\Models\ActionSchema;
use Illuminate\Support\Facades\Http;

// ============================================
// 1. CREATE STRIPE CHARGE SCHEMA
// ============================================

ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'action_type' => 'external',
    'method' => null,
    'is_external' => true,
    'source' => 'manual',
    'name' => 'Stripe: Create Charge',
    'description' => 'Create a charge using Stripe Payment API',
    'category' => 'payments',
    'tags' => ['stripe', 'payment', 'checkout'],

    'schema' => [
        'summary' => 'Create Stripe Charge',
        'description' => 'Charge a payment method using Stripe API',
        'operationId' => 'createStripeCharge',
        'tags' => ['payments'],

        'parameters' => [
            'amount' => [
                'type' => 'integer',
                'description' => 'Amount in cents (e.g., 2000 = $20.00)',
                'required' => true,
                'minimum' => 50,
                'example' => 2000,
                'x-source' => 'config',
            ],
            'currency' => [
                'type' => 'string',
                'description' => 'Three-letter ISO currency code',
                'required' => true,
                'default' => 'usd',
                'enum' => ['usd', 'eur', 'gbp', 'cad'],
                'example' => 'usd',
            ],
            'payment_method' => [
                'type' => 'string',
                'description' => 'Stripe payment method ID',
                'required' => true,
                'pattern' => '^pm_[a-zA-Z0-9]+$',
                'example' => 'pm_1234567890',
                'x-source' => 'context',
            ],
            'customer' => [
                'type' => 'string',
                'description' => 'Stripe customer ID',
                'required' => false,
                'pattern' => '^cus_[a-zA-Z0-9]+$',
                'example' => 'cus_ABC123',
                'x-source' => 'context',
            ],
            'description' => [
                'type' => 'string',
                'required' => false,
                'maxLength' => 1000,
                'example' => 'Order #12345 - Product Purchase',
            ],
        ],

        'responses' => [
            '200' => [
                'description' => 'Charge created successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string', 'description' => 'Charge ID', 'example' => 'ch_1234567890'],
                                'amount' => ['type' => 'integer', 'description' => 'Amount charged'],
                                'status' => ['type' => 'string', 'enum' => ['succeeded', 'pending', 'failed']],
                                'receipt_url' => ['type' => 'string', 'format' => 'uri'],
                            ],
                            'required' => ['id', 'amount', 'status'],
                        ],
                    ],
                ],
            ],
        ],

        'x-timeout' => 30,
    ],

    'config' => [
        'endpoint' => 'https://api.stripe.com/v1/charges',
        'method' => 'POST',
        'auth' => 'bearer',
        'timeout' => 30,
        'retries' => 3,
    ],
]);

// ============================================
// 2. CREATE TWILIO SMS SCHEMA
// ============================================

ActionSchema::create([
    'action_class' => 'Twilio::SendSMS',
    'action_type' => 'external',
    'is_external' => true,
    'source' => 'manual',
    'name' => 'Twilio: Send SMS',
    'description' => 'Send SMS message via Twilio',
    'category' => 'notifications',
    'tags' => ['twilio', 'sms', 'messaging'],

    'schema' => [
        'summary' => 'Send SMS via Twilio',
        'operationId' => 'sendTwilioSMS',

        'parameters' => [
            'to' => [
                'type' => 'string',
                'description' => 'Recipient phone number (E.164 format)',
                'required' => true,
                'pattern' => '^\+[1-9]\d{1,14}$',
                'example' => '+15551234567',
                'x-source' => 'context',
            ],
            'from' => [
                'type' => 'string',
                'description' => 'Twilio phone number',
                'required' => true,
                'pattern' => '^\+[1-9]\d{1,14}$',
                'example' => '+15559876543',
                'x-source' => 'config',
            ],
            'body' => [
                'type' => 'string',
                'description' => 'Message body',
                'required' => true,
                'maxLength' => 1600,
                'example' => 'Your order has shipped! Track: https://example.com/track/123',
            ],
        ],

        'responses' => [
            '200' => [
                'description' => 'SMS sent successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'sid' => ['type' => 'string', 'description' => 'Message SID'],
                                'status' => ['type' => 'string', 'enum' => ['queued', 'sent', 'delivered', 'failed']],
                                'price' => ['type' => 'string', 'description' => 'Cost of message'],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'x-timeout' => 15,
    ],

    'config' => [
        'endpoint' => 'https://api.twilio.com/2010-04-01/Accounts/{AccountSid}/Messages.json',
        'method' => 'POST',
        'auth' => 'basic',
        'timeout' => 15,
    ],
]);

// ============================================
// 3. CREATE SENDGRID EMAIL SCHEMA
// ============================================

ActionSchema::create([
    'action_class' => 'SendGrid::SendEmail',
    'action_type' => 'external',
    'is_external' => true,
    'source' => 'manual',
    'name' => 'SendGrid: Send Email',
    'description' => 'Send transactional email via SendGrid',
    'category' => 'notifications',
    'tags' => ['sendgrid', 'email', 'transactional'],

    'schema' => [
        'summary' => 'Send Email via SendGrid',
        'operationId' => 'sendSendGridEmail',

        'parameters' => [
            'to' => [
                'type' => 'string',
                'format' => 'email',
                'description' => 'Recipient email address',
                'required' => true,
                'example' => 'customer@example.com',
                'x-source' => 'context',
            ],
            'from' => [
                'type' => 'string',
                'format' => 'email',
                'description' => 'Sender email address',
                'required' => true,
                'example' => 'noreply@yourcompany.com',
                'x-source' => 'config',
            ],
            'subject' => [
                'type' => 'string',
                'required' => true,
                'maxLength' => 255,
                'example' => 'Your Order Confirmation',
            ],
            'html' => [
                'type' => 'string',
                'description' => 'HTML email body',
                'required' => true,
                'example' => '<h1>Thank you!</h1><p>Your order has been confirmed.</p>',
            ],
            'template_id' => [
                'type' => 'string',
                'description' => 'SendGrid template ID (if using templates)',
                'required' => false,
                'pattern' => '^d-[a-f0-9]{32}$',
                'example' => 'd-1234567890abcdef1234567890abcdef',
            ],
        ],

        'responses' => [
            '200' => [
                'description' => 'Email sent successfully',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message_id' => ['type' => 'string'],
                                'status' => ['type' => 'string', 'enum' => ['queued', 'sent']],
                            ],
                        ],
                    ],
                ],
            ],
        ],

        'x-timeout' => 20,
    ],

    'config' => [
        'endpoint' => 'https://api.sendgrid.com/v3/mail/send',
        'method' => 'POST',
        'auth' => 'bearer',
        'timeout' => 20,
    ],
]);

// ============================================
// 4. IMPORT FROM EXTERNAL OPENAPI SPEC
// ============================================

// Import Stripe's published OpenAPI spec
Http::post('/api/forgepulse/action-schemas/import', [
    'source_url' => 'https://raw.githubusercontent.com/stripe/openapi/master/openapi/spec3.json',
    'name' => 'Stripe: Create Customer',
    'category' => 'payments',
    'operation_id' => 'PostCustomers',
]);

// ============================================
// 5. SYNC CODE-BASED SCHEMAS TO DB
// ============================================

// Sync all internal actions to database
Http::post('/api/forgepulse/action-schemas/sync', [
    'classes' => [
        'App\Actions\ProcessOrderService',
        'App\Actions\SendEmailNotification',
        'App\Actions\CreateUserService',
    ],
]);

// ============================================
// 6. LIST ALL AVAILABLE ACTIONS
// ============================================

// Get all external services
$externalActions = Http::get('/api/forgepulse/action-schemas', [
    'is_external' => true,
])->json();

// Get payment-related actions
$paymentActions = Http::get('/api/forgepulse/action-schemas', [
    'category' => 'payments',
])->json();

// Get all active actions (internal + external)
$allActions = Http::get('/api/forgepulse/action-schemas')->json();

// ============================================
// 7. USE IN WORKFLOW
// ============================================

use AlizHarb\ForgePulse\Models\Workflow;

// Create workflow using external Stripe action
$workflow = Workflow::create([
    'name' => 'Order Processing with Stripe',
    'status' => 'active',
]);

$workflow->steps()->create([
    'name' => 'Charge Payment',
    'type' => 'action',
    'order' => 1,
    'configuration' => [
        'class' => 'Stripe::Charge', // References DB schema
        'mode' => 'sync',
        'parameters' => [
            'amount' => '{{order.total_cents}}',
            'currency' => 'usd',
            'payment_method' => '{{order.payment_method_id}}',
            'customer' => '{{customer.stripe_id}}',
            'description' => 'Order #{{order.id}}',
        ],
    ],
]);

$workflow->steps()->create([
    'name' => 'Send Confirmation SMS',
    'type' => 'action',
    'order' => 2,
    'configuration' => [
        'class' => 'Twilio::SendSMS', // References DB schema
        'mode' => 'dispatch',
        'parameters' => [
            'to' => '{{customer.phone}}',
            'from' => '+15559876543',
            'body' => 'Your payment of ${{order.total}} was processed successfully!',
        ],
    ],
]);

// ============================================
// 8. UPDATE EXTERNAL SCHEMA AT RUNTIME
// ============================================

// Update Stripe schema (e.g., add new parameter)
$schema = ActionSchema::where('action_class', 'Stripe::Charge')->first();

$updatedSchema = $schema->schema;
$updatedSchema['parameters']['metadata'] = [
    'type' => 'object',
    'description' => 'Custom metadata key-value pairs',
    'required' => false,
];

$schema->update(['schema' => $updatedSchema]);

// ============================================
// 9. VERSION MANAGEMENT
// ============================================

// Create new version of schema
$oldSchema = ActionSchema::find(1);
$newVersion = $oldSchema->createVersion([
    // Updated OpenAPI schema with breaking changes
    'summary' => 'Create Stripe Charge v2',
    'parameters' => [
        // ... new parameter structure
    ],
]);

// Keep old version active for existing workflows
// Switch new workflows to new version

// ============================================
// BENEFITS
// ============================================

/*
✅ RUNTIME MANAGEMENT
   - Update schemas without deploying code
   - A/B test different parameter sets
   - Roll back schema changes instantly

✅ EXTERNAL SERVICES
   - Document third-party APIs
   - Validate before calling external services
   - Share API knowledge across team

✅ UI BUILDER
   - Dynamic form generation
   - Auto-complete with real schemas
   - Validation in the UI

✅ GOVERNANCE
   - Centralized action registry
   - Track what external services are used
   - Audit schema changes

✅ VERSIONING
   - Multiple schema versions
   - Gradual migration
   - Backward compatibility

✅ DISCOVERY
   - List all available actions
   - Search by category/tag
   - Filter internal vs external

✅ OVERRIDE CODE
   - Override reflection-based schemas
   - Add metadata to existing actions
   - Customize without changing code
*/
