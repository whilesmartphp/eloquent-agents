<?php

namespace Tests\Fixtures;

use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Contracts\ToolResolver;
use Whilesmart\Agents\Exceptions\ToolNotFoundException;

class ArrayToolResolver implements ToolResolver
{
    /**
     * @param  array<string, Tool>  $tools
     */
    public function __construct(private array $tools = []) {}

    public function add(Tool $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function resolve(string $name): Tool
    {
        return $this->tools[$name] ?? throw ToolNotFoundException::named($name);
    }
}
