<?php

namespace Whilesmart\Agents\Contracts;

interface ToolResolver
{
    public function has(string $name): bool;

    public function resolve(string $name): Tool;
}
