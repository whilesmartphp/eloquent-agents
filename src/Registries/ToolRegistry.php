<?php

namespace Whilesmart\Agents\Registries;

use Closure;
use Whilesmart\Agents\Contracts\Tool;
use Whilesmart\Agents\Contracts\ToolResolver;
use Whilesmart\Agents\Exceptions\ToolNotFoundException;
use Whilesmart\Agents\Tools\AbstractTool;

/**
 * Holds the tools available to harnesses. Ships defaults, accepts app
 * registrations by class or instance, lazy `extend()` closures, and optional
 * filesystem auto-discovery. Mirrors the whilesmart Manager + extend() pattern.
 */
class ToolRegistry implements ToolResolver
{
    /** @var array<string, Tool|Closure():Tool> */
    protected array $tools = [];

    /**
     * Register a tool by instance or class string. Class strings are resolved
     * through the container so tools may declare dependencies.
     *
     * @param  Tool|class-string<Tool>  $tool
     */
    public function register(Tool|string $tool): void
    {
        $instance = is_string($tool) ? app($tool) : $tool;

        $this->tools[$instance->name()] = $instance;
    }

    /**
     * Register a tool under an explicit name, optionally lazily via a closure.
     */
    public function extend(string $name, Closure|Tool $tool): void
    {
        $this->tools[$name] = $tool;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }

    public function resolve(string $name): Tool
    {
        if (! isset($this->tools[$name])) {
            throw ToolNotFoundException::named($name);
        }

        $tool = $this->tools[$name];

        if ($tool instanceof Closure) {
            $tool = $tool();
            $this->tools[$name] = $tool;
        }

        return $tool;
    }

    /**
     * @return array<string, Tool>
     */
    public function all(): array
    {
        return array_map(fn (string $name): Tool => $this->resolve($name), array_combine(
            array_keys($this->tools),
            array_keys($this->tools),
        ));
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->tools);
    }

    /**
     * Register every concrete AbstractTool found under a namespace/path.
     */
    public function discover(string $namespace, string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $namespace = rtrim($namespace, '\\');

        foreach (glob($path.'/*.php') ?: [] as $file) {
            $class = $namespace.'\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class)) {
                continue;
            }

            if (is_subclass_of($class, AbstractTool::class) && ! (new \ReflectionClass($class))->isAbstract()) {
                $this->register($class);
            }
        }
    }
}
