<?php

namespace Whilesmart\Agents\Sources;

use Whilesmart\Agents\Contracts\AgentSource;

class ConfigAgentSource implements AgentSource
{
    public function definitions(): array
    {
        return (array) config('agents.agents', []);
    }
}
