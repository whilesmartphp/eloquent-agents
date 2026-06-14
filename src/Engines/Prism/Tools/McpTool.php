<?php

namespace Whilesmart\Agents\Engines\Prism\Tools;

use Prism\Prism\Tool as PrismTool;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Engines\Prism\Contracts\ProvidesPrismTool;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Adopts a pre-built Prism tool into the registry. The common case is bridging
 * a Laravel MCP server tool: wrap it with Prism's LaravelMcpTool and hand it
 * here, e.g. McpTool::wrap(new \Prism\Prism\Tools\LaravelMcpTool($serverTool)).
 *
 * This is the one Prism-native tool the package ships, so it lives under the
 * Prism engine and implements ProvidesPrismTool: the adapter returns its wrapped
 * tool verbatim instead of rebuilding it.
 */
class McpTool implements ProvidesPrismTool, Tool
{
    public function __construct(
        protected PrismTool $tool,
        protected ToolPermission $permission = ToolPermission::EXTERNAL,
    ) {}

    public static function wrap(PrismTool $tool, ToolPermission $permission = ToolPermission::EXTERNAL): self
    {
        return new self($tool, $permission);
    }

    public function name(): string
    {
        return $this->tool->name();
    }

    public function description(): string
    {
        return $this->tool->description();
    }

    public function parameters(): array
    {
        return [];
    }

    public function permission(): ToolPermission
    {
        return $this->permission;
    }

    public function authorize(ToolContext $context): bool
    {
        return true;
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        return $this->tool->handle(...$arguments);
    }

    public function toPrismTool(ToolContext $context): PrismTool
    {
        return $this->tool;
    }
}
