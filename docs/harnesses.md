## Harnesses

A harness is a named agent: it bundles a system prompt, a set of tools, a model provider, and a step budget into a single configurable unit. Harnesses are the primary way to define agent behaviour in your application.

### Config Array Harnesses

The simplest way to define a harness is through the `agents.harnesses` config array:

```php
// config/agents.php
'harnesses' => [
    'finance-assistant' => [
        'prompt'      => 'finance-assistant',        // prompt name or inline text
        'tools'       => ['clock', 'calculator', 'eloquent.query'],
        'provider'    => 'gemini',                    // optional, overrides default
        'model'       => 'gemini-2.0-flash',          // optional, overrides default
        'temperature' => 0.1,                         // optional, overrides default
        'max_steps'   => 5,                           // optional, capped by AGENTS_MAX_STEPS
        'permissions' => ['read'],                    // optional, restricts tool permission levels
    ],
],
```

Values:
- `prompt` - A prompt name resolved through the prompt registry, or literal text.
- `tools` - Tool names registered in the tool registry.
- `permissions` - When present, only tools whose `ToolPermission` matches one of the listed values are available to this harness.
- `provider`, `model`, `temperature`, `max_steps` - Per-harness overrides for the run configuration.

### Class Harnesses

When you need custom logic, extend `AbstractHarness`:

```php
use Whilesmart\Agents\Harness\AbstractHarness;
use Whilesmart\Agents\Enums\ToolPermission;

class FinanceHarness extends AbstractHarness
{
    public function name(): string
    {
        return 'finance';
    }

    public function systemPrompt(): string
    {
        return 'You are a careful finance assistant. Use the calculator for all arithmetic.';
    }

    public function toolNames(): array
    {
        return ['clock', 'calculator', 'eloquent.query'];
    }

    public function allowedPermissions(): array
    {
        return [ToolPermission::READ];
    }
}
```

Register the class:

```php
Agents::registerHarness('finance', FinanceHarness::class);
```

Or at class registration time in `config/agents.php`:

```php
'harnesses' => [
    'finance' => FinanceHarness::class,
],
```

### Running a Harness

```php
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\ValueObjects\ToolContext;

$result = Agents::run(
    'assistant',
    'How much did I spend last month?',
    ToolContext::forUser($user),
);
```

### Per-Run Overrides

Override provider, model, temperature, max steps, or max tokens for a single run:

```php
$result = Agents::run('assistant', $input, $context, media: [], overrides: [
    'provider'    => 'anthropic',
    'model'       => 'claude-3-5-sonnet-latest',
    'temperature' => 0.5,
    'maxSteps'    => 10,
    'maxTokens'   => 4096,
]);
```

### Step Budget

The agent loop has a layered step cap. The hard upper bound is set by `config('agents.max_steps')` (default 8) and cannot be exceeded even if the harness or per-run override asks for more.
