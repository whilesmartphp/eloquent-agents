<?php

namespace Whilesmart\Agents\ValueObjects;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The execution context handed to every tool call. Carries the acting user and
 * arbitrary scope, and is the spine of authorization: tools scope their work to
 * this user rather than trusting model-supplied identifiers.
 */
final readonly class ToolContext
{
    /**
     * @param  array<string, mixed>  $scope
     */
    public function __construct(
        public ?Authenticatable $user = null,
        public ?string $locale = null,
        public array $scope = [],
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     */
    public static function forUser(Authenticatable $user, ?string $locale = null, array $scope = []): self
    {
        return new self($user, $locale, $scope);
    }

    public static function guest(?string $locale = null): self
    {
        return new self(null, $locale);
    }

    public function userId(): int|string|null
    {
        return $this->user?->getAuthIdentifier();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->scope[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->scope);
    }
}
