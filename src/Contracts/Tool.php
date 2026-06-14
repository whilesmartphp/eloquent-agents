<?php

namespace Whilesmart\Agents\Contracts;

use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

interface Tool
{
    /**
     * The unique, model-facing tool name (snake/dotted, e.g. "eloquent.query").
     */
    public function name(): string;

    /**
     * Natural-language description the model uses to decide when to call it.
     */
    public function description(): string;

    /**
     * @return array<int, ParameterSpec>
     */
    public function parameters(): array;

    public function permission(): ToolPermission;

    /**
     * Per-call gate evaluated against the acting user before handle() runs.
     */
    public function authorize(ToolContext $context): bool;

    /**
     * Execute the tool. Return a string, or an array (JSON-encoded for the model).
     *
     * @param  array<string, mixed>  $arguments
     * @return string|array<mixed>
     */
    public function handle(array $arguments, ToolContext $context): string|array;
}
