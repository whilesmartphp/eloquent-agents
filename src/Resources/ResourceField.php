<?php

namespace Whilesmart\Agents\Resources;

use Whilesmart\Agents\Enums\ParameterType;
use Whilesmart\Agents\ValueObjects\ParameterSpec;

/**
 * One field of a model as the agent sees it. The same declaration drives three
 * consumers: what a read tool returns, what a write tool accepts, and what a
 * generated semantic-layer schema describes.
 */
final readonly class ResourceField
{
    /**
     * @param  array<int, string|int|float>  $options  Allowed values, for enum-like fields.
     * @param  array<int, mixed>|string  $rules  Laravel validation rules for writes.
     * @param  string|null  $dataType  Semantic-layer type; defaults from $type when omitted.
     */
    public function __construct(
        public string $name,
        public ParameterType $type,
        public string $description = '',
        public bool $required = false,
        public bool $hidden = false,
        public array $options = [],
        public array|string $rules = [],
        public ?string $references = null,
        public bool $primary = false,
        public ?string $dataType = null,
    ) {}

    public static function string(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::STRING, $description, $required, $hidden, [], $rules);
    }

    public static function text(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::STRING, $description, $required, $hidden, [], $rules, dataType: 'text');
    }

    public static function integer(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::NUMBER, $description, $required, $hidden, [], $rules, dataType: 'integer');
    }

    public static function decimal(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::NUMBER, $description, $required, $hidden, [], $rules, dataType: 'decimal');
    }

    public static function number(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::NUMBER, $description, $required, $hidden, [], $rules);
    }

    public static function boolean(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::BOOLEAN, $description, $required, $hidden, [], $rules);
    }

    public static function datetime(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::STRING, $description, $required, $hidden, [], $rules, dataType: 'datetime');
    }

    public static function date(string $name, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::STRING, $description, $required, $hidden, [], $rules, dataType: 'date');
    }

    /**
     * @param  array<int, string|int|float>  $options
     */
    public static function enum(string $name, array $options, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::ENUM, $description, $required, $hidden, $options, $rules);
    }

    public static function key(string $name = 'id', string $description = ''): self
    {
        return new self($name, ParameterType::NUMBER, $description, false, false, [], [], null, true, 'integer');
    }

    /**
     * A foreign key. ``$references`` is "<entity>.<column>" and is what lets a
     * generated schema resolve the target's human-readable label.
     */
    public static function reference(string $name, string $references, string $description = '', bool $required = false, array|string $rules = [], bool $hidden = false): self
    {
        return new self($name, ParameterType::NUMBER, $description, $required, $hidden, [], $rules, $references, false, 'integer');
    }

    /**
     * A field the agent may filter on but must never surface, such as the column
     * scoping rows to their owner.
     */
    public static function internal(string $name, string $dataType = 'integer', string $description = ''): self
    {
        return new self($name, ParameterType::NUMBER, $description, false, true, [], [], null, false, $dataType);
    }

    /**
     * Type name for the exported semantic layer.
     */
    public function dataType(): string
    {
        if ($this->dataType !== null) {
            return $this->dataType;
        }

        return match ($this->type) {
            ParameterType::NUMBER => 'decimal',
            ParameterType::BOOLEAN => 'boolean',
            ParameterType::ENUM => 'enum',
            default => 'string',
        };
    }

    public function toParameterSpec(): ParameterSpec
    {
        return new ParameterSpec(
            name: $this->name,
            type: $this->type,
            description: $this->description,
            required: $this->required,
            options: $this->options,
        );
    }
}
