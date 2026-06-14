<?php

namespace Tests\Feature;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Contracts\Harness;
use Whilesmart\Agents\Exceptions\HarnessNotFoundException;
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\Harness\GenericHarness;
use Whilesmart\Agents\ValueObjects\ToolContext;

class RegistryTest extends TestCase
{
    public function test_register_tool_at_runtime(): void
    {
        Agents::registerTool(new EchoTool);

        $this->assertTrue(Agents::tools()->has('echo'));
        $this->assertSame('echo', Agents::tools()->resolve('echo')->name());
    }

    public function test_extend_registers_lazily(): void
    {
        $built = 0;

        Agents::extendTool('lazy', function () use (&$built) {
            $built++;

            return new EchoTool;
        });

        $this->assertSame(0, $built, 'closure should not run until resolved');

        Agents::tools()->resolve('lazy');
        Agents::tools()->resolve('lazy');

        $this->assertSame(1, $built, 'closure should run once and cache');
    }

    public function test_register_array_harness_builds_generic_harness(): void
    {
        Agents::registerHarness('chat', [
            'prompt' => 'You are helpful.',
            'tools' => [],
        ]);

        $harness = Agents::harness('chat');

        $this->assertInstanceOf(GenericHarness::class, $harness);
        $this->assertInstanceOf(Harness::class, $harness);
        $this->assertSame('You are helpful.', $harness->systemPrompt());
    }

    public function test_array_harness_runs(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('hi from agent'),
        ]);

        Agents::registerHarness('chat', ['prompt' => 'You are helpful.']);

        $result = Agents::harness('chat')->run('hello', ToolContext::guest());

        $this->assertTrue($result->ok);
        $this->assertSame('hi from agent', $result->text);
    }

    public function test_unknown_harness_throws(): void
    {
        $this->expectException(HarnessNotFoundException::class);

        Agents::harness('does-not-exist');
    }
}
