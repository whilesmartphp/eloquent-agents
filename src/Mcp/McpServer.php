<?php

namespace Whilesmart\Agents\Mcp;

use Closure;
use Illuminate\Support\Facades\Cache;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * A remote MCP server whose tools join the registry. Tools are discovered once
 * per cache window. Headers and bound arguments are worked out from the run's
 * context on every call, so one registration serves every user.
 */
class McpServer
{
    /** @var array<string, McpClient> */
    protected array $clients = [];

    /** @var array<int, array<string, mixed>>|null */
    protected ?array $definitions = null;

    /**
     * @param  array<string, string>|Closure(ToolContext):array<string, string>  $headers
     * @param  array<string, Closure(ToolContext):mixed|string>  $bind  Argument name to a resolver, or to a key of the context's scope.
     * @param  array<int, string>  $only  Tool names to keep. Empty keeps every tool.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $url,
        protected array|Closure $headers = [],
        protected array $bind = [],
        protected array $only = [],
        protected ?int $cacheTtl = null,
        protected ToolPermission $permission = ToolPermission::EXTERNAL,
        protected ?int $timeout = null,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $name, array $config): self
    {
        $permission = $config['permission'] ?? ToolPermission::EXTERNAL;

        return new self(
            name: $name,
            url: (string) $config['url'],
            headers: $config['headers'] ?? [],
            bind: (array) ($config['bind'] ?? []),
            only: (array) ($config['only'] ?? []),
            cacheTtl: isset($config['cache_ttl']) ? (int) $config['cache_ttl'] : null,
            permission: $permission instanceof ToolPermission ? $permission : ToolPermission::from((string) $permission),
            timeout: isset($config['timeout']) ? (int) $config['timeout'] : null,
        );
    }

    /**
     * @return array<int, RemoteMcpTool>
     */
    public function tools(): array
    {
        $tools = [];

        foreach ($this->definitions() as $definition) {
            if (! is_string($definition['name'] ?? null)) {
                continue;
            }

            if ($this->only !== [] && ! in_array($definition['name'], $this->only, true)) {
                continue;
            }

            $tools[] = new RemoteMcpTool($this, $definition);
        }

        return $tools;
    }

    /**
     * The raw tool definitions from tools/list, cached for the server's TTL.
     *
     * @return array<int, array<string, mixed>>
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $discover = fn (): array => $this->client(ToolContext::guest())->listTools();
        $ttl = $this->cacheTtl ?? (int) config('agents.mcp.cache_ttl', 300);

        return $this->definitions = $ttl > 0
            ? Cache::remember($this->cacheKey(), $ttl, $discover)
            : $discover();
    }

    public function forget(): void
    {
        $this->definitions = null;
        Cache::forget($this->cacheKey());
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function call(string $tool, array $arguments, ToolContext $context): array
    {
        return $this->client($context)->callTool($tool, $arguments);
    }

    /**
     * @return array<int, string>
     */
    public function boundNames(): array
    {
        return array_keys($this->bind);
    }

    /**
     * The bound arguments for this context. One that resolves to null is left out.
     *
     * @return array<string, mixed>
     */
    public function boundArguments(ToolContext $context): array
    {
        $arguments = [];

        foreach ($this->bind as $name => $resolver) {
            $value = $resolver instanceof Closure ? $resolver($context) : $context->get((string) $resolver);

            if ($value !== null) {
                $arguments[$name] = $value;
            }
        }

        return $arguments;
    }

    public function permission(): ToolPermission
    {
        return $this->permission;
    }

    /**
     * @return array<string, string>
     */
    public function headersFor(ToolContext $context): array
    {
        return $this->headers instanceof Closure ? (array) ($this->headers)($context) : $this->headers;
    }

    protected function client(ToolContext $context): McpClient
    {
        $headers = $this->headersFor($context);
        $key = md5(serialize($headers));

        return $this->clients[$key] ??= new McpClient(
            $this->url,
            $headers,
            $this->timeout ?? (int) config('agents.mcp.timeout', 10),
        );
    }

    protected function cacheKey(): string
    {
        return 'agents:mcp:'.$this->name.':'.md5($this->url).':tools';
    }
}
