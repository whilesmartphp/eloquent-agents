<?php

namespace Whilesmart\Agents\Resources;

use Illuminate\Database\Eloquent\Model;

/**
 * One model, described once, for every agent-facing surface.
 *
 * A model that declares a resource gets a generated read tool, can be driven by
 * a generic write tool, and appears in an exported semantic-layer schema,
 * without those three drifting apart, which is what happens when each is
 * maintained by hand.
 */
final readonly class AgentResource
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<int, string>  $aliases  Other names a user might use for this resource.
     * @param  array<int, ResourceField>  $readable
     * @param  array<int, ResourceField>  $writable
     * @param  array<int, ResourceRelationship>  $relationships
     * @param  string|null  $ownerKey  Column scoping rows to their owner.
     * @param  string|null  $ownerParam  Identity key the owner column binds to; defaults to $ownerKey.
     * @param  string|null  $ownerRelation  Relation on the user model yielding this resource.
     * @param  array<string, string>  $ownerConstants  Extra columns pinned to fixed values (polymorphic owners).
     * @param  array<int, string>  $ownerBypassRoles  Roles that may read this resource unscoped.
     * @param  ThroughScope|null  $scopeThrough  Scoping borrowed from a parent resource.
     * @param  bool  $global  Rows belong to nobody and are readable by everyone.
     * @param  bool  $readTool  Generate a list tool. Set false when a hand-written tool already covers reads;
     *                          the resource still scopes children and still appears in an exported schema.
     */
    public function __construct(
        public string $name,
        public string $model,
        public string $table,
        public string $description = '',
        public array $aliases = [],
        public ?string $labelColumn = null,
        public ?string $ownerKey = 'user_id',
        public ?string $ownerParam = null,
        public ?string $ownerRelation = null,
        public array $ownerConstants = [],
        public array $ownerBypassRoles = [],
        public ?ThroughScope $scopeThrough = null,
        public bool $global = false,
        public array $readable = [],
        public array $writable = [],
        public array $relationships = [],
        public bool $writeEnabled = false,
        public ?string $orderColumn = null,
        public bool $readTool = true,
    ) {}

    /**
     * True when rows can be tied to a specific user. A resource that is neither
     * owned nor explicitly global is a configuration mistake, and read tools
     * refuse it rather than returning everyone's rows.
     */
    public function isOwned(): bool
    {
        return $this->ownerKey !== null
            || $this->ownerRelation !== null
            || $this->scopeThrough !== null;
    }

    /**
     * Identity key the owner column binds to.
     */
    public function ownerParam(): ?string
    {
        return $this->ownerParam ?? $this->ownerKey;
    }

    /**
     * Readable fields minus the internal ones, i.e. what may actually be shown.
     *
     * @return array<int, ResourceField>
     */
    public function visibleFields(): array
    {
        return array_values(array_filter($this->readable, fn (ResourceField $f): bool => ! $f->hidden));
    }

    /**
     * @return array<int, string>
     */
    public function visibleColumns(): array
    {
        return array_map(fn (ResourceField $f): string => $f->name, $this->visibleFields());
    }

    /**
     * Column result rows are ordered by, newest first. Falls back to the
     * timestamp when the model keeps them and the primary key when it does not.
     */
    public function orderColumn(): string
    {
        if ($this->orderColumn !== null) {
            return $this->orderColumn;
        }

        /** @var Model $instance */
        $instance = new $this->model;

        return $instance->usesTimestamps() ? $instance->getCreatedAtColumn() : $instance->getKeyName();
    }

    public function toolName(): string
    {
        return 'list_'.$this->name;
    }
}
