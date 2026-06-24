<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\ToolContext;

interface Harness
{
    public function name(): string;

    public function systemPrompt(): string;

    /**
     * Names of tools this harness exposes to the model.
     *
     * @return array<int, string>
     */
    public function toolNames(): array;

    /**
     * Permissions a tool must fall within to be admitted. Empty = all allowed.
     *
     * @return array<int, ToolPermission>
     */
    public function allowedPermissions(): array;

    public function provider(): ?string;

    public function model(): ?string;

    public function temperature(): ?float;

    public function maxSteps(): ?int;

    /**
     * @param  array<int, mixed>  $images  Engine-ready media for multimodal input. Empty for text-only.
     * @param  array<string, mixed>  $overrides  Per-run overrides: provider, model, temperature, maxSteps, maxTokens.
     */
    public function run(string $input, ToolContext $context, array $images = [], array $overrides = []): AgentResult;
}
