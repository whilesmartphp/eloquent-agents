## Installation

Install the package via Composer:

```bash
composer require whilesmart/eloquent-agents
```

Publish the configuration:

```bash
php artisan vendor:publish --tag=agents-config
```

This publishes `config/agents.php` where you configure providers, models, tools, and harnesses.

### Provider Credentials

Eloquent Agents uses Prism internally, which reads credentials from provider-native environment variables. Set at least one of these depending on your chosen provider:

```dotenv
# Gemini (default)
GEMINI_API_KEY=

# Anthropic
ANTHROPIC_API_KEY=

# OpenAI
OPENAI_API_KEY=

# xAI (Grok)
XAI_API_KEY=
```

### Default Model

Set a default provider and model. Used by every harness that does not specify its own.

```dotenv
AGENTS_PROVIDER=gemini
AGENTS_MODEL=gemini-2.0-flash
```

### Optional Environment Variables

| Variable | Default | Description |
|---|---|---|
| `AGENTS_TEMPERATURE` | `0` | Model temperature |
| `AGENTS_MAX_STEPS` | `8` | Hard cap on tool-calling steps |
| `AGENTS_MAX_TOKENS` | provider default | Max output tokens per response |
| `AGENTS_REQUEST_TIMEOUT` | `0` (disabled) | HTTP timeout per provider call in seconds |
| `AGENTS_ELOQUENT_MAX_ROWS` | `50` | Max rows returned by `eloquent.query` |
| `AGENTS_ELOQUENT_ALLOW_WRITES` | `false` | Enable `eloquent.write` |
| `AGENTS_HTTP_ALLOWED_HOSTS` | empty | Comma-separated host allowlist for `http.fetch` |
| `AGENTS_HTTP_TIMEOUT` | `10` | HTTP fetch timeout in seconds |
| `AGENTS_HTTP_MAX_BYTES` | `100000` | Max response body size for `http.fetch` |
| `AGENTS_WEB_SEARCH_DRIVER` | `null` | Driver class for `web.search` |
| `AGENTS_WEB_SEARCH_MAX_RESULTS` | `5` | Max search results |
| `AGENTS_STORAGE_DISK` | `local` | Filesystem disk for `storage.read` |
| `AGENTS_STORAGE_PREFIX` | empty | Path prefix jail for `storage.read` |
| `AGENTS_STORAGE_MAX_BYTES` | `100000` | Max file read size |
| `AGENTS_REGISTER_ROUTES` | `false` | Register built-in HTTP routes |
| `AGENTS_ROUTE_PREFIX` | `api` | Route prefix for built-in routes |
| `AGENTS_DISCOVERY` | `false` | Enable auto-discovery of `App\Ai\Tools` |
