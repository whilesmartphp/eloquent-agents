<?php

namespace Whilesmart\Agents\Exceptions;

use InvalidArgumentException;

class AgentNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("No agent registered with name [{$name}].");
    }
}
