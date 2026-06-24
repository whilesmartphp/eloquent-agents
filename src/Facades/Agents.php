<?php

namespace Whilesmart\Agents\Facades;

use Illuminate\Support\Facades\Facade;
use Whilesmart\Agents\AgentManager;

/**
 * @method static \Whilesmart\Agents\Contracts\Harness harness(string $name)
 * @method static \Whilesmart\Agents\ValueObjects\AgentResult run(string $harness, string $input, \Whilesmart\Agents\ValueObjects\ToolContext $context, array $media = [], array $overrides = [])
 * @method static \Whilesmart\Agents\AgentManager registerTool(\Whilesmart\Agents\Contracts\Tool|string $tool)
 * @method static \Whilesmart\Agents\AgentManager extendTool(string $name, \Closure|\Whilesmart\Agents\Contracts\Tool $tool)
 * @method static \Whilesmart\Agents\AgentManager registerHarness(string $name, \Whilesmart\Agents\Contracts\Harness|array|string $harness)
 * @method static \Whilesmart\Agents\Registries\ToolRegistry tools()
 * @method static \Whilesmart\Agents\Registries\HarnessRegistry harnesses()
 *
 * @see AgentManager
 */
class Agents extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'agents';
    }
}
