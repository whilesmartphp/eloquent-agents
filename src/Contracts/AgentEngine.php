<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;

/**
 * The LLM backend that actually runs an agent loop. The only seam that knows
 * about a concrete SDK. Swapping providers (Prism today, something else later)
 * means writing one implementation of this; nothing else in the package changes.
 */
interface AgentEngine
{
    public function run(AgentRequest $request): AgentResult;
}
