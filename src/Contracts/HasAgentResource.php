<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\Resources\AgentResource;

/**
 * A model that describes itself to agents. Implement this and list the model in
 * config('agents.resources') to give it a read tool and a place in an exported
 * semantic-layer schema.
 */
interface HasAgentResource
{
    public static function agentResource(): AgentResource;
}
