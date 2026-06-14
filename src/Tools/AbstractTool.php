<?php

namespace Whilesmart\Agents\Tools;

use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Base for application tools. Implement name(), description(), handle(), and
 * optionally parameters()/permission()/authorize(). Nothing here knows about
 * any LLM SDK: the engine adapts this into whatever the backend needs.
 */
abstract class AbstractTool implements Tool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @param  array<string, mixed>  $arguments
     * @return string|array<mixed>
     */
    abstract public function handle(array $arguments, ToolContext $context): string|array;

    /**
     * @return array<int, ParameterSpec>
     */
    public function parameters(): array
    {
        return [];
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::READ;
    }

    /**
     * Default-allow at the context level; security-sensitive tools override this
     * and the built-in data tools deny by virtue of empty allowlists.
     */
    public function authorize(ToolContext $context): bool
    {
        return true;
    }
}
