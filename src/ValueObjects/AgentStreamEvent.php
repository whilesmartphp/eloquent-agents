<?php

namespace Whilesmart\Agents\ValueObjects;

use Whilesmart\Agents\Enums\StreamEventType;

/**
 * One progress event from a streaming run, in the package's own vocabulary. The
 * engine translates its SDK's stream events into these, so a caller can react
 * to tool calls, text deltas and step boundaries without touching the SDK.
 */
final readonly class AgentStreamEvent
{
    /**
     * @param  array<string, mixed>  $arguments  Tool arguments for a ToolCall event.
     */
    public function __construct(
        public StreamEventType $type,
        public ?string $text = null,
        public ?string $toolName = null,
        public array $arguments = [],
    ) {}

    public static function textDelta(string $text): self
    {
        return new self(StreamEventType::TextDelta, text: $text);
    }

    public static function thinking(string $text): self
    {
        return new self(StreamEventType::Thinking, text: $text);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function toolCall(string $toolName, array $arguments = []): self
    {
        return new self(StreamEventType::ToolCall, toolName: $toolName, arguments: $arguments);
    }

    public static function toolResult(string $toolName): self
    {
        return new self(StreamEventType::ToolResult, toolName: $toolName);
    }

    public static function stepFinish(): self
    {
        return new self(StreamEventType::StepFinish);
    }
}
