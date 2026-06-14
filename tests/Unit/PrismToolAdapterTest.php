<?php

namespace Tests\Unit;

use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Engines\Prism\PrismToolAdapter;
use Whilesmart\Agents\Tools\AbstractTool;
use Whilesmart\Agents\ValueObjects\ToolContext;

class PrismToolAdapterTest extends TestCase
{
    private function adapter(): PrismToolAdapter
    {
        return new PrismToolAdapter;
    }

    public function test_maps_parameters_onto_prism_tool(): void
    {
        $prismTool = $this->adapter()->adapt(new EchoTool, ToolContext::guest());

        $this->assertSame('echo', $prismTool->name());
        $this->assertArrayHasKey('message', $prismTool->parametersAsArray());
        $this->assertContains('message', $prismTool->requiredParameters());
    }

    public function test_invoking_prism_tool_runs_handle(): void
    {
        $prismTool = $this->adapter()->adapt(new EchoTool, ToolContext::guest());

        $this->assertSame('echo: hi', $prismTool->handle(message: 'hi'));
    }

    public function test_denied_when_authorize_fails(): void
    {
        $prismTool = $this->adapter()->adapt(new EchoTool(authorized: false), ToolContext::guest());

        $this->assertSame(
            'This action is not permitted for the current user.',
            $prismTool->handle(message: 'hi'),
        );
    }

    public function test_array_results_are_json_encoded(): void
    {
        $tool = new class extends AbstractTool
        {
            public function name(): string
            {
                return 'json';
            }

            public function description(): string
            {
                return 'returns json';
            }

            public function handle(array $arguments, ToolContext $context): string|array
            {
                return ['a' => 1, 'b' => 2];
            }
        };

        $this->assertSame('{"a":1,"b":2}', $this->adapter()->adapt($tool, ToolContext::guest())->handle());
    }
}
