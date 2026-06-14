<?php

namespace Whilesmart\Agents\Registries;

use Whilesmart\Agents\Contracts\Harness;
use Whilesmart\Agents\Contracts\ToolResolver;
use Whilesmart\Agents\Exceptions\HarnessNotFoundException;
use Whilesmart\Agents\Harness\GenericHarness;
use Whilesmart\Agents\Prompts\PromptRegistry;

/**
 * Holds named harnesses. A registration may be a Harness instance, a config
 * array (built into a GenericHarness), or a class string implementing Harness.
 */
class HarnessRegistry
{
    /** @var array<string, Harness|array<string, mixed>|class-string<Harness>> */
    protected array $harnesses = [];

    public function __construct(
        protected ToolResolver $tools,
        protected PromptRegistry $prompts,
    ) {}

    /**
     * @param  Harness|array<string, mixed>|class-string<Harness>  $harness
     */
    public function register(string $name, Harness|array|string $harness): void
    {
        $this->harnesses[$name] = $harness;
    }

    public function has(string $name): bool
    {
        return isset($this->harnesses[$name]);
    }

    public function get(string $name): Harness
    {
        if (! isset($this->harnesses[$name])) {
            throw HarnessNotFoundException::named($name);
        }

        $harness = $this->harnesses[$name];

        if ($harness instanceof Harness) {
            return $harness;
        }

        if (is_array($harness)) {
            return new GenericHarness($this->tools, $this->prompts, $name, $harness);
        }

        return app($harness);
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->harnesses);
    }
}
