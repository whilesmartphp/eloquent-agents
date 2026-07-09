# Changelog

## [0.2.0] - 2026-07-09
- Streaming runs: `Agents::stream()` runs a harness while emitting a progress event for each tool call, text delta and step boundary, returning the same final result a buffered run would. Progress is reported in the package's own vocabulary, so callers never touch the underlying SDK's types. Engines or harnesses that cannot stream fall back to a buffered run.
- Multimodal input: a run accepts images and other media alongside its prompt, with per-call output and timeout limits.

## [0.1.0] - 2026-06-10
- Agent harness over Prism: named system prompt, tool set, model, and step-budgeted tool-calling loop.
- Tool contract and Prism adapter; tool and harness registries with config registration, runtime `extend()`, and optional auto-discovery.
- Built-in tools: clock, calculator, owner-scoped Eloquent query and write, HTTP fetch, web search, storage read, and an MCP adapter.
- Default-deny security: per-user tool context, model/column allowlists, host and path jails, write opt-in.
- Console commands: `agents:tools`, `agents:harnesses`, `agents:run`.
