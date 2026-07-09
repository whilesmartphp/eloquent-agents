<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\AgentStreamEvent;

/**
 * An engine that can run the agent loop while emitting progress as it goes.
 * Optional: engines that only support buffered runs implement AgentEngine
 * alone, and callers fall back to run() when this is not available.
 */
interface StreamingAgentEngine extends AgentEngine
{
    /**
     * Run the loop, invoking $onEvent for each progress event, and return the
     * same final result run() would. The callback receives package-native
     * AgentStreamEvent values, never the underlying SDK's types.
     *
     * @param  callable(AgentStreamEvent):void  $onEvent
     */
    public function stream(AgentRequest $request, callable $onEvent): AgentResult;
}
