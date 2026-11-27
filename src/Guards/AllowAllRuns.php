<?php

namespace Whilesmart\Agents\Guards;

use Closure;
use Whilesmart\Agents\Contracts\RunGuard;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;

class AllowAllRuns implements RunGuard
{
    public function handle(string $name, AgentRequest $request, Closure $next): AgentResult
    {
        return $next($request);
    }
}
