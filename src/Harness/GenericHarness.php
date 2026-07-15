<?php

namespace Whilesmart\Agents\Harness;

use Whilesmart\Agents\Contracts\ToolResolver;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\Prompts\PromptRegistry;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * A harness defined entirely by a config array, so apps add agents without
 * writing a class. Keys: prompt, tools, provider, model, temperature,
 * max_steps, permissions.
 */
class GenericHarness extends AbstractHarness
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        ToolResolver $tools,
        protected PromptRegistry $prompts,
        protected string $harnessName,
        protected array $config = [],
    ) {
        parent::__construct($tools);
    }

    public function name(): string
    {
        return $this->harnessName;
    }

    public function systemPrompt(?ToolContext $context = null): string
    {
        return $this->prompts->resolve((string) ($this->config['prompt'] ?? ''));
    }

    public function toolNames(): array
    {
        return $this->config['tools'] ?? [];
    }

    public function allowedPermissions(): array
    {
        return array_map(
            fn ($permission): ToolPermission => $permission instanceof ToolPermission ? $permission : ToolPermission::from($permission),
            $this->config['permissions'] ?? [],
        );
    }

    public function provider(): ?string
    {
        return $this->config['provider'] ?? null;
    }

    public function model(): ?string
    {
        return $this->config['model'] ?? null;
    }

    public function temperature(): ?float
    {
        return isset($this->config['temperature']) ? (float) $this->config['temperature'] : null;
    }

    public function maxSteps(): ?int
    {
        return isset($this->config['max_steps']) ? (int) $this->config['max_steps'] : null;
    }
}
