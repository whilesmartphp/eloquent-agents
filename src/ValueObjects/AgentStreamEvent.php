<?php

namespace Whilesmart\Agents\ValueObjects;

use Whilesmart\Agents\Enums\StreamEventType;

/**
 * One progress event from a streaming run, in the package's own vocabulary. The
 * engine translates its SDK's stream events into these, so a caller can react
 * to tool calls, tool results, text deltas, step boundaries and errors without
 * touching the SDK.
 */
final readonly class AgentStreamEvent
{
    /**
     * @param  array<string, mixed>  $arguments  Tool arguments for a ToolCall or ToolResult event.
     * @param  mixed  $result  What the tool returned, for a ToolResult event.
     * @param  ?bool  $success  Whether the tool succeeded, for a ToolResult event.
     * @param  ?string  $error  The tool's error for a failed ToolResult, or the message of an Error event.
     */
    public function __construct(
        public StreamEventType $type,
        public ?string $text = null,
        public ?string $toolName = null,
        public array $arguments = [],
        public ?string $toolCallId = null,
        public mixed $result = null,
        public ?bool $success = null,
        public ?string $error = null,
        public ?string $errorType = null,
        public ?bool $recoverable = null,
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
    public static function toolCall(string $toolName, array $arguments = [], ?string $toolCallId = null): self
    {
        return new self(StreamEventType::ToolCall, toolName: $toolName, arguments: $arguments, toolCallId: $toolCallId);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public static function toolResult(
        string $toolName,
        mixed $result = null,
        bool $success = true,
        ?string $error = null,
        array $arguments = [],
        ?string $toolCallId = null,
    ): self {
        return new self(
            StreamEventType::ToolResult,
            toolName: $toolName,
            arguments: $arguments,
            toolCallId: $toolCallId,
            result: $result,
            success: $success,
            error: $error,
        );
    }

    public static function stepStart(): self
    {
        return new self(StreamEventType::StepStart);
    }

    public static function stepFinish(): self
    {
        return new self(StreamEventType::StepFinish);
    }

    public static function error(string $message, ?string $errorType = null, bool $recoverable = false): self
    {
        return new self(StreamEventType::Error, error: $message, errorType: $errorType, recoverable: $recoverable);
    }
}
