<?php

namespace Whilesmart\Agents\ValueObjects;

use Whilesmart\Agents\Contracts\Tool;

/**
 * A fully-resolved agent run, expressed in the package's own types. The harness
 * builds it and hands it to an AgentEngine. No SDK types appear here.
 */
final readonly class AgentRequest
{
    /**
     * @param  array<int, Tool>  $tools
     */
    public function __construct(
        public string $systemPrompt,
        public string $input,
        public array $tools,
        public ToolContext $context,
        public ?string $provider,
        public ?string $model,
        public ?float $temperature,
        public int $maxSteps,
    ) {}
}
