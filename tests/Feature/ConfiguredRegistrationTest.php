<?php

namespace Tests\Feature;

use Tests\Fixtures\EchoTool;
use Tests\TestCase;
use Whilesmart\Agents\Facades\Agents;

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
