<?php

namespace Whilesmart\Agents\Contracts;

interface WebSearchDriver
{
    /**
     * @return array<int, array{title: string, url: string, snippet: string}>
     */
    public function search(string $query, int $maxResults): array;
}
