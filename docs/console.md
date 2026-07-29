## Console Commands

The package registers three Artisan commands that become available once the service provider boots.

### agents:tools

List every tool registered in the `ToolRegistry`, including defaults, config-registered tools, and auto-discovered tools.

```bash
php artisan agents:tools
```

Outputs a table with the tool name, permission level, and description.

### agents:harnesses

List every harness registered in the `HarnessRegistry`.

```bash
php artisan agents:harnesses
```

### agents:run

Run a harness against a message and print the result. This calls the live provider, so provider credentials must be configured.

```bash
php artisan agents:run assistant "What is 1200.50 plus 300?" --user=1
```

**Arguments:**
- `harness` - The harness name to run.
- `input` - The user message.

**Options:**
- `--user` - Act as this user ID. Resolved against the model defined in `auth.providers.users.model`. Omit for a guest context.
