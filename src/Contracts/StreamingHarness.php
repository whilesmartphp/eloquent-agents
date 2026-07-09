<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\AgentStreamEvent;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * A harness that can stream its run. Backed by a StreamingAgentEngine; when the
 * engine cannot stream, implementations fall back to a buffered run and emit no
 * events, so callers can always use stream() safely.
 */
interface StreamingHarness extends Harness
{
    /**
     * @param  callable(AgentStreamEvent):void  $onEvent
     * @param  array<int, mixed>  $media  Engine-ready media for multimodal input. Empty for text-only.
     * @param  array<string, mixed>  $overrides  Per-run overrides: provider, model, temperature, maxSteps, maxTokens.
     */
    public function stream(string $input, ToolContext $context, callable $onEvent, array $media = [], array $overrides = []): AgentResult;
}
