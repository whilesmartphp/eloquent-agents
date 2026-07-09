<?php

namespace Whilesmart\Agents\Engines\Prism;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Streaming\Events\StepFinishEvent;
use Prism\Prism\Streaming\Events\StreamEndEvent;
use Prism\Prism\Streaming\Events\TextDeltaEvent;
use Prism\Prism\Streaming\Events\ThinkingEvent;
use Prism\Prism\Streaming\Events\ToolCallEvent;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
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
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $steps = 0;
        $finishReason = 'stop';

        try {
            foreach ($this->buildPendingText($request)->asStream() as $event) {
                if ($event instanceof TextDeltaEvent) {
                    $text .= $event->delta;
                    $onEvent(AgentStreamEvent::textDelta($event->delta));
                } elseif ($event instanceof ThinkingEvent) {
                    $onEvent(AgentStreamEvent::thinking($event->delta));
                } elseif ($event instanceof ToolCallEvent) {
                    $toolCalls[] = ['name' => $event->toolCall->name, 'arguments' => $event->toolCall->arguments()];
                    $onEvent(AgentStreamEvent::toolCall($event->toolCall->name, $event->toolCall->arguments()));
                } elseif ($event instanceof StepFinishEvent) {
                    $steps++;
                    $onEvent(AgentStreamEvent::stepFinish());
                } elseif ($event instanceof StreamEndEvent) {
                    if ($event->usage !== null) {
                        $usage = [
                            'prompt_tokens' => $event->usage->promptTokens,
                            'completion_tokens' => $event->usage->completionTokens,
                        ];
                    }
                    $finishReason = $event->finishReason->value;
                }
            }
        } catch (\Throwable $e) {
            // Keep whatever was produced before the stream broke: a partial
            // answer is more useful than discarding the whole turn.
            if (trim($text) === '' && $toolCalls === []) {
                return AgentResult::failed($e->getMessage());
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
            foreach ($step->toolCalls as $toolCall) {
                /** @var ToolCall $toolCall */
                $toolCalls[] = [
                    'name' => $toolCall->name,
                    'arguments' => $toolCall->arguments(),
                ];
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
}
