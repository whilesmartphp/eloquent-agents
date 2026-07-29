## Custom Tools

Tools are the actions your agents can perform. Writing a custom tool means implementing the `Tool` contract, typically by extending `AbstractTool`.

### The Tool Contract

Every tool needs a name, description, optional parameters, optional permission level, optional authorization gate, and a handle method.

```php
use Whilesmart\Agents\Tools\AbstractTool;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

class CreateReminderTool extends AbstractTool
{
    public function name(): string
    {
        return 'create_reminder';
    }

    public function description(): string
    {
        return 'Create a reminder for the current user.';
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('text', 'What to be reminded about'),
            ParameterSpec::string('due_date', 'Due date in YYYY-MM-DD format'),
            ParameterSpec::enum('priority', 'Priority level', ['low', 'normal', 'high']),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $reminder = $context->user->reminders()->create([
            'text' => $arguments['text'],
            'due_date' => $arguments['due_date'],
            'priority' => $arguments['priority'],
        ]);

        return ['id' => $reminder->id, 'status' => 'created'];
    }
}
```

### Parameter Specs

The `ParameterSpec` value object describes one tool parameter in a SDK-agnostic way:

```php
ParameterSpec::string('name', 'Description', $required = true);
ParameterSpec::number('count', 'Description', $required = true);
ParameterSpec::boolean('flag', 'Description', $required = true);
ParameterSpec::enum('mode', 'Description', ['a', 'b', 'c'], $required = true);
ParameterSpec::arrayOf('items', 'Description', ParameterType::STRING, $required = true);
```

### Permission Levels

Tools declare a permission level that harnesses can use to filter available tools:

```php
use Whilesmart\Agents\Enums\ToolPermission;

public function permission(): ToolPermission
{
    return ToolPermission::WRITE;
}
```

Levels: `READ`, `WRITE`, `EXTERNAL`.

### Authorization

Tools can implement a per-call authorization gate evaluated against the current context before `handle()` runs:

```php
public function authorize(ToolContext $context): bool
{
    return $context->user?->can('manage-reminders') ?? false;
}
```

The default implementation returns `true`.

### Registration

Register tools in `config/agents.php`:

```php
'tools' => [
    App\Ai\Tools\CreateReminderTool::class,
],
```

At runtime:

```php
Agents::registerTool(new CreateReminderTool);
// or
Agents::registerTool(CreateReminderTool::class);
```

### Lazy Extension

Override an existing tool or add one lazily:

```php
Agents::extendTool('create_reminder', function () {
    return new CustomReminderTool;
});
```

### Auto-Discovery

Enable auto-discovery when you have many tools under a namespace:

```php
// config/agents.php
'discovery' => [
    'enabled' => true,
    'namespace' => 'App\\Ai\\Tools',
    'path' => app_path('Ai/Tools'),
],
```

Every concrete `AbstractTool` under that path is registered automatically.
