<?php

namespace Whilesmart\Agents\Contracts;

use Closure;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;

/**
 * Wraps every model run: harness runs, streamed runs and single completions.
 * A host binds its own guard to check an allowance before the run, hold a
 * lock, tighten the request, or record the usage the result reports.
 * Throwing refuses the run; the exception reaches the caller unchanged.
 */
interface RunGuard
{
    /**
     * @param  Closure(AgentRequest):AgentResult  $next
     */
    public function handle(string $name, AgentRequest $request, Closure $next): AgentResult;
}
