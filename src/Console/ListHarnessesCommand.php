<?php

namespace Whilesmart\Agents\Console;

use Illuminate\Console\Command;
use Whilesmart\Agents\Registries\HarnessRegistry;

class ListHarnessesCommand extends Command
{
    protected $signature = 'agents:harnesses';

    protected $description = 'List the harnesses registered with the agents package';

    public function handle(HarnessRegistry $registry): int
    {
        $names = $registry->names();

        if ($names === []) {
            $this->warn('No harnesses registered.');

            return self::SUCCESS;
        }

        $this->table(['Harness'], array_map(fn (string $name): array => [$name], $names));

        return self::SUCCESS;
    }
}
