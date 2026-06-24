<?php

namespace Whilesmart\Agents\Engines\Prism;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ToolCall;
use Whilesmart\Agents\Contracts\AgentEngine;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;

/**
 * Runs an agent loop on the Prism SDK. The only class in the package that
 * builds a Prism request and reads a Prism response. Replace this (and the
 * adapter) to move off Prism; the contracts above it stay put.
 */
class PrismEngine implements AgentEngine
{
    public function __construct(protected PrismToolAdapter $adapter) {}

    public function run(AgentRequest $request): AgentResult
    {
        $prismTools = array_map(
            fn (Tool $tool) => $this->adapter->adapt($tool, $request->context),
            $request->tools,
        );

        $text = Prism::text()
            ->using($request->provider, $request->model)
            ->withSystemPrompt($request->systemPrompt)
            ->withMaxSteps($request->maxSteps);

        // Multimodal input (e.g. brand logos, user attachments) must travel as a
        // UserMessage with media; withPrompt() is text-only and would drop images.
        $text = $request->images !== []
            ? $text->withMessages([new UserMessage($request->input, $request->images)])
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

        try {
            $response = $text->asText();
        } catch (\Throwable $e) {
            return AgentResult::failed($e->getMessage());
        }

        return $this->toAgentResult($response);
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
