<?php

namespace Whilesmart\Agents;

use Closure;
use Whilesmart\Agents\Contracts\Harness;
use Whilesmart\Agents\Contracts\StreamingHarness;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\Mcp\McpServer;
use Whilesmart\Agents\Registries\HarnessRegistry;
use Whilesmart\Agents\Registries\ToolRegistry;
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
    ) {}

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
     * Offer the tools of a remote MCP server (streamable HTTP) to harnesses.
     * Tools are discovered on first use and cached for $cacheTtl seconds.
     *
     * @param  array<string, string>|Closure(ToolContext):array<string, string>  $headers
     * @param  array<string, Closure(ToolContext):mixed|string>  $bind  Arguments filled from the run's context and hidden from the model: a resolver, or a key of the context's scope.
     * @param  array<int, string>  $only  Tool names to keep. Empty keeps every tool.
     */
    public function registerMcpServer(
        McpServer|string $name,
        ?string $url = null,
        array|Closure $headers = [],
        array $bind = [],
        array $only = [],
        ?int $cacheTtl = null,
        ToolPermission $permission = ToolPermission::EXTERNAL,
        ?int $timeout = null,
    ): static {
        $server = $name instanceof McpServer
            ? $name
            : new McpServer($name, (string) $url, $headers, $bind, $only, $cacheTtl, $permission, $timeout);

        $this->tools->source(fn (): array => $server->tools());

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
