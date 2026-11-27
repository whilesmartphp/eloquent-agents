<?php

namespace Whilesmart\Agents;

use Closure;
use LogicException;
use Whilesmart\Agents\Contracts\AgentEngine;
use Whilesmart\Agents\Contracts\Harness;
use Whilesmart\Agents\Contracts\RunGuard;
use Whilesmart\Agents\Contracts\StreamingHarness;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Events\AgentRunCompleted;
use Whilesmart\Agents\Registries\AgentRegistry;
use Whilesmart\Agents\Registries\HarnessRegistry;
use Whilesmart\Agents\Registries\ToolRegistry;
use Whilesmart\Agents\ValueObjects\AgentDefinition;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\AgentStreamEvent;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * The package entry point, exposed via the Agents facade. Wraps the tool and
 * harness registries and runs harnesses.
 */
class AgentManager
{
    public function __construct(
        protected ToolRegistry $tools,
        protected HarnessRegistry $harnesses,
        protected AgentRegistry $agents = new AgentRegistry,
    ) {}

    public function agents(): AgentRegistry
    {
        return $this->agents;
    }

    public function agent(string $name): AgentDefinition
    {
        return $this->agents->get($name);
    }

    public function runAgent(string $name, string $input, ToolContext $context, array $media = []): AgentResult
    {
        $agent = $this->agent($name);

        if ($agent->harness === null) {
            throw new LogicException("Agent [{$name}] cannot run because it has no harness.");
        }

        return $this->run($agent->harness, $input, $context, $media, array_filter([
            'provider' => $agent->provider,
            'model' => $agent->model,
        ], fn ($value) => $value !== null));
    }

    public function harness(string $name): Harness
    {
        return $this->harnesses->get($name);
    }

    /**
     * @param  array<int, mixed>  $media  Engine-ready media for multimodal input. Empty for text-only.
     * @param  array<string, mixed>  $overrides  Per-run overrides: provider, model, temperature, maxSteps, maxTokens.
     */
    public function run(string $harness, string $input, ToolContext $context, array $media = [], array $overrides = []): AgentResult
    {
        return $this->harness($harness)->run($input, $context, $media, $overrides);
    }

    /**
     * Run a harness while forwarding progress to $onEvent. Harnesses that cannot
     * stream fall back to a buffered run, emitting no events.
     *
     * @param  callable(AgentStreamEvent):void  $onEvent
     * @param  array<int, mixed>  $media  Engine-ready media for multimodal input. Empty for text-only.
     * @param  array<string, mixed>  $overrides  Per-run overrides: provider, model, temperature, maxSteps, maxTokens.
     */
    public function stream(string $harness, string $input, ToolContext $context, callable $onEvent, array $media = [], array $overrides = []): AgentResult
    {
        $resolved = $this->harness($harness);

        if (! $resolved instanceof StreamingHarness) {
            return $resolved->run($input, $context, $media, $overrides);
        }

        return $resolved->stream($input, $context, $onEvent, $media, $overrides);
    }

    /**
     * One model call with no tools and no harness: classify, summarise,
     * extract. It passes through the bound RunGuard and publishes the same
     * completion event as a harness run, so it is metered the same way.
     *
     * @param  array{name?: string, system?: string, provider?: string, model?: string, temperature?: float, maxTokens?: int, media?: array<int, mixed>}  $options
     */
    public function complete(string $input, ToolContext $context, array $options = []): AgentResult
    {
        $name = $options['name'] ?? 'completion';
        $request = new AgentRequest(
            systemPrompt: $options['system'] ?? '',
            input: $input,
            tools: [],
            context: $context,
            provider: $options['provider'] ?? config('agents.provider'),
            model: $options['model'] ?? config('agents.model'),
            temperature: $options['temperature'] ?? (float) config('agents.temperature'),
            maxSteps: 1,
            media: $options['media'] ?? [],
            maxTokens: $options['maxTokens'] ?? config('agents.max_tokens'),
        );

        $result = app(RunGuard::class)->handle($name, $request, function (AgentRequest $guarded) use (&$request) {
            $request = $guarded;

            return app(AgentEngine::class)->run($guarded);
        });

        AgentRunCompleted::dispatch($name, $request, $result);

        return $result;
    }

    /**
     * @param  Tool|class-string<Tool>  $tool
     */
    public function registerTool(Tool|string $tool): static
    {
        $this->tools->register($tool);

        return $this;
    }

    public function extendTool(string $name, Closure|Tool $tool): static
    {
        $this->tools->extend($name, $tool);

        return $this;
    }

    /**
     * @param  Harness|array<string, mixed>|class-string<Harness>  $harness
     */
    public function registerHarness(string $name, Harness|array|string $harness): static
    {
        $this->harnesses->register($name, $harness);

        return $this;
    }

    public function tools(): ToolRegistry
    {
        return $this->tools;
    }

    public function harnesses(): HarnessRegistry
    {
        return $this->harnesses;
    }
}
