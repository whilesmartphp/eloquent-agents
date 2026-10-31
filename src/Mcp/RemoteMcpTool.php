<?php

namespace Whilesmart\Agents\Mcp;

use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * One tool of a remote MCP server, described by its tools/list entry. Bound
 * arguments are hidden from the model and filled from the run's context, so the
 * model cannot choose them.
 */
class RemoteMcpTool implements Tool
{
    /**
     * @param  array<string, mixed>  $definition  The tool's entry from tools/list.
     */
    public function __construct(
        protected McpServer $server,
        protected array $definition,
    ) {}

    public function name(): string
    {
        return (string) $this->definition['name'];
    }

    public function description(): string
    {
        return is_string($this->definition['description'] ?? null) ? $this->definition['description'] : '';
    }

    public function parameters(): array
    {
        $schema = is_array($this->definition['inputSchema'] ?? null) ? $this->definition['inputSchema'] : [];
        $definitions = (array) ($schema['$defs'] ?? $schema['definitions'] ?? []);
        $required = (array) ($schema['required'] ?? []);
        $bound = $this->server->boundNames();
        $parameters = [];

        foreach ((array) ($schema['properties'] ?? []) as $name => $property) {
            if (in_array($name, $bound, true) || ! is_array($property)) {
                continue;
            }

            $parameters[] = ParameterSpec::schema(
                (string) $name,
                $this->inline($property, $definitions),
                in_array($name, $required, true),
            );
        }

        return $parameters;
    }

    public function permission(): ToolPermission
    {
        return $this->server->permission();
    }

    public function authorize(ToolContext $context): bool
    {
        return true;
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $arguments = [
            ...array_diff_key($arguments, array_flip($this->server->boundNames())),
            ...$this->server->boundArguments($context),
        ];

        try {
            $result = $this->server->call($this->name(), $arguments, $context);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }

        if (($result['isError'] ?? false) === true) {
            $text = $this->text($result);

            return ['error' => $text !== '' ? $text : "The {$this->name()} tool reported an error."];
        }

        if (is_array($result['structuredContent'] ?? null)) {
            return $result['structuredContent'];
        }

        return $this->text($result);
    }

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return $this->definition;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    protected function text(array $result): string
    {
        $parts = [];

        foreach ((array) ($result['content'] ?? []) as $item) {
            if (is_array($item) && ($item['type'] ?? null) === 'text' && is_string($item['text'] ?? null)) {
                $parts[] = $item['text'];
            }
        }

        return implode("\n", $parts);
    }

    /**
     * Each property reaches the model on its own, away from the schema's $defs,
     * so local references are replaced by what they point at.
     *
     * @param  array<mixed>  $node
     * @param  array<mixed>  $definitions
     * @return array<mixed>
     */
    protected function inline(array $node, array $definitions, int $depth = 0): array
    {
        if (is_string($node['$ref'] ?? null) && preg_match('~^#/(?:\$defs|definitions)/(.+)$~', $node['$ref'], $match)) {
            $target = $definitions[$match[1]] ?? null;
            unset($node['$ref']);

            if (is_array($target) && $depth < 8) {
                $node = [...$this->inline($target, $definitions, $depth + 1), ...$node];
            }
        }

        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $node[$key] = $this->inline($value, $definitions, $depth);
            }
        }

        return $node;
    }
}
