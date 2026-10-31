<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Whilesmart\Agents\Engines\Prism\PrismToolAdapter;
use Whilesmart\Agents\Enums\ParameterType;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\Mcp\McpServer;
use Whilesmart\Agents\Mcp\RemoteMcpTool;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

class RemoteMcpServerTest extends TestCase
{
    private const URL = 'http://engine.test/mcp/planning/';

    private const SESSION = '2b32aed951794af6812734a9bea2096a';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('cache.default', 'array');
    }

    private function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/../Fixtures/mcp/'.$name);
    }

    /**
     * @param  array<string, string>  $calls  Tool name to the fixture answering tools/call.
     */
    private function fakeEngine(array $calls = []): void
    {
        Http::fake(function (Request $request) use ($calls) {
            $message = $this->wire($request);

            return match ($message['method']) {
                'initialize' => Http::response($this->fixture('initialize.json'), 200, ['Content-Type' => 'application/json']),
                'tools/list' => Http::response($this->fixture('tools-list.json'), 200, ['Content-Type' => 'application/json']),
                'tools/call' => Http::response($this->fixture($calls[$message['params']['name']]), 200, ['Content-Type' => 'application/json']),
            };
        });
    }

    private function registerPlanning(): void
    {
        Agents::registerMcpServer(
            'planning',
            self::URL,
            headers: fn (ToolContext $context) => ['X-Workspace' => (string) $context->get('workspace', 'none')],
            bind: ['plan' => fn (ToolContext $context) => $context->get('plan')],
            only: ['describe_plan', 'tell', 'set_script'],
        );
    }

    private function tool(string $name): RemoteMcpTool
    {
        $tool = Agents::tools()->resolve($name);
        $this->assertInstanceOf(RemoteMcpTool::class, $tool);

        return $tool;
    }

    private function context(): ToolContext
    {
        return new ToolContext(scope: ['plan' => 'plan-a320261f4451', 'workspace' => 'studio']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sentMessages(): array
    {
        return Http::recorded()->map(fn (array $pair) => $this->wire($pair[0]))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function wire(Request $request): array
    {
        return json_decode($request->body(), true);
    }

    public function test_remote_tools_are_discovered_on_first_use(): void
    {
        $this->fakeEngine();
        $this->registerPlanning();

        Http::assertNothingSent();

        $this->assertTrue(Agents::tools()->has('tell'));
        $this->assertTrue(Agents::tools()->has('describe_plan'));
        $this->assertFalse(Agents::tools()->has('make_film'));
        $this->assertSame(ToolPermission::EXTERNAL, $this->tool('tell')->permission());
        $this->assertStringStartsWith('Passes on what the person said', $this->tool('tell')->description());

        $this->assertSame(['initialize', 'tools/list'], array_column($this->sentMessages(), 'method'));
        Http::assertSent(fn (Request $request) => $request->url() === self::URL
            && $request->hasHeader('Accept', 'application/json, text/event-stream')
            && $request->hasHeader('Content-Type', 'application/json')
            && $request['jsonrpc'] === '2.0');
    }

    public function test_discovery_is_cached_per_server(): void
    {
        $this->fakeEngine();

        (new McpServer('planning', self::URL))->tools();
        $again = (new McpServer('planning', self::URL))->tools();

        $this->assertCount(41, $again);
        $this->assertSame(['initialize', 'tools/list'], array_column($this->sentMessages(), 'method'));
    }

    public function test_bound_arguments_are_hidden_from_the_model(): void
    {
        $this->fakeEngine();
        $this->registerPlanning();

        $parameters = $this->tool('tell')->parameters();
        $names = array_map(fn (ParameterSpec $p) => $p->name, $parameters);

        $this->assertSame(['text', 'kind', 'question'], $names);
        $this->assertSame(ParameterType::SCHEMA, $parameters[0]->type);
        $this->assertTrue($parameters[0]->required);
        $this->assertFalse($parameters[1]->required);

        $prismTool = (new PrismToolAdapter)->adapt($this->tool('tell'), $this->context());
        $this->assertArrayNotHasKey('plan', $prismTool->parametersAsArray());
        $this->assertSame(['text'], $prismTool->requiredParameters());
        $this->assertSame(
            ['anyOf' => [['type' => 'string'], ['type' => 'null']], 'default' => null, 'title' => 'Question'],
            $prismTool->parametersAsArray()['question'],
        );
    }

    public function test_local_references_are_inlined_into_each_parameter(): void
    {
        $this->fakeEngine();
        $this->registerPlanning();

        $lines = collect($this->tool('set_script')->parameters())->firstWhere('name', 'lines');
        $items = $lines->schema['anyOf'][0]['items'];

        $this->assertArrayNotHasKey('$ref', $items);
        $this->assertSame('object', $items['type']);
        $this->assertSame(['text'], $items['required']);
        $this->assertSame(['said', 'shown', 'direction'], $items['properties']['kind']['enum']);
    }

    public function test_a_call_fills_bound_arguments_and_returns_structured_content(): void
    {
        $this->fakeEngine(['tell' => 'tools-call-tell.json']);
        $this->registerPlanning();

        $prismTool = (new PrismToolAdapter)->adapt($this->tool('tell'), $this->context());
        $answer = $prismTool->handle(text: 'A launch film', plan: 'someone-elses-plan');

        $this->assertSame('{"turn":1}', $answer);

        $call = collect($this->sentMessages())->firstWhere('method', 'tools/call');
        $this->assertSame(
            ['name' => 'tell', 'arguments' => ['text' => 'A launch film', 'plan' => 'plan-a320261f4451']],
            $call['params'],
        );
        Http::assertSent(fn (Request $request) => $request['method'] === 'tools/call'
            && $request->hasHeader('X-Workspace', 'studio'));
    }

    public function test_structured_content_wins_over_the_text(): void
    {
        $this->fakeEngine(['describe_plan' => 'tools-call-describe-plan.json']);
        $this->registerPlanning();

        $result = $this->tool('describe_plan')->handle([], $this->context());

        $this->assertIsArray($result);
        $this->assertSame(['summary', 'changed', 'data'], array_keys($result));
    }

    public function test_a_tool_error_becomes_the_error_the_agent_sees(): void
    {
        $this->fakeEngine(['describe_plan' => 'tools-call-error.json']);
        $this->registerPlanning();

        $result = $this->tool('describe_plan')->handle([], $this->context());

        $this->assertSame(['error' => "Error executing tool describe_plan: 'plan-does-not-exist'"], $result);
    }

    public function test_a_json_rpc_error_becomes_the_error_the_agent_sees(): void
    {
        $this->fakeEngine(['tell' => 'rpc-error.json']);
        $this->registerPlanning();

        $result = $this->tool('tell')->handle(['text' => 'hi'], $this->context());

        $this->assertSame(['error' => 'MCP tools/call failed: Invalid request parameters'], $result);
    }

    public function test_an_event_stream_reply_is_read_and_the_session_id_is_echoed(): void
    {
        Http::fake(function (Request $request) {
            $sse = ['Content-Type' => 'text/event-stream', 'mcp-session-id' => self::SESSION];

            return match ($request['method']) {
                'initialize' => Http::response($this->fixture('sse-initialize.txt'), 200, $sse),
                'notifications/initialized' => Http::response('', 202, ['Content-Type' => 'application/json', 'mcp-session-id' => self::SESSION]),
                'tools/list' => Http::response($this->fixture('tools-list.json'), 200, ['Content-Type' => 'application/json', 'mcp-session-id' => self::SESSION]),
                'tools/call' => Http::response($this->fixture('sse-tools-call.txt'), 200, $sse),
            };
        });
        $this->registerPlanning();

        $result = $this->tool('tell')->handle(['text' => 'hi'], $this->context());

        $this->assertSame("{\n  \"turn\": 1\n}", $result);

        $sent = Http::recorded()->map(fn (array $pair) => $pair[0]);
        $this->assertSame(
            ['initialize', 'notifications/initialized', 'tools/list', 'initialize', 'notifications/initialized', 'tools/call'],
            $sent->map(fn (Request $request) => $request['method'])->all(),
        );
        $this->assertFalse($sent[0]->hasHeader('mcp-session-id'));
        $this->assertTrue($sent[5]->hasHeader('mcp-session-id', self::SESSION));
        $this->assertTrue($sent[1]->hasHeader('mcp-session-id', self::SESSION));
    }

    public function test_an_unreachable_server_leaves_its_tools_out(): void
    {
        Http::fake(fn () => Http::response('', 503));
        $this->registerPlanning();

        $this->assertFalse(Agents::tools()->has('tell'));
        $this->assertContains('clock', Agents::tools()->names());
    }
}
