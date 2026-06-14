<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Whilesmart\Agents\Facades\Agents;
use Whilesmart\Agents\Tools\ClockTool;
use Whilesmart\Agents\Tools\HttpFetchTool;
use Whilesmart\Agents\Tools\StorageReadTool;
use Whilesmart\Agents\Tools\WebSearchTool;
use Whilesmart\Agents\ValueObjects\ToolContext;

class BuiltinToolsTest extends TestCase
{
    public function test_default_tools_are_registered(): void
    {
        foreach (['clock', 'calculator', 'http.fetch', 'web.search', 'storage.read'] as $name) {
            $this->assertTrue(Agents::tools()->has($name), "{$name} should be registered");
        }
    }

    public function test_clock_reports_current_date(): void
    {
        Carbon::setTestNow('2026-06-10 12:00:00');

        $result = (new ClockTool)->handle(['timezone' => 'UTC'], ToolContext::guest());

        $this->assertSame('2026-06-10', $result['date']);
        $this->assertSame('2026-05-01', $result['start_of_last_month']);
        $this->assertSame('2026-05-31', $result['end_of_last_month']);

        Carbon::setTestNow();
    }

    public function test_http_fetch_denies_unlisted_host(): void
    {
        config()->set('agents.http.allowed_hosts', ['example.com']);

        $result = (new HttpFetchTool)->handle(['url' => 'https://evil.test/x'], ToolContext::guest());

        $this->assertSame("Host 'evil.test' is not allowed.", $result);
    }

    public function test_http_fetch_allows_listed_host(): void
    {
        config()->set('agents.http.allowed_hosts', ['example.com']);
        Http::fake(['example.com/*' => Http::response('hello body', 200)]);

        $result = (new HttpFetchTool)->handle(['url' => 'https://example.com/page'], ToolContext::guest());

        $this->assertSame(200, $result['status']);
        $this->assertSame('hello body', $result['body']);
    }

    public function test_web_search_reports_when_unconfigured(): void
    {
        $result = (new WebSearchTool)->handle(['query' => 'laravel'], ToolContext::guest());

        $this->assertSame('Web search is not configured.', $result);
    }

    public function test_storage_read_is_jailed(): void
    {
        Storage::fake('local');
        config()->set('agents.storage.disk', 'local');
        config()->set('agents.storage.path_prefix', 'reports');
        Storage::disk('local')->put('reports/q1.txt', 'revenue up');

        $tool = new StorageReadTool;

        $read = $tool->handle(['operation' => 'read', 'path' => 'q1.txt'], ToolContext::guest());
        $this->assertSame('revenue up', $read['contents']);

        $traversal = $tool->handle(['operation' => 'read', 'path' => '../secret.txt'], ToolContext::guest());
        $this->assertSame('Invalid path.', $traversal);
    }
}
