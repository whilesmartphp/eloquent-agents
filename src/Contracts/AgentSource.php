<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\ValueObjects\AgentDefinition;

interface AgentSource
{
    /**
     * @return array<string, array|AgentDefinition> Definitions keyed by agent name.
     */
    public function definitions(): array;
}
