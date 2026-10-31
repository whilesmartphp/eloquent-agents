<?php

namespace Tests\Feature;

use Generator;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\PrismManager;
use Prism\Prism\Providers\Provider;
use Prism\Prism\Streaming\EventID;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StepFinishEvent;
use Prism\Prism\Streaming\Events\StepStartEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\StreamEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\Testing\TextStepFake;
use Prism\Prism\Text\Request as TextRequest;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Prism\Prism\ValueObjects\Usage;
use Tests\Fixtures\ArrayToolResolver;
use Tests\Fixtures\EchoHarness;
use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Enums\StreamEventType;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\AgentStreamEvent;
use Whilesmart\Agents\ValueObjects\ToolContext;

class PrismEngineStreamTest extends TestCase
{
    private function harness(): EchoHarness
    {
        return new EchoHarness(new ArrayToolResolver(['echo' => new EchoTool]));
    }

    private function echoTurn(string $result): TextResponseFake
    {
        $call = new ToolCall('call-1', 'echo', ['message' => 'hi']);

        return TextResponseFake::make()
            ->withText('Done')
            ->withUsage(new Usage(15, 6))
            ->withSteps(collect([
                TextStepFake::make()
                    ->withToolCalls([$call])
                    ->withToolResults([new ToolResult('call-1', 'echo', ['message' => 'hi'], $result)])
                    ->withUsage(new Usage(10, 4)),
                TextStepFake::make()->withText('Done')->withUsage(new Usage(5, 2)),
            ]));
    }

    /**
     * @param  array<int, StreamEvent>  $events
     */
    private function scripted(array $events): void
    {
        $provider = new class($events) extends Provider
        {
            /**
             * @param  array<int, StreamEvent>  $events
             */
            public function __construct(private array $events) {}

            public function stream(TextRequest $request): Generator
            {
                yield from $this->events;
            }
        };

        app(PrismManager::class)->extend('scripted', fn () => $provider);
    }

    /**
     * @return array{0: AgentResult, 1: array<int, AgentStreamEvent>}
     */
    private function stream(array $overrides = []): array
    {
        $events = [];
        $result = $this->harness()->stream('hi', ToolContext::guest(), function (AgentStreamEvent $event) use (&$events) {
            $events[] = $event;
        }, overrides: $overrides);

        return [$result, $events];
    }

    public function test_tool_results_are_streamed_with_their_outcome(): void
    {
        $adapted = (new EchoTool)->handle(['message' => 'hi'], ToolContext::guest());
        Prism::fake([$this->echoTurn($adapted)]);

        [$result, $events] = $this->stream();

        $types = array_map(fn (AgentStreamEvent $e) => $e->type, $events);
        $this->assertSame(StreamEventType::StepStart, $types[0]);
        $this->assertContains(StreamEventType::ToolCall, $types);
        $this->assertContains(StreamEventType::StepFinish, $types);

        $toolResult = collect($events)->firstWhere('type', StreamEventType::ToolResult);
        $this->assertSame('echo', $toolResult->toolName);
        $this->assertSame('echo: hi', $toolResult->result);
        $this->assertTrue($toolResult->success);
        $this->assertNull($toolResult->error);
        $this->assertSame('call-1', $toolResult->toolCallId);
        $this->assertSame(['message' => 'hi'], $toolResult->arguments);

        $this->assertSame([[
            'name' => 'echo',
            'arguments' => ['message' => 'hi'],
            'result' => 'echo: hi',
            'success' => true,
            'error' => null,
        ]], $result->toolCalls);
        $this->assertSame('Done', $result->text);
    }

    public function test_an_error_payload_marks_the_outcome_failed(): void
    {
        $returned = json_encode(['error' => 'Error executing tool describe_plan: \'plan-does-not-exist\'']);
        Prism::fake([$this->echoTurn($returned)]);

        [$result, $events] = $this->stream();

        $toolResult = collect($events)->firstWhere('type', StreamEventType::ToolResult);
        $this->assertFalse($toolResult->success);
        $this->assertSame("Error executing tool describe_plan: 'plan-does-not-exist'", $toolResult->error);
        $this->assertFalse($result->toolCalls[0]['success']);
        $this->assertSame(['error' => "Error executing tool describe_plan: 'plan-does-not-exist'"], $result->toolCalls[0]['result']);
    }

    public function test_a_failed_tool_result_from_prism_is_carried(): void
    {
        $call = new ToolCall('call-9', 'echo', ['message' => 'x']);
        $this->scripted([
            new StepStartEvent(EventID::generate(), time()),
            new ToolCallEvent(EventID::generate(), time(), $call, 'msg-1'),
            new ToolResultEvent(
                EventID::generate(),
                time(),
                new ToolResult('call-9', 'echo', ['message' => 'x'], 'boom'),
                'msg-1',
                success: false,
                error: 'boom',
            ),
            new StepFinishEvent(EventID::generate(), time()),
            new StreamEndEvent(EventID::generate(), time(), FinishReason::Stop, new Usage(1, 1)),
        ]);

        [$result, $events] = $this->stream(['provider' => 'scripted']);

        $toolResult = collect($events)->firstWhere('type', StreamEventType::ToolResult);
        $this->assertFalse($toolResult->success);
        $this->assertSame('boom', $toolResult->error);
        $this->assertCount(1, $result->toolCalls);
        $this->assertSame(['name' => 'echo', 'arguments' => ['message' => 'x'], 'result' => 'boom', 'success' => false, 'error' => 'boom'], $result->toolCalls[0]);
    }

    public function test_usage_is_summed_across_every_stream_end(): void
    {
        $this->scripted([
            new StepStartEvent(EventID::generate(), time()),
            new TextDeltaEvent(EventID::generate(), time(), 'Part one. ', 'msg-1'),
            new StepFinishEvent(EventID::generate(), time()),
            new StreamEndEvent(EventID::generate(), time(), FinishReason::ToolCalls, new Usage(3, 2)),
            new StepStartEvent(EventID::generate(), time()),
            new TextDeltaEvent(EventID::generate(), time(), 'Part two.', 'msg-2'),
            new StepFinishEvent(EventID::generate(), time()),
            new StreamEndEvent(EventID::generate(), time(), FinishReason::Stop, new Usage(5, 1)),
        ]);

        [$result] = $this->stream(['provider' => 'scripted']);

        $this->assertSame(['prompt_tokens' => 8, 'completion_tokens' => 3], $result->usage);
        $this->assertSame(2, $result->steps);
        $this->assertSame('stop', $result->finishReason);
    }

    public function test_a_single_stream_end_keeps_its_usage(): void
    {
        Prism::fake([$this->echoTurn('echo: hi')]);

        [$result] = $this->stream();

        $this->assertSame(['prompt_tokens' => 15, 'completion_tokens' => 6], $result->usage);
    }

    public function test_a_recoverable_error_is_forwarded_and_the_run_goes_on(): void
    {
        $this->scripted([
            new StepStartEvent(EventID::generate(), time()),
            new ErrorEvent(EventID::generate(), time(), 'overloaded_error', 'Overloaded', recoverable: true),
            new TextDeltaEvent(EventID::generate(), time(), 'Still here', 'msg-1'),
            new StepFinishEvent(EventID::generate(), time()),
            new StreamEndEvent(EventID::generate(), time(), FinishReason::Stop, new Usage(2, 2)),
        ]);

        [$result, $events] = $this->stream(['provider' => 'scripted']);

        $error = collect($events)->firstWhere('type', StreamEventType::Error);
        $this->assertSame('Overloaded', $error->error);
        $this->assertSame('overloaded_error', $error->errorType);
        $this->assertTrue($error->recoverable);
        $this->assertTrue($result->ok);
        $this->assertSame('Still here', $result->text);
        $this->assertSame('stop', $result->finishReason);
    }

    public function test_an_unrecoverable_error_before_any_output_fails_the_run(): void
    {
        $this->scripted([
            new StepStartEvent(EventID::generate(), time()),
            new ErrorEvent(EventID::generate(), time(), 'invalid_request_error', 'Bad request', recoverable: false),
            new TextDeltaEvent(EventID::generate(), time(), 'never seen', 'msg-1'),
        ]);

        [$result, $events] = $this->stream(['provider' => 'scripted']);

        $this->assertFalse($result->ok);
        $this->assertSame('Bad request', $result->error);
        $this->assertFalse(collect($events)->contains('type', StreamEventType::TextDelta));
    }

    public function test_an_unrecoverable_error_after_output_keeps_the_partial_answer(): void
    {
        $this->scripted([
            new StepStartEvent(EventID::generate(), time()),
            new TextDeltaEvent(EventID::generate(), time(), 'Half an answer', 'msg-1'),
            new ErrorEvent(EventID::generate(), time(), 'api_error', 'Connection lost', recoverable: false),
        ]);

        [$result] = $this->stream(['provider' => 'scripted']);

        $this->assertTrue($result->ok);
        $this->assertSame('Half an answer', $result->text);
        $this->assertSame('error', $result->finishReason);
    }

    public function test_a_buffered_run_records_the_same_outcomes(): void
    {
        $returned = json_encode(['error' => 'Unknown tool: no_such_tool']);
        Prism::fake([$this->echoTurn('echo: hi'), $this->echoTurn($returned)]);

        $ok = $this->harness()->run('hi', ToolContext::guest());
        $failed = $this->harness()->run('hi', ToolContext::guest());

        $this->assertSame([[
            'name' => 'echo',
            'arguments' => ['message' => 'hi'],
            'result' => 'echo: hi',
            'success' => true,
            'error' => null,
        ]], $ok->toolCalls);
        $this->assertFalse($failed->toolCalls[0]['success']);
        $this->assertSame('Unknown tool: no_such_tool', $failed->toolCalls[0]['error']);
        $this->assertSame('echo', $ok->toArray()['tool_calls'][0]['name']);
    }
}
