## Engine Abstraction

Prism is the default LLM backend, but it is a swappable layer. Everything your app writes (tools, harnesses, parameters, results) speaks the package's own SDK-agnostic types. All Prism-specific code lives behind the `AgentEngine` contract under `src/Engines/Prism/`.

### Architecture

```
Your Tool/Harness code (uses package types only)
        |
AgentEngine contract (Whilesmart\Agents\Contracts\AgentEngine)
        |
PrismEngine (default, src/Engines/Prism/)
        |
Prism SDK
```

### AgentEngine

The engine is responsible for running an agent loop: sending a system prompt and user input to the LLM, handling tool call responses, and returning a result.

```php
namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;

interface AgentEngine
{
    public function run(AgentRequest $request): AgentResult;
}
```

### AgentRequest

The engine receives a fully resolved request in the package's own types:

```php
new AgentRequest(
    systemPrompt: '...',
    input: 'User message',
    tools: [...],           // array of Tool instances
    context: $toolContext,
    provider: 'gemini',
    model: 'gemini-2.0-flash',
    temperature: 0.1,
    maxSteps: 8,
    media: [],              // multimodal input images/documents/audio/video
    maxTokens: null,
);
```

### Replacing the Engine

To use a different LLM backend, implement `AgentEngine` and rebind the contract:

```php
// In a service provider
$this->app->bind(
    \Whilesmart\Agents\Contracts\AgentEngine::class,
    \App\Ai\MyEngine::class,
);
```

Your engine receives `AgentRequest` and must return `AgentResult`. Nothing else in the package changes: tools, harnesses, registries, and the facade remain untouched.

### Making Tools Engine-Agnostic

The package's tool system is designed to never depend on any SDK:

- `Tool` and `AbstractTool` know nothing about Prism or any other SDK.
- `ParameterSpec` describes parameters in a schema-independent way.
- `ToolContext` is a plain PHP object carrying the user and scope.
- `AgentResult` expresses the outcome in package-only types.

The engine's adapter (for Prism, `PrismToolAdapter`) translates each package `Tool` into whatever the SDK expects. If you write a custom engine, you build this adapter for your SDK instead.
