<?php

namespace Whilesmart\Agents;

use Closure;
use Whilesmart\Agents\Contracts\Harness;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Registries\HarnessRegistry;
use Whilesmart\Agents\Registries\ToolRegistry;
use Whilesmart\Agents\ValueObjects\AgentResult;
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
