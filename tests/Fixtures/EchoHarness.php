<?php

namespace Tests\Fixtures;

use Whilesmart\Agents\Harness\AbstractHarness;

class EchoHarness extends AbstractHarness
{
    public function name(): string
    {
        return 'echo';
    }

    public function systemPrompt(): string
    {
        return 'You echo messages.';
    }

    public function toolNames(): array
    {
        return ['echo'];
    }
}
