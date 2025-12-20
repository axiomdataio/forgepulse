<?php

/**
 * ===============================================
 * Swagger UI Integration for ForgePulse Actions
 * ===============================================
 *
 * Steps to integrate Swagger UI to visualize your workflow actions.
 */

// ============================================
// 1. ADD SWAGGER UI ROUTE (routes/web.php)
// ============================================

use Illuminate\Support\Facades\Route;

Route::get('/docs/actions', function () {
    return view('forgepulse::swagger-ui');
})->name('forgepulse.docs.actions');

// ============================================
// 2. CREATE VIEW (resources/views/swagger-ui.blade.php)
// ============================================

/*
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ForgePulse Actions - API Documentation</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.10.0/swagger-ui.css" />
    <style>
        html { box-sizing: border-box; overflow: -moz-scrollbars-vertical; overflow-y: scroll; }
        *, *:before, *:after { box-sizing: inherit; }
        body { margin:0; padding:0; }
    </style>
</head>
<body>
    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5.10.0/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.0/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            // Fetch OpenAPI spec from your API
            fetch('/api/forgepulse/actions/openapi', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    classes: [
                        'App\\Actions\\ProcessOrderService',
                        'App\\Actions\\SendEmailNotification',
                        'App\\Actions\\CreateUserService',
                        // Add all your action classes here
                    ]
                })
            })
            .then(response => response.json())
            .then(spec => {
                const ui = SwaggerUIBundle({
                    spec: spec,
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    presets: [
                        SwaggerUIBundle.presets.apis,
                        SwaggerUIStandalonePreset
                    ],
                    plugins: [
                        SwaggerUIBundle.plugins.DownloadUrl
                    ],
                    layout: "StandaloneLayout"
                });

                window.ui = ui;
            });
        };
    </script>
</body>
</html>
*/

// ============================================
// 3. AUTO-DISCOVER ACTIONS (Optional)
// ============================================

/**
 * Controller to serve OpenAPI spec for all discovered actions
 */
class ActionDocsController
{
    public function openapi(ActionSchema $schemaService): JsonResponse
    {
        // Auto-discover all action classes in your app
        $classes = $this->discoverActionClasses();

        $spec = $schemaService->exportAsOpenAPI($classes);

        return response()->json($spec);
    }

    protected function discoverActionClasses(): array
    {
        // Option 1: Scan specific directories
        $actionsPath = app_path('Actions');
        $classes = [];

        if (is_dir($actionsPath)) {
            $files = glob($actionsPath.'/*.php');
            foreach ($files as $file) {
                $className = 'App\\Actions\\'.basename($file, '.php');
                if (class_exists($className)) {
                    $classes[] = $className;
                }
            }
        }

        // Option 2: Use config
        $classes = array_merge($classes, config('forgepulse.actions.classes', []));

        return $classes;
    }
}

// ============================================
// 4. PUBLISH CONFIG (config/forgepulse.php)
// ============================================

/*
return [
    // ... existing config ...

    'actions' => [
        // List of action classes to document
        'classes' => [
            \App\Actions\ProcessOrderService::class,
            \App\Actions\SendEmailNotification::class,
            \App\Actions\CreateUserService::class,
        ],

        // Auto-discover actions in these directories
        'auto_discover' => [
            app_path('Actions'),
            app_path('Services'),
        ],
    ],

    'docs' => [
        // Enable Swagger UI
        'enabled' => env('FORGEPULSE_DOCS_ENABLED', true),

        // Swagger UI route
        'route' => '/docs/actions',

        // API spec route
        'spec_route' => '/api/forgepulse/actions/openapi',
    ],
];
*/

// ============================================
// 5. ALTERNATIVE: STATIC OpenAPI FILE
// ============================================

/**
 * Generate static openapi.json file for CI/CD or external tools
 */

// Run this artisan command
class GenerateOpenAPICommand extends Command
{
    protected $signature = 'forgepulse:generate-openapi {--output=openapi.json}';
    protected $description = 'Generate OpenAPI specification for workflow actions';

    public function handle(ActionSchema $schemaService): int
    {
        $classes = config('forgepulse.actions.classes', []);

        if (empty($classes)) {
            $this->error('No action classes configured. Add them to config/forgepulse.php');

            return 1;
        }

        $spec = $schemaService->exportAsOpenAPI($classes);

        $output = $this->option('output');
        file_put_contents($output, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("OpenAPI specification generated: {$output}");
        $this->info('You can now:');
        $this->info('  - Import into Postman');
        $this->info('  - Use with swagger-ui');
        $this->info('  - Generate client SDKs');

        return 0;
    }
}

// Register in service provider
class ForgePulseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateOpenAPICommand::class,
            ]);
        }
    }
}

// ============================================
// 6. USAGE EXAMPLES
// ============================================

// Visit Swagger UI
// http://your-app.test/docs/actions

// Download OpenAPI spec
// curl -X POST http://your-app.test/api/forgepulse/actions/openapi \
//   -H "Content-Type: application/json" \
//   -d '{"classes": ["App\\Actions\\ProcessOrderService"]}' \
//   > openapi.json

// Generate static file
// php artisan forgepulse:generate-openapi

// Import to Postman
// File > Import > openapi.json

// Generate TypeScript client
// npx openapi-generator-cli generate -i openapi.json -g typescript-axios -o ./src/api

// ============================================
// 7. BENEFITS
// ============================================

/*
✅ Interactive Documentation
   - Test actions directly from browser
   - See real request/response examples
   - Understand parameter requirements

✅ Team Collaboration
   - Share API specs with frontend teams
   - No more "what parameters does this take?"
   - Self-documenting code

✅ Code Generation
   - Generate TypeScript types
   - Generate API clients
   - Generate test fixtures

✅ Validation
   - CI/CD can validate schemas
   - Catch breaking changes
   - Ensure API contract compliance

✅ External Tools
   - Import into Postman
   - Use with API gateways
   - Integrate with monitoring tools
*/
