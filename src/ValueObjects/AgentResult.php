<?php

namespace Whilesmart\Agents\ValueObjects;

/**
 * The outcome of a harness run. Provider-agnostic: the harness maps Prism's
 * response into this shape.
 */
final readonly class AgentResult
{
    public const NO_USAGE = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'cache_write_input_tokens' => 0,
        'cache_read_input_tokens' => 0,
        'thought_tokens' => 0,
    ];

    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array{prompt_tokens: int, completion_tokens: int, cache_write_input_tokens: int, cache_read_input_tokens: int, thought_tokens: int}  $usage
     */
    public function __construct(
        public bool $ok,
        public string $text,
        public int $steps = 0,
        public array $toolCalls = [],
        public array $usage = self::NO_USAGE,
        public string $finishReason = 'stop',
        public ?string $error = null,
    ) {}

    /**
     * @param  array<int, array{name: string, arguments: array<string, mixed>}>  $toolCalls
     * @param  array{prompt_tokens: int, completion_tokens: int, cache_write_input_tokens: int, cache_read_input_tokens: int, thought_tokens: int}  $usage
     */
    public static function success(
        string $text,
        int $steps = 0,
        array $toolCalls = [],
        array $usage = self::NO_USAGE,
        string $finishReason = 'stop',
    ): self {
        return new self(true, $text, $steps, $toolCalls, $usage + self::NO_USAGE, $finishReason);
    }

    /**
     * @param  array<string, int>  $usage  Tokens already spent before the failure.
     */
    public static function failed(string $error, array $usage = self::NO_USAGE): self
    {
        return new self(false, '', 0, [], $usage + self::NO_USAGE, 'error', $error);
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
