<?php

namespace Whilesmart\Agents;

use Illuminate\Support\ServiceProvider;
use Whilesmart\Agents\Contracts\AgentEngine;
use Whilesmart\Agents\Contracts\ToolResolver;
use Whilesmart\Agents\Engines\Prism\PrismEngine;
use Whilesmart\Agents\Engines\Prism\PrismToolAdapter;
use Whilesmart\Agents\Prompts\PromptRegistry;
use Whilesmart\Agents\Registries\HarnessRegistry;
use Whilesmart\Agents\Registries\ModelResourceRegistry;
use Whilesmart\Agents\Registries\ToolRegistry;
use Whilesmart\Agents\Resources\ResourceScope;
use Whilesmart\Agents\Tools\ListResourceTool;

class AgentsServiceProvider extends ServiceProvider
{
    /**
     * Tools registered for every consuming app unless they opt out. The MCP
     * adapter is excluded: it wraps a backend-native tool and is registered
     * explicitly by apps that need it.
     *
     * @var array<int, class-string<Contracts\Tool>>
     */
    protected array $defaultTools = [
        Tools\ClockTool::class,
        Tools\CalculatorTool::class,
        Tools\EloquentQueryTool::class,
        Tools\EloquentWriteTool::class,
        Tools\HttpFetchTool::class,
        Tools\WebSearchTool::class,
        Tools\StorageReadTool::class,
    ];

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/agents.php', 'agents');

        $this->app->singleton(PrismToolAdapter::class);
        $this->app->bind(AgentEngine::class, PrismEngine::class);

        $this->app->singleton(ToolRegistry::class);
        $this->app->bind(ToolResolver::class, fn ($app) => $app->make(ToolRegistry::class));
        $this->app->singleton(PromptRegistry::class);
        $this->app->singleton(ModelResourceRegistry::class);
        $this->app->singleton(ResourceScope::class);

        $this->app->singleton(HarnessRegistry::class, fn ($app) => new HarnessRegistry(
            $app->make(ToolResolver::class),
            $app->make(PromptRegistry::class),
        ));

        $this->app->singleton('agents', fn ($app) => new AgentManager(
            $app->make(ToolRegistry::class),
            $app->make(HarnessRegistry::class),
        ));
        $this->app->alias('agents', AgentManager::class);
    }

    public function boot(): void
    {
        $this->registerConfiguredResources();
        $this->registerConfiguredTools();
        $this->registerConfiguredHarnesses();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/agents.php' => config_path('agents.php'),
            ], 'agents-config');

            $this->publishes([
                __DIR__.'/../resources/prompts' => resource_path('vendor/agents/prompts'),
            ], 'agents-prompts');

            $this->commands([
                Console\ListToolsCommand::class,
                Console\ListHarnessesCommand::class,
                Console\RunHarnessCommand::class,
                Console\ExportSchemaCommand::class,
            ]);
        }
    }

    /**
     * Turn each configured model resource into a read tool. Registration happens
     * before the configured tool list so an app can still override a generated
     * tool by registering its own under the same name.
     */
    protected function registerConfiguredResources(): void
    {
        /** @var ModelResourceRegistry $resources */
        $resources = $this->app->make(ModelResourceRegistry::class);
        $resources->registerMany((array) config('agents.resources.models', []));

        /** @var ToolRegistry $tools */
        $tools = $this->app->make(ToolRegistry::class);
        $scope = $this->app->make(ResourceScope::class);

        foreach ($resources->all() as $resource) {
            if ($resource->readTool) {
                $tools->register(new ListResourceTool($resource, $scope));
            }
        }
    }

    protected function registerConfiguredTools(): void
    {
        /** @var ToolRegistry $registry */
        $registry = $this->app->make(ToolRegistry::class);

        foreach ($this->defaultTools as $tool) {
            $registry->register($tool);
        }

        foreach ((array) config('agents.tools', []) as $tool) {
            $registry->register($tool);
        }

        if (config('agents.discovery.enabled')) {
            $registry->discover(
                (string) config('agents.discovery.namespace'),
                (string) config('agents.discovery.path'),
            );
        }
    }

    protected function registerConfiguredHarnesses(): void
    {
        /** @var HarnessRegistry $registry */
        $registry = $this->app->make(HarnessRegistry::class);

        foreach ((array) config('agents.harnesses', []) as $name => $definition) {
            $registry->register($name, $definition);
        }
    }
}
