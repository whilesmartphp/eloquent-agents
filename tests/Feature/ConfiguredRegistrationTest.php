<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\ValueObjects\ToolContext;

class ConfiguredRegistrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('agents.tools', [EchoTool::class]);
        $app['config']->set('agents.harnesses', [
            'support' => [
                'prompt' => 'You are support.',
                'tools' => ['echo'],
            ],
        ]);
        $app['config']->set('agents.prompts', [
            'support-prompt' => 'Overridden prompt text.',
        ]);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('agents.mcp.servers', [
            'planning' => [
                'url' => 'http://engine.test/mcp/planning/',
                'headers' => ['Authorization' => 'Bearer your-token-here'],
                'bind' => ['plan' => 'plan'],
                'only' => ['tell'],
                'permission' => 'write',
            ],
        ]);
    }

    public function test_config_mcp_servers_offer_their_tools(): void
    {
        $fixtures = __DIR__.'/../Fixtures/mcp/';
        Http::fake(fn (Request $request) => Http::response(
            file_get_contents($fixtures.match (json_decode($request->body(), true)['method']) {
                'initialize' => 'initialize.json',
                'tools/list' => 'tools-list.json',
                'tools/call' => 'tools-call-tell.json',
            }),
            200,
            ['Content-Type' => 'application/json'],
        ));

        $this->assertTrue(Agents::tools()->has('tell'));
        $this->assertFalse(Agents::tools()->has('describe_plan'));

        $tell = Agents::tools()->resolve('tell');
        $this->assertSame(ToolPermission::WRITE, $tell->permission());
        $this->assertSame(['turn' => 1], $tell->handle(['text' => 'hi'], new ToolContext(scope: ['plan' => 'plan-a320261f4451'])));

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer your-token-here')
            && (json_decode($request->body(), true)['params']['arguments']['plan'] ?? null) === 'plan-a320261f4451');
    }

    public function test_config_tools_are_registered_on_boot(): void
    {
        $this->assertTrue(Agents::tools()->has('echo'));
    }

    public function test_config_harnesses_are_registered_on_boot(): void
    {
        $this->assertContains('support', Agents::harnesses()->names());
        $this->assertSame('You are support.', Agents::harness('support')->systemPrompt());
    }

    public function test_prompt_override_resolves_by_name(): void
    {
        Agents::registerHarness('named', ['prompt' => 'support-prompt']);

        $this->assertSame('Overridden prompt text.', Agents::harness('named')->systemPrompt());
    }
}
