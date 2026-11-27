<?php

namespace Tests\Feature;

use Closure;
use Illuminate\Support\Facades\Event;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Text\Request;
use Prism\Prism\ValueObjects\Usage;
use RuntimeException;
use Tests\Fixtures\ArrayToolResolver;
use Tests\Fixtures\EchoHarness;
use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Contracts\RunGuard;
use Whilesmart\Agents\Events\AgentRunCompleted;
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\AgentStreamEvent;
use Whilesmart\Agents\ValueObjects\ToolContext;

class CompletionAndRunGuardTest extends TestCase
{
    public function test_a_completion_is_one_tool_free_model_call_with_its_full_usage(): void
    {
        Event::fake([AgentRunCompleted::class]);
        $fake = Prism::fake([
            TextResponseFake::make()
                ->withText('agent')
                ->withUsage(new Usage(20, 1, 4, 6, 3)),
        ]);

        $result = Agents::complete('Plan my budget', ToolContext::guest(), [
            'name' => 'router.classify',
            'system' => 'Answer with one word.',
            'provider' => 'anthropic',
            'model' => 'claude-haiku-5-5',
            'temperature' => 0.0,
            'maxTokens' => 16,
        ]);

        $this->assertTrue($result->ok);
        $this->assertSame('agent', $result->text);
        $this->assertSame([
            'prompt_tokens' => 20,
            'completion_tokens' => 1,
            'cache_write_input_tokens' => 4,
            'cache_read_input_tokens' => 6,
            'thought_tokens' => 3,
        ], $result->usage);

        $fake->assertCallCount(1);
        $fake->assertRequest(function (array $requests): void {
            /** @var Request $request */
            $request = $requests[0];
            $this->assertSame('anthropic', $request->provider());
            $this->assertSame('claude-haiku-5-5', $request->model());
            $this->assertSame('Plan my budget', $request->prompt());
            $this->assertSame('Answer with one word.', $request->systemPrompts()[0]->content);
            $this->assertSame([], $request->tools());
            $this->assertSame(1, $request->maxSteps());
            $this->assertSame(16, $request->maxTokens());
        });

        Event::assertDispatched(
            AgentRunCompleted::class,
            fn (AgentRunCompleted $event) => $event->harness === 'router.classify'
                && $event->request->provider === 'anthropic'
                && $event->result === $result,
        );
    }

    public function test_a_completion_without_a_system_prompt_sends_none(): void
    {
        $fake = Prism::fake([TextResponseFake::make()->withText('ok')->withUsage(new Usage(1, 1))]);

        Agents::complete('Title this chat', ToolContext::guest());

        $fake->assertRequest(fn (array $requests) => $this->assertSame([], $requests[0]->systemPrompts()));
    }

    public function test_a_refusing_guard_stops_every_kind_of_run_before_the_model_is_called(): void
    {
        Event::fake([AgentRunCompleted::class]);
        $fake = Prism::fake([TextResponseFake::make()->withText('never')]);
        $this->app->instance(RunGuard::class, new class implements RunGuard
        {
            public function handle(string $name, AgentRequest $request, Closure $next): AgentResult
            {
                throw new RuntimeException("Allowance used up for {$name}.");
            }
        });
        $this->registerEchoHarness();

        $runs = [
            fn () => Agents::complete('hi', ToolContext::guest()),
            fn () => Agents::run('echo', 'hi', ToolContext::guest()),
            fn () => Agents::stream('echo', 'hi', ToolContext::guest(), fn () => null),
        ];

        foreach ($runs as $run) {
            try {
                $run();
                $this->fail('The guard should have refused the run.');
            } catch (RuntimeException $e) {
                $this->assertStringStartsWith('Allowance used up for', $e->getMessage());
            }
        }

        $fake->assertCallCount(0);
        Event::assertNotDispatched(AgentRunCompleted::class);
    }

    public function test_a_guard_can_tighten_the_request_and_the_event_reports_what_ran(): void
    {
        Event::fake([AgentRunCompleted::class]);
        $fake = Prism::fake([TextResponseFake::make()->withText('ok')->withUsage(new Usage(1, 1))]);
        $this->app->instance(RunGuard::class, new class implements RunGuard
        {
            public function handle(string $name, AgentRequest $request, Closure $next): AgentResult
            {
                return $next($request->with(maxTokens: 50));
            }
        });
        $this->registerEchoHarness();

        Agents::run('echo', 'hi', ToolContext::guest(), [], ['maxTokens' => 4000]);

        $fake->assertRequest(fn (array $requests) => $this->assertSame(50, $requests[0]->maxTokens()));
        Event::assertDispatched(AgentRunCompleted::class, fn (AgentRunCompleted $event) => $event->request->maxTokens === 50);
    }

    public function test_a_guard_sees_the_usage_of_buffered_streamed_and_single_runs(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('one')->withUsage(new Usage(10, 2)),
            TextResponseFake::make()->withText('two')->withUsage(new Usage(30, 4, thoughtTokens: 5)),
            TextResponseFake::make()->withText('three')->withUsage(new Usage(7, 1)),
        ]);
        $guard = new class implements RunGuard
        {
            public array $recorded = [];

            public function handle(string $name, AgentRequest $request, Closure $next): AgentResult
            {
                $result = $next($request);
                $this->recorded[] = [$name, $request->provider, $result->usage['prompt_tokens'], $result->usage['thought_tokens']];

                return $result;
            }
        };
        $this->app->instance(RunGuard::class, $guard);
        $this->registerEchoHarness();
        $events = [];

        Agents::run('echo', 'hi', ToolContext::guest());
        Agents::stream('echo', 'hi', ToolContext::guest(), function (AgentStreamEvent $event) use (&$events) {
            $events[] = $event;
        });
        Agents::complete('hi', ToolContext::guest(), ['name' => 'title']);

        $provider = config('agents.provider');
        $this->assertSame([
            ['echo', $provider, 10, 0],
            ['echo', $provider, 30, 5],
            ['title', $provider, 7, 0],
        ], $guard->recorded);
        $this->assertNotEmpty($events);
    }

    private function registerEchoHarness(): void
    {
        Agents::registerHarness('echo', new EchoHarness(new ArrayToolResolver(['echo' => new EchoTool])));
    }
}
