<?php

namespace Whilesmart\Agents\Sources;

use JsonException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Whilesmart\Agents\Contracts\AgentSource;
use Whilesmart\Agents\Exceptions\InvalidAgentDefinition;
use Whilesmart\Agents\ValueObjects\AgentDefinition;

class FileAgentSource implements AgentSource
{
    public function __construct(protected ?array $paths = null) {}

    public function definitions(): array
    {
        $definitions = [];

        foreach ($this->paths ?? (array) config('agents.paths', []) as $path) {
            foreach ($this->files($path) as $file) {
                $definition = $this->read($file);
                $name = is_string($definition['name'] ?? null) ? $definition['name'] : pathinfo($file, PATHINFO_FILENAME);

                try {
                    $agent = AgentDefinition::fromArray($name, $definition);
                } catch (InvalidAgentDefinition $exception) {
                    throw InvalidAgentDefinition::unreadable($file, $exception->getMessage());
                }

                if (isset($definitions[$name])) {
                    throw InvalidAgentDefinition::duplicate($name);
                }

                $definitions[$name] = $agent;
            }
        }

        return $definitions;
    }

    protected function files(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $files = array_filter(scandir($path) ?: [], fn ($file) => in_array(pathinfo($file, PATHINFO_EXTENSION), ['json', 'yaml', 'yml'], true));
        sort($files);

        return array_map(fn ($file) => rtrim($path, '/').'/'.$file, $files);
    }

    protected function read(string $file): array
    {
        $contents = file_get_contents($file);

        if ($contents === false) {
            throw InvalidAgentDefinition::unreadable($file, 'the file cannot be opened');
        }

        try {
            $definition = str_ends_with($file, '.json')
                ? json_decode($contents, true, 512, JSON_THROW_ON_ERROR)
                : $this->yaml($file, $contents);
        } catch (JsonException|ParseException $exception) {
            throw InvalidAgentDefinition::unreadable($file, $exception->getMessage());
        }

        if (! is_array($definition) || array_is_list($definition)) {
            throw InvalidAgentDefinition::unreadable($file, 'the file must contain an object of agent settings');
        }

        return $definition;
    }

    protected function yaml(string $file, string $contents): mixed
    {
        if (! class_exists(Yaml::class)) {
            throw InvalidAgentDefinition::unreadable($file, 'install symfony/yaml to read YAML definitions');
        }

        return Yaml::parse($contents);
    }
}
