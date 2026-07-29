<?php

namespace Whilesmart\Agents\Console;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Whilesmart\Agents\Registries\ModelResourceRegistry;
use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceField;
use Whilesmart\Agents\Resources\ResourceRelationship;

/**
 * Emit a semantic-layer schema derived from the registered model resources, so
 * the natural-language query layer and the agent's tools describe the same
 * models rather than drifting apart in two hand-maintained files.
 *
 * The output is a fragment: entities, relationships and the security rules that
 * follow from ownership. Connection details, prompts and examples stay in the
 * target file and are meant to be merged, not overwritten.
 */
class ExportSchemaCommand extends Command
{
    protected $signature = 'agents:export-schema
        {--format=smartql : Output format}
        {--output= : Write to this file instead of stdout}';

    protected $description = 'Export a semantic-layer schema from the registered agent resources';

    public function handle(ModelResourceRegistry $registry): int
    {
        $format = (string) $this->option('format');

        if ($format !== 'smartql') {
            $this->error("Unsupported format '{$format}'. Supported: smartql.");

            return self::FAILURE;
        }

        $resources = $registry->all();

        if ($resources === []) {
            $this->warn('No agent resources registered. Add models to config(\'agents.resources\').');

            return self::SUCCESS;
        }

        $yaml = Yaml::dump($this->schema($resources), 6, 2);

        if ($output = $this->option('output')) {
            file_put_contents($output, $yaml);
            $this->info("Wrote {$output}.");

            return self::SUCCESS;
        }

        $this->line($yaml);

        return self::SUCCESS;
    }

    /**
     * @param  array<string, AgentResource>  $resources
     * @return array<string, mixed>
     */
    protected function schema(array $resources): array
    {
        $entities = [];
        $relationships = [];

        foreach ($resources as $resource) {
            $entities[$resource->name] = $this->entity($resource);

            foreach ($resource->relationships as $relationship) {
                $relationships[] = $this->relationship($resource, $relationship);
            }
        }

        return [
            'semantic_layer' => [
                'entities' => $entities,
                'relationships' => $relationships,
            ],
            'security' => [
                'allowed_tables' => $this->allowedTables($resources),
                'required_filters' => $this->requiredFilters($resources),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function entity(AgentResource $resource): array
    {
        $entity = ['table' => $resource->table];

        if ($resource->description !== '') {
            $entity['description'] = $resource->description;
        }

        if ($resource->aliases !== []) {
            $entity['aliases'] = $resource->aliases;
        }

        if ($resource->labelColumn !== null) {
            $entity['label_column'] = $resource->labelColumn;
        }

        $entity['columns'] = [];

        foreach ($resource->readable as $field) {
            $entity['columns'][$field->name] = $this->column($field);
        }

        return $entity;
    }

    /**
     * @return array<string, mixed>
     */
    protected function column(ResourceField $field): array
    {
        $column = ['type' => $field->dataType()];

        if ($field->description !== '') {
            $column['description'] = $field->description;
        }

        if ($field->primary) {
            $column['primary'] = true;
        }

        if ($field->hidden) {
            $column['hidden'] = true;
        }

        if ($field->options !== []) {
            $column['values'] = $field->options;
        }

        if ($field->references !== null) {
            $column['references'] = $field->references;
        }

        return $column;
    }

    /**
     * @return array<string, mixed>
     */
    protected function relationship(AgentResource $resource, ResourceRelationship $relationship): array
    {
        $entry = [
            'name' => $relationship->name,
            'type' => $relationship->type,
            'from' => $resource->name,
            'to' => $relationship->to,
        ];

        if ($relationship->foreignKey !== null) {
            $entry['foreign_key'] = $relationship->foreignKey;
        }

        if ($relationship->pivotTable !== null) {
            $entry['pivot_table'] = $relationship->pivotTable;
            $entry['pivot_from'] = $relationship->pivotFrom;
            $entry['pivot_to'] = $relationship->pivotTo;
        }

        if ($relationship->description !== '') {
            $entry['description'] = $relationship->description;
        }

        return $entry;
    }

    /**
     * @param  array<string, AgentResource>  $resources
     * @return array<int, string>
     */
    protected function allowedTables(array $resources): array
    {
        $tables = array_map(fn (AgentResource $r): string => $r->table, $resources);
        $tables = array_values(array_unique($tables));
        sort($tables);

        return $tables;
    }

    /**
     * Ownership, restated as query-layer scoping. A global resource contributes
     * nothing; everything else must produce a rule, and a resource that cannot
     * is reported rather than silently left open.
     *
     * @param  array<string, AgentResource>  $resources
     * @return array<string, array<string, mixed>>
     */
    protected function requiredFilters(array $resources): array
    {
        $filters = [];

        foreach ($resources as $resource) {
            if ($resource->global) {
                continue;
            }

            $filter = [];

            if ($resource->ownerKey !== null) {
                $filter['column'] = $resource->ownerKey;

                if ($resource->ownerParam() !== $resource->ownerKey) {
                    $filter['param'] = $resource->ownerParam();
                }
            }

            if ($resource->ownerConstants !== []) {
                $filter['constants'] = $resource->ownerConstants;
            }

            if ($resource->scopeThrough !== null) {
                $filter['through'] = [
                    'column' => $resource->scopeThrough->column,
                    'references' => $resource->scopeThrough->references,
                ];
            }

            if ($filter === []) {
                $this->warn("Resource '{$resource->name}' is neither global nor scoped; it has no required filter.");

                continue;
            }

            if ($resource->ownerBypassRoles !== []) {
                $filter['bypass_roles'] = $resource->ownerBypassRoles;
            }

            $filters[$resource->table] = $filter;
        }

        return $filters;
    }
}
