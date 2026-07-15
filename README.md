# Eloquent Agents

AI tool-calling foundation for Laravel. Built on [Prism](https://github.com/prism-php/prism), it adds the
two things Prism leaves to you: a set of **ready-made tools** the model can call, and a small **extension
API** so your app registers its own tools, agents ("harnesses"), and prompts.

## Features

- **Batteries included.** Ships safe, generic tools: scoped Eloquent read/write, HTTP fetch, web search,
  storage read, a clock, and a calculator.
- **Harnesses.** A harness is a named agent: a system prompt + a tool set + a model + a step budget. Define
  one in a config array or a class.
- **Bring your own tools.** Implement one method, register the class, and the model can call it.
- **Default-deny security.** Every tool runs against a `ToolContext` (the acting user). Data tools enforce an
  allowlist of models and columns and scope every row to that user.
- **MCP ready.** Adopt any Prism tool, including MCP server tools, through the registry.

## Installation

```bash
composer require whilesmart/eloquent-agents
php artisan vendor:publish --tag=agents-config
```

Set credentials the Prism way (provider-native env vars), e.g. `GEMINI_API_KEY`, then choose a default model:

```dotenv
AGENTS_PROVIDER=gemini
AGENTS_MODEL=gemini-2.0-flash
```

## Quick start

```php
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\ValueObjects\ToolContext;

// config/agents.php
'harnesses' => [
    'assistant' => [
        'prompt' => 'example-assistant',          // a prompt name or literal text
        'tools'  => ['clock', 'calculator'],
        'max_steps' => 5,
    ],
],

// anywhere in the app
$result = Agents::harness('assistant')->run(
    'What is 1200.50 plus 300, and what was last month?',
    ToolContext::forUser($user),
);

$result->text;       // the model's answer
$result->toolCalls;  // what it called
$result->usage;      // token usage
```

## Built-in tools

| Name | Permission | What it does |
|------|------------|--------------|
| `clock` | read | Current date/time and relative anchors (start of last month, etc.). |
| `calculator` | read | Deterministic arithmetic over `+ - * /` and parentheses. |
| `eloquent.query` | read | List/aggregate the user's own records across an allowlist of models. |
| `eloquent.write` | write | Create/update allowlisted models+columns, owner-scoped. Off by default. |
| `http.fetch` | external | GET an allowlisted host, size-capped. |
| `web.search` | external | Search via a pluggable driver (null driver by default). |
| `storage.read` | read | List/read files on a disk, jailed to a path prefix. |

The Eloquent tools read nothing until you declare an allowlist:

```php
// config/agents.php
'eloquent' => [
    'allow_writes' => true,
    'models' => [
        'transactions' => [
            'model' => App\Models\Transaction::class,
            'owner_key' => 'user_id',                    // rows scoped to the acting user
            'readable' => ['amount', 'type', 'description', 'created_at'],
            'writable' => ['amount', 'type', 'description'],
        ],
    ],
],
```

## Writing your own tool

```php
use Whilesmart\Agents\Tools\AbstractTool;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

class CreateReminderTool extends AbstractTool
{
    public function name(): string { return 'create_reminder'; }

    public function description(): string { return 'Create a reminder for the user.'; }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('text', 'What to be reminded about'),
            ParameterSpec::string('due', 'Due date (YYYY-MM-DD)'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $reminder = $context->user->reminders()->create($arguments);

        return ['id' => $reminder->id];
    }
}
```

Register it by config (`'tools' => [CreateReminderTool::class]`), at runtime
(`Agents::registerTool(new CreateReminderTool)`), or enable auto-discovery of `App\Ai\Tools`.

### Parameter shapes

`ParameterSpec` covers scalars (`string`, `number`, `boolean`), `enum`, and lists
(`arrayOf`). For structured input, `object` and `arrayOfObject` take nested specs:

```php
ParameterSpec::arrayOfObject('assignments', 'One entry per transaction.', [
    ParameterSpec::number('transaction_id', 'The transaction id'),
    ParameterSpec::string('category_name', 'Category to apply'),
    ParameterSpec::string('note', 'Optional note', required: false),
]);
```

A nested spec marked `required: false` is omitted from the item's required
fields. Reach for `arrayOfObject` when a tool acts on many records in one call:
it keeps related values in one item, where parallel lists can arrive misaligned.

### Grounding the prompt in the user

`systemPrompt()` receives the run's `ToolContext`, so the prompt can state facts
about who is asking rather than leaving the model to guess them:

```php
public function systemPrompt(?ToolContext $context = null): string
{
    $currency = $context?->user?->getConfigValue('default-currency') ?? 'unknown';

    return "Report every amount in {$currency}.";
}
```

The context is null when a prompt is built outside a run (listing harnesses, for
example), so always guard with `?->`. Telling the model a fact beats instructing
it to honour one it was never given.

## Harness as a class

When config arrays are not enough, extend `AbstractHarness`:

```php
use Whilesmart\Agents\Harness\AbstractHarness;
use Whilesmart\Agents\Enums\ToolPermission;

class FinanceHarness extends AbstractHarness
{
    public function name(): string { return 'finance'; }

    public function systemPrompt(): string { return 'You are a careful finance assistant.'; }

    public function toolNames(): array { return ['clock', 'calculator', 'eloquent.query']; }

    public function allowedPermissions(): array { return [ToolPermission::READ]; }   // refuse write tools
}
```

`Agents::registerHarness('finance', FinanceHarness::class)`.

## Overriding prompts

`run()` resolves a harness prompt by name in this order: `config('agents.prompts.{name}')`, a published file
at `resources/vendor/agents/prompts/{name}.md`, then the package default. A value that matches no prompt is
used as literal text.

## MCP tools

```php
use Prism\Prism\Tools\LaravelMcpTool;
use Whilesmart\Agents\Engines\Prism\Tools\McpTool;

Agents::registerTool(McpTool::wrap(new LaravelMcpTool($mcpServerTool)));
```

## Engine abstraction

Prism is a swappable layer, not a hard dependency of your tools. Everything you write (tools, harnesses,
parameters, results) speaks the package's own types. All Prism SDK code lives behind the `AgentEngine`
contract under `src/Engines/Prism/`:

- `PrismEngine` builds and runs the Prism request and maps the response to an `AgentResult`.
- `PrismToolAdapter` translates a package `Tool` into a Prism tool.

To move to a different backend, implement `AgentEngine` and rebind it:

```php
// a service provider
$this->app->bind(\Whilesmart\Agents\Contracts\AgentEngine::class, MyEngine::class);
```

Nothing else changes: tools, harnesses, registries, and the facade are untouched.

## Console

```bash
php artisan agents:tools        # list registered tools
php artisan agents:harnesses    # list registered harnesses
php artisan agents:run finance "how much did I spend last month?" --user=1
```

## Security model

Default-deny. Tools authorize against the `ToolContext` before running. `eloquent.query`/`eloquent.write`
expose nothing outside the configured model+column allowlist and scope every row to the acting user;
`eloquent.write` additionally requires `allow_writes` and applies only whitelisted columns (mass-assignment
guard), forcing the owner key on create. `http.fetch` honours a host allowlist; `storage.read` is jailed to a
path prefix and rejects traversal. The agent loop is bounded by `agents.max_steps`.

## Testing

```bash
make check     # pint + phpunit via Docker
# or
composer test
```

## License

MIT
