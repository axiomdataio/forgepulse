<?php

/**
 * ===============================================
 * Database-Stored OpenAPI Schemas - Complete Guide
 * ===============================================
 *
 * Why store schemas in the database?
 */

// ============================================
// THE PROBLEM
// ============================================

/*
BEFORE (Code-only schemas):
❌ Need code deploy to update schemas
❌ Can't manage external services (Stripe, Twilio)
❌ No UI for non-developers
❌ Hard to version and rollback
❌ Can't override third-party code
*/

// ============================================
// THE SOLUTION: DATABASE-STORED SCHEMAS
// ============================================

/*
AFTER (DB-stored schemas):
✅ Update schemas at runtime (no deploy)
✅ Document external APIs (Stripe, Twilio, etc.)
✅ UI for schema management
✅ Version control built-in
✅ Override any action's schema
✅ Centralized action registry
*/

// ============================================
// SCHEMA PRIORITY (How it works)
// ============================================

/*
When you request a schema for "App\Actions\ProcessOrder":

Priority 1: DATABASE
├─ Check action_schemas table
├─ If found and active → return DB schema
└─ Allows runtime overrides

Priority 2: CODE (ProvidesSchema interface)
├─ Check if class has schema() method
├─ If exists → return code-based schema
└─ Self-describing actions

Priority 3: REFLECTION
├─ Use PHP reflection
├─ Auto-detect from method signature
└─ Zero-setup fallback

This gives you MAXIMUM FLEXIBILITY!
*/

use AlizHarb\ForgePulse\Models\ActionSchema;
use Illuminate\Support\Facades\Http;

// ============================================
// 1. MANAGING INTERNAL ACTIONS
// ============================================

// Sync code-based schemas to database
Http::post('/api/forgepulse/action-schemas/sync', [
    'classes' => [
        'App\Actions\ProcessOrderService',
        'App\Actions\SendEmailNotification',
    ],
]);

// Override a code-based schema
$schema = ActionSchema::where('action_class', 'App\Actions\ProcessOrderService')->first();
$schema->update([
    'schema' => [
        // Your custom OpenAPI schema
        'summary' => 'Process Order (Custom)',
        'parameters' => [
            'customerId' => [
                'type' => 'integer',
                'required' => true,
                'minimum' => 1,
                'description' => 'Customer ID from CRM',
            ],
            // Add more custom parameters
        ],
    ],
]);

// Now this schema is used instead of code-based one!

// ============================================
// 2. MANAGING EXTERNAL SERVICES
// ============================================

// Create Stripe charge schema
ActionSchema::create([
    'action_class' => 'Stripe::Charge',
    'action_type' => 'external',
    'is_external' => true,
    'name' => 'Stripe: Create Charge',
    'category' => 'payments',
    'schema' => [
        'summary' => 'Create Stripe Charge',
        'operationId' => 'createStripeCharge',
        'parameters' => [
            'amount' => [
                'type' => 'integer',
                'required' => true,
                'minimum' => 50,
                'description' => 'Amount in cents',
            ],
            'payment_method' => [
                'type' => 'string',
                'required' => true,
                'pattern' => '^pm_[a-zA-Z0-9]+$',
            ],
        ],
        'responses' => [
            '200' => [
                'description' => 'Charge created',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'status' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'config' => [
        'endpoint' => 'https://api.stripe.com/v1/charges',
        'method' => 'POST',
        'auth' => 'bearer',
    ],
]);

// Now use in workflow
$workflow->steps()->create([
    'name' => 'Charge Payment',
    'type' => 'action',
    'configuration' => [
        'class' => 'Stripe::Charge', // Uses DB schema!
        'parameters' => [
            'amount' => 2000,
            'payment_method' => 'pm_123',
        ],
    ],
]);

// ============================================
// 3. IMPORT FROM EXTERNAL OPENAPI SPECS
// ============================================

// Import from public OpenAPI spec
Http::post('/api/forgepulse/action-schemas/import', [
    'source_url' => 'https://api.stripe.com/v1/openapi',
    'name' => 'Stripe: Create Customer',
    'category' => 'payments',
    'operation_id' => 'PostCustomers',
]);

// ============================================
// 4. API ENDPOINTS
// ============================================

/*
GET /api/forgepulse/action-schemas
  - List all schemas (with filters)
  - Query params: type, category, is_external, is_active

GET /api/forgepulse/action-schemas/{id}
  - Get single schema

POST /api/forgepulse/action-schemas
  - Create new schema (manual or external)

PUT /api/forgepulse/action-schemas/{id}
  - Update schema (only if source=manual or imported)

DELETE /api/forgepulse/action-schemas/{id}
  - Delete schema (soft delete)

POST /api/forgepulse/action-schemas/sync
  - Sync code-based schemas to DB

POST /api/forgepulse/action-schemas/import
  - Import from external OpenAPI URL
*/

// ============================================
// 5. VERSIONING
// ============================================

// Create new version of schema
$oldSchema = ActionSchema::find(1);
$newVersion = $oldSchema->createVersion([
    'summary' => 'Process Order v2',
    'parameters' => [
        // Updated parameters
    ],
]);

// Old workflows use v1
// New workflows use v2
// Gradual migration!

// ============================================
// 6. DISCOVERY AND FILTERING
// ============================================

// List all external services
$external = ActionSchema::external()->active()->get();

// Payment-related actions
$payments = ActionSchema::category('payments')->get();

// Search by tag
$smsActions = ActionSchema::whereJsonContains('tags', 'sms')->get();

// Get all available actions for UI dropdown
$available = ActionSchema::active()
    ->orderBy('category')
    ->orderBy('name')
    ->get()
    ->groupBy('category');

// ============================================
// 7. UI BUILDER INTEGRATION
// ============================================

// Frontend requests available actions
fetch('/api/forgepulse/action-schemas?is_active=true')
    .then(schemas => {
        // Render dropdown grouped by category
        schemas.data.forEach(schema => {
            dropdown.addOption(schema.id, schema.name, schema.category);
        });
    });

// User selects action
// Frontend requests schema details
fetch(`/api/forgepulse/action-schemas/${selectedId}`)
    .then(schema => {
        // Generate form fields from schema.schema.parameters
        renderDynamicForm(schema.schema.parameters);
    });

// ============================================
// 8. EXTERNAL SERVICE HANDLER
// ============================================

// Implement handler for external services
class ExternalServiceHandler
{
    public function handle(WorkflowStep $step, array $context): array
    {
        $config = $step->configuration;
        $actionClass = $config['class'];

        // Get schema from database
        $schema = ActionSchema::where('action_class', $actionClass)
            ->where('is_external', true)
            ->firstOrFail();

        // Make HTTP request to external service
        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->getApiKey($schema)}",
        ])->timeout($schema->config['timeout'] ?? 30)
            ->post($schema->config['endpoint'], $config['parameters']);

        return $response->json();
    }

    protected function getApiKey(ActionSchema $schema): string
    {
        // Get from config or env based on service
        return match ($schema->category) {
            'payments' => config('services.stripe.secret'),
            'notifications' => config('services.twilio.token'),
            default => throw new \Exception('Unknown service'),
        };
    }
}

// ============================================
// 9. REAL-WORLD USE CASES
// ============================================

/*
USE CASE 1: E-COMMERCE
- Stripe payment schema in DB
- Twilio SMS schema in DB
- SendGrid email schema in DB
- Update Stripe params without deploy
- Add new payment methods instantly

USE CASE 2: MULTI-TENANT
- Each tenant has custom action schemas
- Override parameters per tenant
- Different API keys per tenant
- Tenant-specific validations

USE CASE 3: API GATEWAY
- Document all backend services
- Centralized schema registry
- Validate requests before proxying
- Track which APIs are used

USE CASE 4: MIGRATION
- Start with code-based schemas
- Gradually move to DB
- Override specific actions
- No breaking changes

USE CASE 5: COMPLIANCE
- Audit trail of schema changes
- Version control built-in
- Track who modified what
- Rollback instantly
*/

// ============================================
// BENEFITS SUMMARY
// ============================================

/*
DATABASE STORAGE GIVES YOU:

✅ RUNTIME FLEXIBILITY
   - Update without code deploys
   - A/B test different schemas
   - Feature flags for new parameters

✅ EXTERNAL SERVICE MANAGEMENT
   - Document Stripe, Twilio, etc.
   - Centralized service catalog
   - Validate before calling APIs

✅ UI BUILDER
   - Dynamic form generation
   - Auto-complete with validation
   - Non-developers can configure

✅ VERSIONING & ROLLBACK
   - Track schema changes
   - Multiple versions live
   - Instant rollback

✅ GOVERNANCE
   - See what services are used
   - Audit who changed what
   - Enforce standards

✅ OVERRIDE ANYTHING
   - Code-based actions
   - Third-party packages
   - Legacy services

✅ DISCOVERY
   - Search by category/tag
   - Filter internal vs external
   - API catalog

COMBINED WITH CODE-BASED SCHEMAS:
- DB for flexibility
- Code for version control
- Best of both worlds!
*/

// ============================================
// MIGRATION STRATEGY
// ============================================

/*
PHASE 1: KEEP EXISTING CODE
- Leave ProvidesSchema in code
- Nothing breaks
- Optional DB usage

PHASE 2: SYNC TO DATABASE
- Run sync endpoint
- Populate database
- Still using code as source of truth

PHASE 3: GRADUAL OVERRIDE
- Override specific actions in DB
- DB takes priority
- Code is fallback

PHASE 4: EXTERNAL SERVICES
- Add Stripe, Twilio, etc.
- Only in database
- No code needed

PHASE 5: FULL DATABASE
- Move all schemas to DB
- Use UI to manage
- Code is fallback for new actions
*/
