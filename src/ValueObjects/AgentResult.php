<?php

namespace Whilesmart\Agents\ValueObjects;

/**
 * The outcome of a harness run. Provider-agnostic: the harness maps Prism's
 * response into this shape.
 */
final readonly class AgentResult
{
    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array{prompt_tokens: int, completion_tokens: int}  $usage
     */
    public function __construct(
        public bool $ok,
        public string $text,
        public int $steps = 0,
        public array $toolCalls = [],
        public array $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0],
        public string $finishReason = 'stop',
        public ?string $error = null,
    ) {}

    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array{prompt_tokens: int, completion_tokens: int}  $usage
     */
    public static function success(
        string $text,
        int $steps = 0,
        array $toolCalls = [],
        array $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0],
        string $finishReason = 'stop',
    ): self {
        return new self(true, $text, $steps, $toolCalls, $usage, $finishReason);
    }

    public static function failed(string $error): self
    {
        return new self(false, '', 0, [], ['prompt_tokens' => 0, 'completion_tokens' => 0], 'error', $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ok' => $this->ok,
            'text' => $this->text,
            'steps' => $this->steps,
            'tool_calls' => $this->toolCalls,
            'usage' => $this->usage,
            'finish_reason' => $this->finishReason,
            'error' => $this->error,
        ];
    }
}
