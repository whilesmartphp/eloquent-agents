<?php

namespace Tests\Fixtures;

use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\Tools\AbstractTool;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

class EchoTool extends AbstractTool
{
    public function __construct(private readonly bool $authorized = true) {}

    public function name(): string
    {
        return 'echo';
    }

    public function description(): string
    {
        return 'Echo a message back to the caller.';
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::READ;
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('message', 'The message to echo'),
        ];
    }

    public function authorize(ToolContext $context): bool
    {
        return $this->authorized;
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        return 'echo: '.($arguments['message'] ?? '');
    }
}
