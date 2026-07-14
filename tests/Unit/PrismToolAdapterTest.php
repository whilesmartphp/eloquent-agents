<?php

namespace Tests\Unit;

use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Engines\Prism\PrismToolAdapter;
use Whilesmart\Agents\Enums\ParameterType;
use Whilesmart\Agents\Tools\AbstractTool;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
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

    /**
     * @param  array<int, ParameterSpec>  $parameters
     */
    private function toolWithParameters(array $parameters): AbstractTool
    {
        return new class($parameters) extends AbstractTool
        {
            /**
             * @param  array<int, ParameterSpec>  $specs
             */
            public function __construct(private array $specs) {}

            public function name(): string
            {
                return 'spec';
            }

            public function description(): string
            {
                return 'declares parameters';
            }

            public function parameters(): array
            {
                return $this->specs;
            }

            public function handle(array $arguments, ToolContext $context): string|array
            {
                return $arguments;
            }
        };
    }

    public function test_array_of_object_maps_to_an_object_items_schema(): void
    {
        $tool = $this->toolWithParameters([
            ParameterSpec::arrayOfObject('assignments', 'One entry per transaction.', [
                ParameterSpec::number('transaction_id', 'The transaction id.'),
                ParameterSpec::string('category_name', 'Category to apply.'),
                ParameterSpec::string('note', 'Optional note.', required: false),
            ]),
        ]);

        $items = $this->adapter()->adapt($tool, ToolContext::guest())
            ->parametersAsArray()['assignments']['items'];

        $this->assertSame('object', $items['type']);
        $this->assertSame('number', $items['properties']['transaction_id']['type']);
        $this->assertSame('string', $items['properties']['category_name']['type']);
        // Only specs marked required become required fields of the item.
        $this->assertSame(['transaction_id', 'category_name'], $items['required']);
    }

    public function test_object_parameter_maps_to_an_object_schema(): void
    {
        $tool = $this->toolWithParameters([
            ParameterSpec::object('range', 'A date range.', [
                ParameterSpec::string('from', 'Start date.'),
                ParameterSpec::string('to', 'End date.'),
            ]),
        ]);

        $range = $this->adapter()->adapt($tool, ToolContext::guest())
            ->parametersAsArray()['range'];

        $this->assertSame('object', $range['type']);
        $this->assertSame(['from', 'to'], $range['required']);
    }

    public function test_nested_specs_survive_inside_an_object(): void
    {
        $tool = $this->toolWithParameters([
            ParameterSpec::object('filter', 'A filter.', [
                ParameterSpec::enum('type', 'Kind.', ['income', 'expense']),
                ParameterSpec::arrayOf('ids', 'Wallet ids.', ParameterType::NUMBER),
            ]),
        ]);

        $filter = $this->adapter()->adapt($tool, ToolContext::guest())
            ->parametersAsArray()['filter'];

        $this->assertSame(['income', 'expense'], $filter['properties']['type']['enum']);
        $this->assertSame('array', $filter['properties']['ids']['type']);
        $this->assertSame('number', $filter['properties']['ids']['items']['type']);
    }

    public function test_scalar_array_items_are_unchanged(): void
    {
        $tool = $this->toolWithParameters([
            ParameterSpec::arrayOf('names', 'Category names.'),
        ]);

        $items = $this->adapter()->adapt($tool, ToolContext::guest())
            ->parametersAsArray()['names']['items'];

        $this->assertSame('string', $items['type']);
    }
}
