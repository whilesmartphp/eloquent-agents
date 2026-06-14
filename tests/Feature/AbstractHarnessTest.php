<?php

namespace Tests\Feature;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Usage;
use Tests\Fixtures\ArrayToolResolver;
use Tests\Fixtures\EchoHarness;
use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Harness\AbstractHarness;
use Whilesmart\Agents\ValueObjects\ToolContext;

class AbstractHarnessTest extends TestCase
{
    public function test_run_returns_agent_result_from_prism_response(): void
    {
        Prism::fake([
            TextResponseFake::make()
                ->withText('Hello there')
                ->withUsage(new Usage(11, 7)),
        ]);

        $harness = new EchoHarness(new ArrayToolResolver(['echo' => new EchoTool]));

        $result = $harness->run('hi', ToolContext::guest());

        $this->assertTrue($result->ok);
        $this->assertSame('Hello there', $result->text);
        $this->assertSame(11, $result->usage['prompt_tokens']);
        $this->assertSame(7, $result->usage['completion_tokens']);
    }

    public function test_max_steps_is_capped_by_config(): void
    {
        config()->set('agents.max_steps', 3);

        $harness = new class(new ArrayToolResolver) extends AbstractHarness
        {
            public function name(): string
            {
                return 't';
            }

            public function systemPrompt(): string
            {
                return 'x';
            }

            public function maxSteps(): ?int
            {
                return 99;
            }

            public function exposedMaxSteps(): int
            {
                return $this->resolveMaxSteps();
            }
        };

        $this->assertSame(3, $harness->exposedMaxSteps());
    }
}
