<?php

namespace Whilesmart\Agents\Registries;

use Whilesmart\Agents\Exceptions\AgentNotFoundException;
use Whilesmart\Agents\Exceptions\InvalidAgentDefinition;
use Whilesmart\Agents\ValueObjects\AgentDefinition;

class AgentRegistry
{
    private array $agents = [];

    public function register(string $name, array|AgentDefinition $definition): void
    {
        if ($this->has($name)) {
            throw InvalidAgentDefinition::duplicate($name);
        }

        $agent = is_array($definition) ? AgentDefinition::fromArray($name, $definition) : $definition;

        if ($agent->name !== $name) {
            throw InvalidAgentDefinition::invalid($name, 'name must match the registered name');
        }

        $this->agents[$name] = $agent;
    }

    public function has(string $name): bool
    {
        return isset($this->agents[$name]);
    }

    public function get(string $name): AgentDefinition
    {
        return $this->agents[$name] ?? throw AgentNotFoundException::named($name);
    }

    public function names(): array
    {
        return array_keys($this->agents);
    }

    public function ofType(string $type): array
    {
        return array_keys(array_filter($this->agents, fn (AgentDefinition $agent) => $agent->type === $type));
    }
}
