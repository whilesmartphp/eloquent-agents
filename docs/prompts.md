## Prompts

The prompt registry resolves named prompts for harnesses. A harness can reference a prompt by name in its config, and the registry resolves it through a priority chain.

### Resolution Order

When `GenericHarness` calls `systemPrompt()`, it asks the `PromptRegistry` to resolve the prompt name. The lookup order is:

1. **Inline config override** - `config('agents.prompts.{name}')` in `config/agents.php`.
2. **Published override file** - `resources/vendor/agents/prompts/{name}.md` (published via `vendor:publish --tag=agents-prompts`).
3. **Package default file** - The markdown file shipped with the package at `resources/prompts/{name}.md`.
4. **Literal fallback** - If no prompt matches the name, the name itself is used as the prompt text.

### Overriding a Prompt

Publish the prompts to override any of the package default prompts:

```bash
php artisan vendor:publish --tag=agents-prompts
```

Edit the markdown files in `resources/vendor/agents/prompts/` to change the system prompt.

Or override inline in config:

```php
// config/agents.php
'prompts' => [
    'example-assistant' => 'You are a specialised assistant for this application.',
],
```

### Using Prompts in Harnesses

A config array harness references a prompt by name:

```php
'harnesses' => [
    'assistant' => [
        'prompt' => 'example-assistant',
        'tools' => ['clock', 'calculator'],
    ],
],
```

If the name matches no registered prompt, the text is used literally.
