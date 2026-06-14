# Changelog

## [0.1.0] - 2026-06-10
- Agent harness over Prism: named system prompt, tool set, model, and step-budgeted tool-calling loop.
- Tool contract and Prism adapter; tool and harness registries with config registration, runtime `extend()`, and optional auto-discovery.
- Built-in tools: clock, calculator, owner-scoped Eloquent query and write, HTTP fetch, web search, storage read, and an MCP adapter.
- Default-deny security: per-user tool context, model/column allowlists, host and path jails, write opt-in.
- Console commands: `agents:tools`, `agents:harnesses`, `agents:run`.
