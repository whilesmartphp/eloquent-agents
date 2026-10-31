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
 * Sources (such as a remote MCP server) are asked for their tools only when a
 * lookup first needs them.
 */
class ToolRegistry implements ToolResolver
{
    /** @var array<string, Tool|Closure():Tool> */
    protected array $tools = [];

    /** @var array<int, Closure():iterable<Tool>> */
    protected array $sources = [];

    /** @var array<int, int> */
    protected array $retryAt = [];

    protected int $retryAfter = 30;

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

    /**
     * Add tools that are only known once asked for, such as a remote server's.
     * A tool already registered under the same name wins. A source that fails is
     * reported and asked again after a short pause.
     *
     * @param  Closure():iterable<Tool>  $source
     */
    public function source(Closure $source): void
    {
        $this->sources[] = $source;
    }

    public function has(string $name): bool
    {
        if (! isset($this->tools[$name])) {
            $this->loadSources();
        }

        return isset($this->tools[$name]);
    }

    public function resolve(string $name): Tool
    {
        if (! isset($this->tools[$name])) {
            $this->loadSources();
        }

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
        $this->loadSources();

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
        $this->loadSources();

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

    protected function loadSources(): void
    {
        foreach ($this->sources as $key => $source) {
            if (($this->retryAt[$key] ?? 0) > time()) {
                continue;
            }

            try {
                $tools = $source();
            } catch (\Throwable $e) {
                report($e);
                $this->retryAt[$key] = time() + $this->retryAfter;

                continue;
            }

            unset($this->sources[$key], $this->retryAt[$key]);

            foreach ($tools as $tool) {
                $this->tools[$tool->name()] ??= $tool;
            }
        }
    }
}
