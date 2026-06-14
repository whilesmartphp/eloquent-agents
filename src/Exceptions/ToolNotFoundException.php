<?php

namespace Whilesmart\Agents\Exceptions;

use InvalidArgumentException;

class ToolNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("No tool registered with name [{$name}].");
    }
}
