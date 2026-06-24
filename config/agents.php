<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default provider and model
    |--------------------------------------------------------------------------
    |
    | The Prism provider and model a harness uses when it does not specify its
    | own. Credentials are read by Prism from each provider's native config
    | (config/prism.php), which in turn reads provider-native env vars such as
    | GEMINI_API_KEY, ANTHROPIC_API_KEY, OPENAI_API_KEY, GROQ_API_KEY. This
    | package never introduces a generic single-key variable.
    |
    */
    'provider' => env('AGENTS_PROVIDER', 'gemini'),
    'model' => env('AGENTS_MODEL', 'gemini-2.0-flash'),
    'temperature' => (float) env('AGENTS_TEMPERATURE', 0),

    /*
    |--------------------------------------------------------------------------
    | Agent loop step cap
    |--------------------------------------------------------------------------
    |
    | Hard upper bound on the number of tool-calling steps any harness may run,
    | regardless of what the harness requests. Bounds runaway cost.
    |
    */
    'max_steps' => (int) env('AGENTS_MAX_STEPS', 8),

    /*
    |--------------------------------------------------------------------------
    | Per-call output cap and HTTP timeout
    |--------------------------------------------------------------------------
    |
    | max_tokens bounds a single response so large outputs are not truncated
    | mid-stream (which some providers surface as an unhandled finish reason);
    | null leaves the provider default. request_timeout (seconds) caps a single
    | provider HTTP call so a hung request fails fast instead of blocking the
    | caller; 0 disables it.
    |
    */
    'max_tokens' => env('AGENTS_MAX_TOKENS') !== null ? (int) env('AGENTS_MAX_TOKENS') : null,
    'request_timeout' => (int) env('AGENTS_REQUEST_TIMEOUT', 0),

    /*
    |--------------------------------------------------------------------------
    | Routes
    |--------------------------------------------------------------------------
    */
    'register_routes' => env('AGENTS_REGISTER_ROUTES', false),
    'route_prefix' => env('AGENTS_ROUTE_PREFIX', 'api'),
    'route_middleware' => ['api', 'auth:sanctum'],

    /*
    |--------------------------------------------------------------------------
    | Tools
    |--------------------------------------------------------------------------
    |
    | Tool classes registered into the ToolRegistry on top of the package
    | defaults. Each must implement Whilesmart\Agents\Contracts\Tool (extend
    | Whilesmart\Agents\Tools\AbstractTool). Apps add their own here, or call
    | Agents::registerTool() at runtime.
    |
    */
    'tools' => [
        // App\Ai\Tools\CreateInvoiceTool::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, every concrete AbstractTool under the given namespace/path
    | is registered automatically, in addition to the `tools` list above.
    |
    */
    'discovery' => [
        'enabled' => env('AGENTS_DISCOVERY', false),
        'namespace' => 'App\\Ai\\Tools',
        'path' => app()->basePath('app/Ai/Tools'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Harnesses
    |--------------------------------------------------------------------------
    |
    | Named agent configurations. A value may be a class implementing
    | Whilesmart\Agents\Contracts\Harness, or an array consumed by
    | GenericHarness with keys: prompt, tools, provider, model, temperature,
    | max_steps, permissions.
    |
    */
    'harnesses' => [
        // 'finance-assistant' => [
        //     'prompt' => 'finance-assistant',
        //     'tools' => ['clock', 'calculator', 'eloquent.query'],
        //     'max_steps' => 5,
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Prompt overrides
    |--------------------------------------------------------------------------
    |
    | Inline overrides for named prompts, keyed by prompt name. A published
    | resources/prompts/{name}.md file also overrides the package default.
    |
    */
    'prompts' => [
        // 'finance-assistant' => 'You are a careful finance assistant...',
    ],

    /*
    |--------------------------------------------------------------------------
    | Built-in tool configuration
    |--------------------------------------------------------------------------
    */
    'eloquent' => [
        // Per-model allowlist. Keys are tool-facing aliases; values declare the
        // model, the column the agent may read/write, and the column scoping
        // rows to the acting user. Nothing is queryable unless listed here.
        'models' => [
            // 'transactions' => [
            //     'model' => App\Models\Transaction::class,
            //     'owner_key' => 'user_id',
            //     'readable' => ['amount', 'type', 'description', 'created_at'],
            //     'writable' => ['amount', 'type', 'description'],
            // ],
        ],
        'max_rows' => (int) env('AGENTS_ELOQUENT_MAX_ROWS', 50),
        'allow_writes' => env('AGENTS_ELOQUENT_ALLOW_WRITES', false),
    ],

    'http' => [
        'allowed_hosts' => array_filter(explode(',', (string) env('AGENTS_HTTP_ALLOWED_HOSTS', ''))),
        'timeout' => (int) env('AGENTS_HTTP_TIMEOUT', 10),
        'max_bytes' => (int) env('AGENTS_HTTP_MAX_BYTES', 100_000),
    ],

    'web_search' => [
        'driver' => env('AGENTS_WEB_SEARCH_DRIVER', 'null'),
        'max_results' => (int) env('AGENTS_WEB_SEARCH_MAX_RESULTS', 5),
    ],

    'storage' => [
        'disk' => env('AGENTS_STORAGE_DISK', 'local'),
        'path_prefix' => env('AGENTS_STORAGE_PREFIX', ''),
        'max_bytes' => (int) env('AGENTS_STORAGE_MAX_BYTES', 100_000),
    ],
];
