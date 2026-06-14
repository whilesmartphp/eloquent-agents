<?php

namespace Tests\Feature;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Tests\TestCase;
use Whilesmart\Agents\Facades\Agents;

class CommandsTest extends TestCase
{
    public function test_agents_tools_lists_defaults(): void
    {
        $this->artisan('agents:tools')
            ->expectsOutputToContain('clock')
            ->expectsOutputToContain('calculator')
            ->expectsOutputToContain('eloquent.query')
            ->assertExitCode(0);
    }

    public function test_agents_harnesses_lists_registered(): void
    {
        Agents::registerHarness('chat', ['prompt' => 'hi']);

        $this->artisan('agents:harnesses')
            ->expectsOutputToContain('chat')
            ->assertExitCode(0);
    }

    public function test_agents_run_executes_a_harness(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('Done.'),
        ]);
        Agents::registerHarness('chat', ['prompt' => 'You are helpful.']);

        $this->artisan('agents:run', ['harness' => 'chat', 'input' => 'hello'])
            ->expectsOutputToContain('Done.')
            ->assertExitCode(0);
    }

    public function test_agents_run_reports_unknown_harness(): void
    {
        $this->artisan('agents:run', ['harness' => 'nope', 'input' => 'hi'])
            ->assertExitCode(1);
    }
}
