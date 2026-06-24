<?php

namespace Whilesmart\Agents\Harness;

use Whilesmart\Agents\Contracts\AgentEngine;
use Whilesmart\Agents\Contracts\Harness;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Contracts\ToolResolver;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\ToolContext;

abstract class AbstractHarness implements Harness
{
    protected AgentEngine $engine;

    public function __construct(protected ToolResolver $tools, ?AgentEngine $engine = null)
    {
        $this->engine = $engine ?? app(AgentEngine::class);
    }

    abstract public function name(): string;

    abstract public function systemPrompt(): string;

    /**
     * @return array<int, string>
     */
    public function toolNames(): array
    {
        return [];
    }

    /**
     * @return array<int, ToolPermission>
     */
    public function allowedPermissions(): array
    {
        return [];
    }

    public function provider(): ?string
    {
        return null;
    }

    public function model(): ?string
    {
        return null;
    }

    public function temperature(): ?float
    {
        return null;
    }

    public function maxSteps(): ?int
    {
        return null;
    }

    public function run(string $input, ToolContext $context, array $images = [], array $overrides = []): AgentResult
    {
        return $this->engine->run(new AgentRequest(
            systemPrompt: $this->systemPrompt(),
            input: $input,
            tools: $this->resolveTools(),
            context: $context,
            provider: $overrides['provider'] ?? $this->provider() ?? config('agents.provider'),
            model: $overrides['model'] ?? $this->model() ?? config('agents.model'),
            temperature: $overrides['temperature'] ?? $this->temperature() ?? (float) config('agents.temperature'),
            maxSteps: $this->resolveMaxSteps($overrides['maxSteps'] ?? null),
            images: $images,
            maxTokens: $overrides['maxTokens'] ?? config('agents.max_tokens'),
        ));
    }

    /**
     * The harness's named tools, filtered to its allowed permissions. Engine
     * adaptation happens downstream, so this stays SDK-agnostic.
     *
     * @return array<int, Tool>
     */
    protected function resolveTools(): array
    {
        $allowed = array_map(fn (ToolPermission $p): string => $p->value, $this->allowedPermissions());
        $tools = [];

        foreach ($this->toolNames() as $name) {
            if (! $this->tools->has($name)) {
                continue;
            }

            $tool = $this->tools->resolve($name);

            if ($allowed !== [] && ! in_array($tool->permission()->value, $allowed, true)) {
                continue;
            }

            $tools[] = $tool;
        }

        return $tools;
    }

    protected function resolveMaxSteps(?int $requested = null): int
    {
        $cap = (int) config('agents.max_steps', 8);
        $requested = $requested ?? $this->maxSteps() ?? $cap;

        return max(1, min($requested, $cap));
    }
}
