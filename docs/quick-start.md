## Quick Start

Define a harness in `config/agents.php` and run it with the `Agents` facade.

### 1. Configure a Harness

A harness is a named agent: a system prompt, a tool set, a model, and a step budget.

```php
// config/agents.php
'harnesses' => [
    'assistant' => [
        'prompt' => 'You are a helpful assistant.',
        'tools'  => ['clock', 'calculator'],
        'max_steps' => 5,
    ],
],
```

### 2. Run the Harness

```php
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\ValueObjects\ToolContext;

$result = Agents::run(
    'assistant',
    'What is 1200.50 plus 300, and what day is it today?',
    ToolContext::forUser($request->user()),
);

$result->text;            // the model's answer
$result->toolCalls;       // tools the model invoked
$result->usage;           // prompt and completion token counts
```

### 3. Read the Result

`AgentResult` carries the full outcome of the run:

```php
$result->ok;              // bool, whether the run completed
$result->text;            // the final text response
$result->steps;           // number of tool-calling steps
$result->toolCalls;       // array of {name, arguments}
$result->usage;           // ['prompt_tokens' => int, 'completion_tokens' => int]
$result->finishReason;    // 'stop', 'tool_calls', 'max_steps', 'error'
```

### Next Steps

- Read about harness configuration in [Harnesses](harnesses.md).
- Explore the [Built-in Tools](built-in-tools.md).
- Learn how to write your own tools in [Custom Tools](custom-tools.md).
- Understand the security model in [Security Model](security.md).
