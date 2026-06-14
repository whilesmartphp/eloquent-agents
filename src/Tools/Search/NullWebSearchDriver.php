<?php

namespace Whilesmart\Agents\Tools\Search;

use Whilesmart\Agents\Contracts\WebSearchDriver;

/**
 * The default search driver: returns nothing. Apps bind their own driver
 * (config agents.web_search.driver = MyDriver::class) to enable web search.
 */
class NullWebSearchDriver implements WebSearchDriver
{
    public function search(string $query, int $maxResults): array
    {
        return [];
    }
}
