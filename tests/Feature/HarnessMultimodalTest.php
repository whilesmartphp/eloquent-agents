<?php

namespace Tests\Feature;

use Prism\Prism\Facades\Prism;
use Prism\Prism\Testing\TextResponseFake;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Usage;
use Tests\Fixtures\ArrayToolResolver;
use Tests\TestCase;
use Whilesmart\Agents\Harness\AbstractHarness;
use Whilesmart\Agents\ValueObjects\ToolContext;

class HarnessMultimodalTest extends TestCase
{
    public function test_run_accepts_images_and_per_run_overrides(): void
    {
        Prism::fake([
            TextResponseFake::make()
                ->withText('described')
                ->withUsage(new Usage(5, 3)),
        ]);

        $harness = $this->harness();

        $result = $harness->run(
            'describe this',
            ToolContext::guest(),
            [Image::fromUrl('https://example.com/logo.png')],
            ['provider' => 'anthropic', 'model' => 'claude-sonnet-4-6', 'maxTokens' => 1234],
        );

        $this->assertTrue($result->ok);
        $this->assertSame('described', $result->text);
    }

    public function test_run_stays_text_only_when_no_images(): void
    {
        Prism::fake([
            TextResponseFake::make()->withText('ok')->withUsage(new Usage(1, 1)),
        ]);

        $result = $this->harness()->run('hi', ToolContext::guest());

        $this->assertTrue($result->ok);
        $this->assertSame('ok', $result->text);
    }

    private function harness(): AbstractHarness
    {
        return new class(new ArrayToolResolver) extends AbstractHarness
        {
            public function name(): string
            {
                return 'multimodal-test';
            }

            public function systemPrompt(): string
            {
                return 'You describe images.';
            }
        };
    }
}
