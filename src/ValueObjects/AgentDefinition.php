<?php

namespace Whilesmart\Agents\ValueObjects;

use Whilesmart\Agents\Exceptions\InvalidAgentDefinition;

final readonly class AgentDefinition
{
    private function __construct(
        public string $name,
        public string $type,
        public string $label,
        public ?string $provider,
        public ?string $model,
        public ?string $modelVersion,
        public ?string $harness,
        public array $attributes,
    ) {}

    public static function fromArray(string $name, array $definition): self
    {
        if (trim($name) === '') {
            throw InvalidAgentDefinition::invalid($name, 'name must be a nonempty string');
        }

        if (array_key_exists('name', $definition) && $definition['name'] !== $name) {
            throw InvalidAgentDefinition::invalid($name, 'name must match the registered name');
        }

        if (! is_string($definition['type'] ?? null) || trim($definition['type']) === '') {
            throw InvalidAgentDefinition::invalid($name, 'type must be a nonempty string');
        }

        $optional = ['label', 'provider', 'model', 'model_version', 'harness'];

        foreach ($optional as $key) {
            $value = $definition[$key] ?? null;
            if ($value !== null && (! is_string($value) || trim($value) === '')) {
                throw InvalidAgentDefinition::invalid($name, "{$key} must be a nonempty string");
            }
        }

        return new self(
            $name,
            $definition['type'],
            $definition['label'] ?? $name,
            $definition['provider'] ?? null,
            $definition['model'] ?? null,
            $definition['model_version'] ?? null,
            $definition['harness'] ?? null,
            array_diff_key($definition, array_flip([...$optional, 'type', 'name'])),
        );
    }
}
