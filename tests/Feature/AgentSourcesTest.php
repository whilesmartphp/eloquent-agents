<?php

namespace Tests\Feature;

use LogicException;
use Tests\Fixtures\EchoHarness;
use Tests\TestCase;
use Whilesmart\Agents\Contracts\AgentEngine;
use Whilesmart\Agents\Exceptions\AgentNotFoundException;
use Whilesmart\Agents\Exceptions\InvalidAgentDefinition;
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\Registries\AgentRegistry;
use Whilesmart\Agents\Sources\FileAgentSource;
use Whilesmart\Agents\ValueObjects\AgentRequest;
use Whilesmart\Agents\ValueObjects\AgentResult;
use Whilesmart\Agents\ValueObjects\ToolContext;

class AgentSourcesTest extends TestCase
{
    private string $configName = 'config-agent';

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('agents.harnesses', ['echo' => EchoHarness::class]);
        $app['config']->set('agents.agents', [
            $this->configName => [
                'type' => 'assistant',
                'label' => 'External assistant',
                'provider' => 'openai',
                'model' => 'gpt-test',
                'model_version' => '2026-11-01',
            ],
        ]);
        $app['config']->set('agents.paths', [__DIR__.'/../Fixtures/agents']);
    }

    public function test_file_and_config_agents_are_registered_separately_from_harnesses(): void
    {
        $this->assertEqualsCanonicalizing(['config-agent', 'invoice-reviewer', 'support-desk'], Agents::agents()->names());
        $this->assertSame(['echo'], Agents::harnesses()->names());
        $this->assertFalse(Agents::agents()->has('echo'));
        $this->assertTrue(Agents::agents()->has('support-desk'));
        $this->assertSame('External assistant', Agents::agent('config-agent')->label);
        $this->assertNull(Agents::agent('config-agent')->harness);
        $this->assertSame('support-desk', Agents::agent('support-desk')->name);
    }

    public function test_definitions_keep_host_keys_and_can_be_listed_by_type(): void
    {
        $agent = Agents::agent('invoice-reviewer');

        $this->assertSame('2026-11-01', $agent->modelVersion);
        $this->assertSame(['department' => 'billing'], $agent->attributes);
        $this->assertSame(['invoice-reviewer'], Agents::agents()->ofType('reviewer'));
        $this->assertEqualsCanonicalizing(['config-agent', 'support-desk'], Agents::agents()->ofType('assistant'));
    }

    public function test_an_agent_defined_in_config_and_a_file_is_rejected(): void
    {
        $this->configName = 'invoice-reviewer';
        $this->expectException(InvalidAgentDefinition::class);
        $this->expectExceptionMessage('Agent [invoice-reviewer] is defined by more than one source.');

        $this->refreshApplication();
    }

    public function test_an_agent_defined_in_two_files_is_rejected(): void
    {
        $this->expectException(InvalidAgentDefinition::class);
        $this->expectExceptionMessage('Agent [invoice-reviewer]');

        (new FileAgentSource([__DIR__.'/../Fixtures/agents', __DIR__.'/../Fixtures/agents-duplicate']))->definitions();
    }

    public function test_a_file_that_is_not_an_object_is_rejected_with_its_path(): void
    {
        $this->expectException(InvalidAgentDefinition::class);
        $this->expectExceptionMessage(__DIR__.'/../Fixtures/agents-invalid/broken.json');

        (new FileAgentSource([__DIR__.'/../Fixtures/agents-invalid']))->definitions();
    }

    public function test_an_invalid_definition_names_the_agent(): void
    {
        $this->expectException(InvalidAgentDefinition::class);
        $this->expectExceptionMessage('Invalid agent [broken]: type');

        Agents::agents()->register('broken', ['type' => []]);
    }

    public function test_missing_folders_define_no_agents(): void
    {
        $this->assertSame([], (new FileAgentSource([__DIR__.'/missing']))->definitions());
    }

    public function test_an_unknown_agent_is_rejected(): void
    {
        $this->expectException(AgentNotFoundException::class);
        $this->expectExceptionMessage('[missing]');

        Agents::agent('missing');
    }

    public function test_an_external_agent_cannot_run_locally(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Agent [config-agent] cannot run because it has no harness.');

        Agents::runAgent('config-agent', 'Hello', ToolContext::guest());
    }

    public function test_an_agent_runs_through_its_harness_with_its_provider_and_model(): void
    {
        $engine = new class implements AgentEngine
        {
            public AgentRequest $request;

            public function run(AgentRequest $request): AgentResult
            {
                $this->request = $request;

                return AgentResult::success($request->input);
            }
        };
        $this->app->instance(AgentEngine::class, $engine);

        $context = ToolContext::guest();
        $result = Agents::runAgent('invoice-reviewer', 'Check the invoice', $context);

        $this->assertSame('Check the invoice', $result->text);
        $this->assertSame('You echo messages.', $engine->request->systemPrompt);
        $this->assertSame($context, $engine->request->context);
        $this->assertSame('openai', $engine->request->provider);
        $this->assertSame('gpt-test', $engine->request->model);
    }

    public function test_only_the_type_is_required(): void
    {
        $registry = new AgentRegistry;
        $registry->register('minimal', ['type' => 'assistant']);
        $agent = $registry->get('minimal');

        $this->assertSame('minimal', $agent->label);
        $this->assertNull($agent->model);
        $this->expectException(InvalidAgentDefinition::class);
        $registry->register('untyped', ['label' => 'No type']);
    }
}
