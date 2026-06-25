## MCP Support

The package can bridge any Prism tool, including Model Context Protocol (MCP) server tools, into the tool registry.

### Wrapping an MCP Tool

Use `McpTool::wrap()` to adopt a pre-built Prism `Tool` object. The common case is wrapping a `LaravelMcpTool` that connects to a Laravel MCP server.

```php
use Prism\Prism\Tools\LaravelMcpTool;
use Whilesmart\Agents\Engines\Prism\Tools\McpTool;
use Whilesmart\Agents\Facades\Agents;

// Create a Prism MCP tool from a server tool definition
$mcpServerTool = new LaravelMcpTool($mcpServerToolDefinition);

// Wrap it for the agent registry
Agents::registerTool(
    McpTool::wrap($mcpServerTool, permission: \Whitesmart\Agents\Enums\ToolPermission::EXTERNAL),
);
```

### How It Works

`McpTool` implements both the `Tool` contract and the `ProvidesPrismTool` contract. When the `PrismToolAdapter` encounters a tool that implements `ProvidesPrismTool`, it returns the native Prism tool directly instead of translating it through the adapter's parameter schema mapping. This means the MCP tool's own schema definitions are preserved verbatim.

### Permission

By default, `McpTool` uses `ToolPermission::EXTERNAL` because MCP tools typically make outbound requests. Pass a different permission to `wrap()` as the second argument if needed.
