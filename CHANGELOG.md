# Changelog

## [1.0.0] - 2026-07-15
- First tagged release. Everything below this entry only ever shipped as a branch, so this is the first version anything can depend on.
- Tool parameters can now be objects and lists of objects, built from nested specs. A tool acting on many records in one call carries one entry per record, instead of spreading each record's values across parallel lists that can arrive misaligned.
- A harness now builds its system prompt with the run's context in hand, for streamed and buffered runs alike, so a prompt can state who is asking (their currency, locale, defaults) rather than instructing the model to honour values it was never given.
- Tests and formatting run on every pull request, across PHP 8.2, 8.3 and 8.4.

### Breaking
- A harness that builds its own prompt must accept the run's context as an optional first argument: `systemPrompt(?ToolContext $context = null): string`. PHP rejects the previous signature outright, so a harness that has not been updated fails at load rather than misbehaving quietly. Harnesses defined by config are unaffected.

## [0.2.0] - 2026-07-09
- Streaming runs: `Agents::stream()` runs a harness while emitting a progress event for each tool call, text delta and step boundary, returning the same final result a buffered run would. Progress is reported in the package's own vocabulary, so callers never touch the underlying SDK's types. Engines or harnesses that cannot stream fall back to a buffered run.
- Multimodal input: a run accepts images and other media alongside its prompt, with per-call output and timeout limits.

## [0.1.0] - 2026-06-10
- Agent harness over Prism: named system prompt, tool set, model, and step-budgeted tool-calling loop.
- Tool contract and Prism adapter; tool and harness registries with config registration, runtime `extend()`, and optional auto-discovery.
- Built-in tools: clock, calculator, owner-scoped Eloquent query and write, HTTP fetch, web search, storage read, and an MCP adapter.
- Default-deny security: per-user tool context, model/column allowlists, host and path jails, write opt-in.
- Console commands: `agents:tools`, `agents:harnesses`, `agents:run`.
