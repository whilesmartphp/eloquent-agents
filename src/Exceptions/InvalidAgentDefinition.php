<?php

namespace Whilesmart\Agents\Exceptions;

use InvalidArgumentException;

class InvalidAgentDefinition extends InvalidArgumentException
{
    public static function unreadable(string $file, string $reason): self
    {
        return new self("Agent definition [{$file}] could not be read: {$reason}");
    }

    public static function invalid(string $name, string $reason): self
    {
        return new self("Invalid agent [{$name}]: {$reason}.");
    }

    public static function duplicate(string $name): self
    {
        return new self("Agent [{$name}] is defined by more than one source.");
    }
}
