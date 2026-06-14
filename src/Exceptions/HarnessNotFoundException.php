<?php

namespace Whilesmart\Agents\Exceptions;

use InvalidArgumentException;

class HarnessNotFoundException extends InvalidArgumentException
{
    public static function named(string $name): self
    {
        return new self("No harness registered with name [{$name}].");
    }
}
