<?php

namespace Whilesmart\Agents\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;

final readonly class AgentRunCompleted
{
    use Dispatchable;

    public function __construct(
        public string $harness,
        public AgentRequest $request,
        public AgentResult $result,
    ) {}
}
