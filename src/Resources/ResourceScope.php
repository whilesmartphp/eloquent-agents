<?php

namespace Whilesmart\Agents\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Whilesmart\Agents\Registries\ModelResourceRegistry;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Turns a resource's ownership declaration into a query restricted to the
 * acting user.
 *
 * Every path fails closed: an incomplete declaration, a missing user, a parent
 * resource that was never registered, or a chain that loops all yield null
 * rather than an unscoped query.
 */
class ResourceScope
{
    /** Depth cap on through-chains; also breaks cycles. */
    protected const MAX_DEPTH = 5;

    public function __construct(protected ModelResourceRegistry $registry) {}

    /**
     * Base query for a resource, scoped to the caller, or null if it cannot be.
     */
    public function query(AgentResource $resource, ToolContext $context): ?Builder
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $resource->model;

        if ($resource->global) {
            return $modelClass::query();
        }

        if ($context->user === null) {
            return null;
        }

        if ($resource->ownerRelation !== null) {
            $relation = $resource->ownerRelation;

            return $context->user->{$relation}()->getQuery();
        }

        $query = $modelClass::query();

        return $this->apply($query, $resource, $context) ? $query : null;
    }

    /**
     * Constrain an existing query to the caller's rows. Returns false if the
     * resource offers no way to do so.
     */
    public function apply(Builder $query, AgentResource $resource, ToolContext $context, int $depth = 0): bool
    {
        if ($resource->global) {
            return true;
        }

        if ($context->user === null || $depth >= self::MAX_DEPTH) {
            return false;
        }

        if ($resource->ownerKey !== null) {
            $query->where($resource->ownerKey, $context->userId());

            foreach ($resource->ownerConstants as $column => $value) {
                $query->where($column, $value);
            }

            return true;
        }

        if ($resource->scopeThrough !== null) {
            return $this->applyThrough($query, $resource->scopeThrough, $context, $depth);
        }

        return false;
    }

    protected function applyThrough(Builder $query, ThroughScope $through, ToolContext $context, int $depth): bool
    {
        $parent = $this->registry->find($through->resource);

        if ($parent === null) {
            return false;
        }

        $scoped = false;

        // whereHas runs its callback while building the query, so the flag is
        // set by the time this returns.
        $query->whereHas($through->relation, function (Builder $parentQuery) use ($parent, $context, $depth, &$scoped): void {
            $scoped = $this->apply($parentQuery, $parent, $context, $depth + 1);
        });

        return $scoped;
    }
}
