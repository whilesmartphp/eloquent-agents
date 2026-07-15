<?php

namespace Tests\Fixtures;

use Whilesmart\Agents\Harness\AbstractHarness;
use Whilesmart\Agents\ValueObjects\ToolContext;

class EchoHarness extends AbstractHarness
{
    public function name(): string
    {
        return 'echo';
    }

    public function systemPrompt(?ToolContext $context = null): string
    {
        return 'You echo messages.';
    }

    public function toolNames(): array
    {
        return ['echo'];
    }
}
