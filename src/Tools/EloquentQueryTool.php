<?php

namespace Whilesmart\Agents\Tools;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Read application data through a strict allowlist. Nothing is queryable unless
 * declared in config('agents.eloquent.models'); rows are scoped to the acting
 * user via the configured owner key, and only readable columns are returned.
 */
class EloquentQueryTool extends AbstractTool
{
    public function name(): string
    {
        return 'eloquent.query';
    }

    public function description(): string
    {
        $resources = implode(', ', array_keys($this->models())) ?: 'none configured';

        return "Query the user's own records. Available resources: {$resources}. ".
            'Use select=count|sum|avg|min|max with a column for aggregates, or list to return rows.';
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('resource', 'Which resource to query'),
            ParameterSpec::enum('select', 'How to read the data', ['list', 'count', 'sum', 'avg', 'min', 'max'], required: false),
            ParameterSpec::string('column', 'Column to aggregate (required for sum/avg/min/max)', required: false),
            ParameterSpec::number('limit', 'Maximum rows to return for select=list', required: false),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $resource = (string) ($arguments['resource'] ?? '');
        $config = $this->models()[$resource] ?? null;

        if ($config === null) {
            return "Resource '{$resource}' is not available.";
        }

        $ownerKey = $config['owner_key'] ?? null;

        if ($ownerKey !== null && $context->userId() === null) {
            return 'No authenticated user to scope this query to.';
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();

        if ($ownerKey !== null) {
            $query->where($ownerKey, $context->userId());
        }

        $select = $arguments['select'] ?? 'list';

        if ($select === 'count') {
            return ['resource' => $resource, 'count' => $query->count()];
        }

        if (in_array($select, ['sum', 'avg', 'min', 'max'], true)) {
            $column = (string) ($arguments['column'] ?? '');

            if (! in_array($column, $config['readable'] ?? [], true)) {
                return "Column '{$column}' is not readable on '{$resource}'.";
            }

            return [
                'resource' => $resource,
                'select' => $select,
                'column' => $column,
                'value' => $query->{$select}($column),
            ];
        }

        $maxRows = (int) config('agents.eloquent.max_rows', 50);
        $limit = isset($arguments['limit']) ? min((int) $arguments['limit'], $maxRows) : $maxRows;
        $readable = $config['readable'] ?? ['*'];

        return [
            'resource' => $resource,
            'rows' => $query->limit($limit)->get($readable)->toArray(),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function models(): array
    {
        return (array) config('agents.eloquent.models', []);
    }
}
