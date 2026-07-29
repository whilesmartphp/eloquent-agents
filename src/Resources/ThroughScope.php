<?php

namespace Whilesmart\Agents\Resources;

/**
 * Scoping borrowed from a parent resource, for models that carry no owner
 * column of their own (a refund belongs to whoever owns its transaction).
 *
 * The same declaration serves both consumers: read tools constrain the query
 * through the relation, and an exported schema turns it into the query layer's
 * equivalent restriction.
 */
final readonly class ThroughScope
{
    /**
     * @param  string  $relation  Relation on this model reaching the parent.
     * @param  string  $resource  Name of the parent resource, which supplies the real scoping.
     * @param  string  $column  Local foreign-key column linking to the parent.
     * @param  string  $references  "<table>.<column>" the local column points at.
     */
    public function __construct(
        public string $relation,
        public string $resource,
        public string $column,
        public string $references,
    ) {}
}
