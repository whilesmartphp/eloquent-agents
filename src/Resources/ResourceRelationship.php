<?php

namespace Whilesmart\Agents\Resources;

/**
 * How one resource connects to another, for the exported semantic layer. Read
 * tools do not traverse these; they exist so a generated schema can describe
 * joins the query layer is allowed to make.
 */
final readonly class ResourceRelationship
{
    public const ONE_TO_ONE = 'one_to_one';

    public const ONE_TO_MANY = 'one_to_many';

    public const MANY_TO_ONE = 'many_to_one';

    public const MANY_TO_MANY = 'many_to_many';

    public function __construct(
        public string $name,
        public string $type,
        public string $to,
        public ?string $foreignKey = null,
        public ?string $pivotTable = null,
        public ?string $pivotFrom = null,
        public ?string $pivotTo = null,
        public string $description = '',
    ) {}

    public static function belongsTo(string $name, string $to, string $foreignKey, string $description = ''): self
    {
        return new self($name, self::MANY_TO_ONE, $to, $foreignKey, description: $description);
    }

    public static function hasMany(string $name, string $to, string $foreignKey, string $description = ''): self
    {
        return new self($name, self::ONE_TO_MANY, $to, $foreignKey, description: $description);
    }

    public static function belongsToMany(string $name, string $to, string $pivotTable, string $pivotFrom, string $pivotTo, string $description = ''): self
    {
        return new self($name, self::MANY_TO_MANY, $to, null, $pivotTable, $pivotFrom, $pivotTo, $description);
    }
}
