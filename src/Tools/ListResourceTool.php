<?php

namespace Whilesmart\Agents\Tools;

use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceScope;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Read tool generated from an AgentResource: one per registered model, named
 * list_<resource>.
 *
 * Rows are scoped to the acting user by the resource's owner key or owner
 * relation, never by anything the model passes in, and only fields the resource
 * marks visible are returned.
 */
class ListResourceTool extends AbstractTool
{
    public function __construct(
        protected AgentResource $resource,
        protected ResourceScope $scope,
    ) {}

    public function resource(): AgentResource
    {
        return $this->resource;
    }

    public function name(): string
    {
        return $this->resource->toolName();
    }

    public function description(): string
    {
        $description = $this->resource->description !== ''
            ? $this->resource->description
            : "List {$this->resource->name}.";

        $columns = implode(', ', $this->resource->visibleColumns());

        return $columns === '' ? $description : "{$description} Returns: {$columns}.";
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::number('limit', 'Maximum number of records to return', required: false),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $query = $this->scope->query($this->resource, $context);

        if ($query === null) {
            return 'No authenticated user to scope this request to.';
        }

        $columns = $this->resource->visibleColumns();

        $rows = $query
            ->orderByDesc($this->resource->orderColumn())
            ->limit($this->limit($arguments))
            ->get($columns === [] ? ['*'] : $columns);

        return [
            'resource' => $this->resource->name,
            'count' => $rows->count(),
            'rows' => $rows->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function limit(array $arguments): int
    {
        $max = (int) config('agents.resources.max_rows', config('agents.eloquent.max_rows', 50));

        if (! isset($arguments['limit'])) {
            return $max;
        }

        return max(1, min((int) $arguments['limit'], $max));
    }
}
