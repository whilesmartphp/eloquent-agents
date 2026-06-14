<?php

namespace Whilesmart\Agents\Console;

use Illuminate\Console\Command;
use Whilesmart\Agents\Registries\ToolRegistry;

class ListToolsCommand extends Command
{
    protected $signature = 'agents:tools';

    protected $description = 'List the tools registered with the agents package';

    public function handle(ToolRegistry $registry): int
    {
        $rows = [];

        foreach ($registry->names() as $name) {
            $tool = $registry->resolve($name);
            $rows[] = [$tool->name(), $tool->permission()->value, $tool->description()];
        }

        if ($rows === []) {
            $this->warn('No tools registered.');

            return self::SUCCESS;
        }

        $this->table(['Name', 'Permission', 'Description'], $rows);

        return self::SUCCESS;
    }
}
