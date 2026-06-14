<?php

namespace Whilesmart\Agents\Prompts;

/**
 * Resolves named prompts. Lookup order: inline config override, a published
 * override file, then the package default file. Unknown names fall back to the
 * caller-supplied default (or are treated as literal text by resolve()).
 */
class PromptRegistry
{
    public function has(string $name): bool
    {
        return $this->get($name) !== null;
    }

    public function get(string $name, ?string $default = null): ?string
    {
        $inline = config("agents.prompts.{$name}");
        if (is_string($inline) && $inline !== '') {
            return $inline;
        }

        foreach ($this->files($name) as $file) {
            if (is_file($file)) {
                return trim((string) file_get_contents($file));
            }
        }

        return $default;
    }

    /**
     * Resolve a value that is either a registered prompt name or literal prompt
     * text. Used by GenericHarness so harness config can reference a prompt by
     * name or inline the text directly.
     */
    public function resolve(string $promptOrName): string
    {
        return $this->get($promptOrName) ?? $promptOrName;
    }

    /**
     * @return array<int, string>
     */
    protected function files(string $name): array
    {
        $files = [];

        if (function_exists('resource_path')) {
            $files[] = resource_path("vendor/agents/prompts/{$name}.md");
        }

        $files[] = __DIR__."/../../resources/prompts/{$name}.md";

        return $files;
    }
}
