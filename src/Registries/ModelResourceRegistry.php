<?php

namespace Whilesmart\Agents\Registries;

use InvalidArgumentException;
use Whilesmart\Agents\Contracts\HasAgentResource;
use Whilesmart\Agents\Resources\AgentResource;

/**
 * Holds the model resources an application exposes to agents. Populated from
 * config('agents.resources'); a model absent from that list is invisible to
 * agents no matter what it declares.
 */
class ModelResourceRegistry
{
    /** @var array<string, AgentResource> */
    protected array $resources = [];

    /**
     * @param  class-string<HasAgentResource>|AgentResource  $resource
     */
    public function register(string|AgentResource $resource): AgentResource
    {
        if (is_string($resource)) {
            if (! is_subclass_of($resource, HasAgentResource::class)) {
                throw new InvalidArgumentException(
                    "Model [{$resource}] must implement ".HasAgentResource::class.' to be registered as an agent resource.'
                );
            }

            $resource = $resource::agentResource();
        }

        $this->resources[$resource->name] = $resource;

        return $resource;
    }

    /**
     * @param  array<int, class-string<HasAgentResource>|AgentResource>  $resources
     */
    public function registerMany(array $resources): void
    {
        foreach ($resources as $resource) {
            $this->register($resource);
        }
    }

    public function has(string $name): bool
    {
        return isset($this->resources[$name]);
    }

    public function find(string $name): ?AgentResource
    {
        return $this->resources[$name] ?? null;
    }

    /**
     * Find by name or by any declared alias, so a resource answers to whatever
     * the application calls it elsewhere.
     */
    public function findByAlias(string $name): ?AgentResource
    {
        if ($resource = $this->find($name)) {
            return $resource;
        }

        foreach ($this->resources as $resource) {
            if (in_array($name, $resource->aliases, true)) {
                return $resource;
            }
        }

        return null;
    }

    /**
     * @return array<string, AgentResource>
     */
    public function all(): array
    {
        return $this->resources;
    }

    /**
     * @return array<int, string>
     */
    public function names(): array
    {
        return array_keys($this->resources);
    }

    /**
     * Names of the read tools generated from these resources. Resources whose
     * reads are already covered by a hand-written tool are absent.
     *
     * @return array<int, string>
     */
    public function toolNames(): array
    {
        return array_values(array_map(
            fn (AgentResource $resource): string => $resource->toolName(),
            array_filter($this->resources, fn (AgentResource $resource): bool => $resource->readTool),
        ));
    }
}
