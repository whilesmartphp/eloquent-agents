<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\ToolContext;

interface Harness
{
    public function name(): string;

    /**
     * The system prompt for a run. The context carries the user the run is for,
     * so a harness can ground the prompt in who is asking (their defaults,
     * locale, currency) instead of leaving the model to guess. Null when a
     * prompt is needed outside a run, e.g. when listing harnesses.
     */
    public function systemPrompt(?ToolContext $context = null): string;

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
     * @param  array<int, mixed>  $media  Engine-ready media for multimodal input. Empty for text-only.
     * @param  array<string, mixed>  $overrides  Per-run overrides: provider, model, temperature, maxSteps, maxTokens.
     */
    public function run(string $input, ToolContext $context, array $media = [], array $overrides = []): AgentResult;
}
