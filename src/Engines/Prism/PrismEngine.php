<?php

namespace Whilesmart\Agents\Engines\Prism;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\ErrorEvent;
use Prism\Prism\Streaming\Events\StepFinishEvent;
use Prism\Prism\Streaming\Events\StepStartEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Streaming\Events\ToolResultEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Prism\Prism\ValueObjects\ToolResult;
use Whilesmart\Agents\Contracts\StreamingAgentEngine;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\AgentStreamEvent;

/**
 * Runs an agent loop on the Prism SDK. The only class in the package that
 * builds a Prism request and reads a Prism response. Replace this (and the
 * adapter) to move off Prism; the contracts above it stay put.
 */
class PrismEngine implements StreamingAgentEngine
{
    public function __construct(protected PrismToolAdapter $adapter) {}

    public function run(AgentRequest $request): AgentResult
    {
        try {
            $response = $this->buildPendingText($request)->asText();
        } catch (\Throwable $e) {
            return AgentResult::failed($e->getMessage());
        }

        return $this->toAgentResult($response);
    }

    public function stream(AgentRequest $request, callable $onEvent): AgentResult
    {
        $text = '';
        $toolCalls = [];
        $callIndex = [];
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $steps = 0;
        $finishReason = 'stop';
        $failure = null;

        try {
            foreach ($this->buildPendingText($request)->asStream() as $event) {
                if ($event instanceof TextDeltaEvent) {
                    $text .= $event->delta;
                    $onEvent(AgentStreamEvent::textDelta($event->delta));
                } elseif ($event instanceof ThinkingEvent) {
                    $onEvent(AgentStreamEvent::thinking($event->delta));
                } elseif ($event instanceof ToolCallEvent) {
                    $call = $event->toolCall;
                    $callIndex[$call->id] = count($toolCalls);
                    $toolCalls[] = $this->toolCallOutcome($call->name, $call->arguments());
                    $onEvent(AgentStreamEvent::toolCall($call->name, $call->arguments(), $call->id));
                } elseif ($event instanceof ToolResultEvent) {
                    $toolResult = $event->toolResult;
                    $outcome = $this->toolCallOutcome(
                        $toolResult->toolName,
                        $toolResult->args,
                        $toolResult->result,
                        $event->success,
                        $event->error,
                    );

                    if (isset($callIndex[$toolResult->toolCallId])) {
                        $toolCalls[$callIndex[$toolResult->toolCallId]] = $outcome;
                    } else {
                        $toolCalls[] = $outcome;
                    }

                    $onEvent(AgentStreamEvent::toolResult(
                        $outcome['name'],
                        $outcome['result'],
                        (bool) $outcome['success'],
                        $outcome['error'],
                        $outcome['arguments'],
                        $toolResult->toolCallId,
                    ));
                } elseif ($event instanceof StepStartEvent) {
                    $onEvent(AgentStreamEvent::stepStart());
                } elseif ($event instanceof StepFinishEvent) {
                    $steps++;
                    $onEvent(AgentStreamEvent::stepFinish());
                } elseif ($event instanceof ErrorEvent) {
                    $onEvent(AgentStreamEvent::error($event->message, $event->errorType, $event->recoverable));

                    if (! $event->recoverable) {
                        $failure = $event->message;
                        break;
                    }
                } elseif ($event instanceof StreamEndEvent) {
                    if ($event->usage !== null) {
                        $usage['prompt_tokens'] += $event->usage->promptTokens;
                        $usage['completion_tokens'] += $event->usage->completionTokens;
                    }
                    $finishReason = $event->finishReason->value;
                }
            }
        } catch (\Throwable $e) {
            $failure = $e->getMessage();
        }

        if ($failure !== null) {
            // Keep whatever was produced before the stream broke: a partial
            // answer is more useful than discarding the whole turn.
            if (trim($text) === '' && $toolCalls === []) {
                return AgentResult::failed($failure);
            }

            $finishReason = 'error';
        }

        return AgentResult::success(
            text: trim($text),
            steps: $steps,
            toolCalls: $toolCalls,
            usage: $usage,
            finishReason: $finishReason,
        );
    }

    protected function buildPendingText(AgentRequest $request): PendingRequest
    {
        $prismTools = array_map(
            fn (Tool $tool) => $this->adapter->adapt($tool, $request->context),
            $request->tools,
        );

        $text = Prism::text()
            ->using($request->provider, $request->model)
            ->withSystemPrompt($request->systemPrompt)
            ->withMaxSteps($request->maxSteps);

        // Multimodal input (images, documents, audio, video) must travel as a
        // UserMessage with media; withPrompt() is text-only and would drop it.
        $text = $request->media !== []
            ? $text->withMessages([new UserMessage($request->input, $request->media)])
            : $text->withPrompt($request->input);

        if ($request->maxTokens !== null) {
            $text = $text->withMaxTokens($request->maxTokens);
        }

        $requestTimeout = (int) config('agents.request_timeout', 0);
        if ($requestTimeout > 0) {
            $text = $text->withClientOptions(['timeout' => $requestTimeout]);
        }

        if ($request->temperature !== null) {
            $text = $text->usingTemperature($request->temperature);
        }

        if ($prismTools !== []) {
            $text = $text->withTools($prismTools);
        }

        return $text;
    }

    protected function toAgentResult(Response $response): AgentResult
    {
        $toolCalls = [];

        foreach ($response->steps as $step) {
            $results = [];

            foreach ($step->toolResults as $toolResult) {
                /** @var ToolResult $toolResult */
                $results[$toolResult->toolCallId] = $toolResult;
            }

            foreach ($step->toolCalls as $toolCall) {
                /** @var ToolCall $toolCall */
                $toolResult = $results[$toolCall->id] ?? null;

                $toolCalls[] = $toolResult === null
                    ? $this->toolCallOutcome($toolCall->name, $toolCall->arguments())
                    : $this->toolCallOutcome($toolCall->name, $toolCall->arguments(), $toolResult->result, true);
            }
        }

        return AgentResult::success(
            text: $response->text,
            steps: $response->steps->count(),
            toolCalls: $toolCalls,
            usage: [
                'prompt_tokens' => $response->usage->promptTokens,
                'completion_tokens' => $response->usage->completionTokens,
            ],
            finishReason: $response->finishReason->value,
        );
    }

    /**
     * One tool call with what came of it. A call still waiting on its result has
     * a null result and success. A tool that returned an ['error' => ...] payload
     * counts as failed even though it ran, since that is how tools report failure.
     *
     * @param  array<string, mixed>  $arguments
     * @return array{name: string, arguments: array<string, mixed>, result: mixed, success: ?bool, error: ?string}
     */
    protected function toolCallOutcome(
        string $name,
        array $arguments,
        mixed $result = null,
        ?bool $success = null,
        ?string $error = null,
    ): array {
        if (is_string($result)) {
            $decoded = json_decode($result, true);
            $result = is_array($decoded) ? $decoded : $result;
        }

        $reported = is_array($result) && array_keys($result) === ['error'] && is_string($result['error'])
            ? $result['error']
            : null;

        if ($success !== null && $reported !== null) {
            $success = false;
        }

        return [
            'name' => $name,
            'arguments' => $arguments,
            'result' => $result,
            'success' => $success,
            'error' => $error ?? $reported,
        ];
    }
}
