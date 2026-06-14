<?php

namespace Whilesmart\Agents\Tools;

use Whilesmart\Agents\Contracts\WebSearchDriver;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\Tools\Search\NullWebSearchDriver;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Run a web search through a pluggable driver. Ships with a null driver that
 * returns nothing; apps configure a real driver class to enable it.
 */
class WebSearchTool extends AbstractTool
{
    public function name(): string
    {
        return 'web.search';
    }

    public function description(): string
    {
        return 'Search the web for up-to-date information and return a list of results (title, url, snippet).';
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::EXTERNAL;
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('query', 'The search query'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $query = trim((string) ($arguments['query'] ?? ''));

        if ($query === '') {
            return 'Provide a non-empty search query.';
        }

        $driver = $this->driver();
        $results = $driver->search($query, (int) config('agents.web_search.max_results', 5));

        if ($results === []) {
            return $driver instanceof NullWebSearchDriver
                ? 'Web search is not configured.'
                : 'No results found.';
        }

        return ['query' => $query, 'results' => $results];
    }

    protected function driver(): WebSearchDriver
    {
        $driver = config('agents.web_search.driver', 'null');

        if (is_string($driver) && $driver !== 'null' && class_exists($driver)) {
            return app($driver);
        }

        return new NullWebSearchDriver;
    }
}
