## Built-in Tools

The package ships seven ready-made tools. Each implements the `Tool` contract and is registered automatically by the service provider.

### Clock

| Property | Value |
|---|---|
| Name | `clock` |
| Permission | `read` |

Gives the model the current date and time plus common relative anchors. Models have no concept of time, so this is the cheapest high-value tool for any date-related reasoning.

**Parameters:**
- `timezone` (string, optional) - IANA timezone, defaults to the application timezone.

### Calculator

| Property | Value |
|---|---|
| Name | `calculator` |
| Permission | `read` |

Deterministic arithmetic using a hand-written shunting-yard evaluator. Supports `+`, `-`, `*`, `/`, parentheses, and unary minus. No `eval()` is used. Language models are unreliable at multi-digit math, so numeric reasoning should always route through this tool.

**Parameters:**
- `expression` (string) - The arithmetic expression to evaluate.

### Eloquent Query

| Property | Value |
|---|---|
| Name | `eloquent.query` |
| Permission | `read` |

Read application data through a strict allowlist. Nothing is queryable unless declared in `config('agents.eloquent.models')`. Rows are scoped to the acting user through the configured owner key, and only readable columns are returned.

**Parameters:**
- `resource` (string) - Which resource to query.
- `select` (enum: list, count, sum, avg, min, max, optional) - How to read the data.
- `column` (string, optional) - Column to aggregate (required for sum, avg, min, max).
- `limit` (number, optional) - Maximum rows to return for `select=list`.

### Eloquent Write

| Property | Value |
|---|---|
| Name | `eloquent.write` |
| Permission | `write` |

Create and update application data through a strict allowlist. Off unless `config('agents.eloquent.allow_writes')` is true and a user is present. Only writable columns are applied (mass-assignment guard), and the owner key is forced to the acting user on create.

**Parameters:**
- `resource` (string) - Which resource to write.
- `operation` (enum: create, update) - Whether to create or update.
- `id` (number, optional) - Record id, required for update.
- `values` (string) - JSON object of column-value pairs.

### HTTP Fetch

| Property | Value |
|---|---|
| Name | `http.fetch` |
| Permission | `external` |

Fetch the contents of an HTTP or HTTPS URL. Constrained by a host allowlist (empty means deny all), scheme check, and response-size cap.

**Parameters:**
- `url` (string) - The absolute HTTP or HTTPS URL to fetch.

### Web Search

| Property | Value |
|---|---|
| Name | `web.search` |
| Permission | `external` |

Run a web search through a pluggable driver. Ships with a null driver that returns nothing. Apps configure a real driver class through `config('agents.web_search.driver')` to enable it.

**Parameters:**
- `query` (string) - The search query.

### Storage Read

| Property | Value |
|---|---|
| Name | `storage.read` |
| Permission | `read` |

List files in a directory or read a file from application storage. Paths are relative to the configured storage root and jailed to a path prefix. Path traversal is rejected and reads are size-capped.

**Parameters:**
- `operation` (enum: list, read) - Whether to list a directory or read a file.
- `path` (string, optional) - Path relative to the storage root.
