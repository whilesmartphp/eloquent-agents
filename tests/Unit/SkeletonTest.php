<?php

namespace Tests\Unit;

use Tests\TestCase;
use Whilesmart\Agents\AgentsServiceProvider;

class SkeletonTest extends TestCase
{
    public function test_config_is_loaded(): void
    {
        $this->assertSame('gemini', config('agents.provider'));
        $this->assertIsInt(config('agents.max_steps'));
    }

    public function test_service_provider_is_registered(): void
    {
        $this->assertTrue(
            $this->app->getLoadedProviders()[AgentsServiceProvider::class] ?? false
        );
    }
}
