## Security Model

The package follows a default-deny security model. Every tool call is subject to permission checks, authorization gates, and application-level allowlists before it touches any data.

### Tool Context

Every tool receives a `ToolContext` carrying the acting user, locale, and arbitrary scope data. Tools must never trust model-supplied identifiers; they scope their work to the user in the context.

```php
ToolContext::forUser($user, locale: 'en', scope: ['team_id' => 42]);
ToolContext::guest();
```

### Permission Levels

Each tool declares a `ToolPermission` level:

| Level | Example Tools | Meaning |
|---|---|---|
| `READ` | `clock`, `calculator`, `eloquent.query`, `storage.read` | Read-only operations |
| `WRITE` | `eloquent.write` | Mutates application data |
| `EXTERNAL` | `http.fetch`, `web.search` | Makes outbound network requests |

Harnesses can restrict which permission levels are available:

```php
// This harness only exposes READ tools
public function allowedPermissions(): array
{
    return [ToolPermission::READ];
}
```

### Authorization Gates

Tools may override `authorize()` for per-call checks evaluated before `handle()`:

```php
public function authorize(ToolContext $context): bool
{
    return $context->user !== null && $context->user->isAdmin();
}
```

### Eloquent Data Tools

`eloquent.query` and `eloquent.write` expose nothing outside the configured model and column allowlist:

- Models must be explicitly declared in `config('agents.eloquent.models')`.
- Only listed columns are readable or writable.
- Rows are scoped to the acting user via the configured `owner_key`.
- `eloquent.write` requires `config('agents.eloquent.allow_writes')` to be `true` and forces the owner key on create.

```php
'eloquent' => [
    'allow_writes' => env('AGENTS_ELOQUENT_ALLOW_WRITES', false),
    'models' => [
        'transactions' => [
            'model' => App\Models\Transaction::class,
            'owner_key' => 'user_id',
            'readable' => ['amount', 'type', 'description', 'created_at'],
            'writable' => ['amount', 'type', 'description'],
        ],
    ],
],
```

### HTTP Fetch

- Host allowlist via `config('agents.http.allowed_hosts')`. Empty list denies all hosts.
- Only HTTP and HTTPS schemes are accepted.
- Response body is capped at `config('agents.http.max_bytes')`.

### Storage Read

- Paths are jailed to `config('agents.storage.path_prefix')`.
- Path traversal (`..`) is rejected.
- Reads are capped at `config('agents.storage.max_bytes')`.

### Agent Loop

The tool-calling step budget is bounded by `config('agents.max_steps')` (default 8). This hard cap cannot be exceeded by any harness or per-run override, preventing runaway cost.
