<?php

namespace Whilesmart\Agents\ValueObjects;

use Whilesmart\Agents\Enums\ParameterType;

/**
 * Declarative, SDK-agnostic description of one tool parameter. The engine's
 * adapter translates this into whatever schema the backend expects.
 */
final readonly class ParameterSpec
{
    /**
     * @param  array<int, string|int|float>  $options
     */
    public function __construct(
        public string $name,
        public ParameterType $type,
        public string $description,
        public bool $required = true,
        public array $options = [],
        public ParameterType $itemType = ParameterType::STRING,
    ) {}

    public static function string(string $name, string $description, bool $required = true): self
    {
        return new self($name, ParameterType::STRING, $description, $required);
    }

    public static function number(string $name, string $description, bool $required = true): self
    {
        return new self($name, ParameterType::NUMBER, $description, $required);
    }

    public static function boolean(string $name, string $description, bool $required = true): self
    {
        return new self($name, ParameterType::BOOLEAN, $description, $required);
    }

    /**
     * @param  array<int, string|int|float>  $options
     */
    public static function enum(string $name, string $description, array $options, bool $required = true): self
    {
        return new self($name, ParameterType::ENUM, $description, $required, $options);
    }

    public static function arrayOf(string $name, string $description, ParameterType $itemType = ParameterType::STRING, bool $required = true): self
    {
        return new self($name, ParameterType::ARRAY, $description, $required, [], $itemType);
    }
}
