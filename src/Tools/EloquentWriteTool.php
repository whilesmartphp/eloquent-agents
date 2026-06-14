<?php

namespace Whilesmart\Agents\Tools;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Create and update application data through a strict allowlist. Off unless
 * config('agents.eloquent.allow_writes') is true and a user is present. Only
 * writable columns are applied (mass-assignment guard), and the owner key is
 * forced to the acting user on create.
 */
class EloquentWriteTool extends AbstractTool
{
    public function name(): string
    {
        return 'eloquent.write';
    }

    public function description(): string
    {
        $resources = implode(', ', array_keys($this->models())) ?: 'none configured';

        return "Create or update the user's own records. Available resources: {$resources}. ".
            'Pass values as a JSON object of column => value.';
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::WRITE;
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('resource', 'Which resource to write'),
            ParameterSpec::enum('operation', 'create or update', ['create', 'update']),
            ParameterSpec::number('id', 'Record id (required for update)', required: false),
            ParameterSpec::string('values', 'JSON object of column => value'),
        ];
    }

    public function authorize(ToolContext $context): bool
    {
        return (bool) config('agents.eloquent.allow_writes', false) && $context->user !== null;
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $resource = (string) ($arguments['resource'] ?? '');
        $config = $this->models()[$resource] ?? null;

        if ($config === null) {
            return "Resource '{$resource}' is not available.";
        }

        $writable = $config['writable'] ?? [];

        if ($writable === []) {
            return "Resource '{$resource}' is not writable.";
        }

        $values = $this->decodeValues($arguments['values'] ?? '');

        if ($values === null) {
            return 'values must be a JSON object.';
        }

        $values = array_intersect_key($values, array_flip($writable));

        if ($values === []) {
            return 'No writable columns were provided.';
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $ownerKey = $config['owner_key'] ?? null;
        $operation = $arguments['operation'] ?? 'create';

        if ($operation === 'create') {
            if ($ownerKey !== null) {
                $values[$ownerKey] = $context->userId();
            }

            $model = $modelClass::create($values);

            return ['resource' => $resource, 'operation' => 'create', 'id' => $model->getKey()];
        }

        $id = $arguments['id'] ?? null;

        if ($id === null) {
            return 'id is required for update.';
        }

        $query = $modelClass::query()->whereKey($id);

        if ($ownerKey !== null) {
            $query->where($ownerKey, $context->userId());
        }

        $model = $query->first();

        if ($model === null) {
            return 'Record not found.';
        }

        $model->fill($values)->save();

        return ['resource' => $resource, 'operation' => 'update', 'id' => $model->getKey()];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function decodeValues(mixed $values): ?array
    {
        if (is_array($values)) {
            return $values;
        }

        $decoded = json_decode((string) $values, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function models(): array
    {
        return (array) config('agents.eloquent.models', []);
    }
}
