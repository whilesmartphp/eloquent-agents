<?php

namespace Whilesmart\Agents\Engines\Prism\Contracts;

use Prism\Prism\Tool as PrismTool;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Escape hatch for tools that are already native to Prism (e.g. an MCP server
 * tool). A tool implementing this hands the adapter a ready Prism tool instead
 * of being rebuilt from parameters()/handle(). Lives under the Prism engine so
 * the rest of the package never references it.
 */
interface ProvidesPrismTool
{
    public function toPrismTool(ToolContext $context): PrismTool;
}
