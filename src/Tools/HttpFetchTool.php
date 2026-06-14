<?php

namespace Whilesmart\Agents\Tools;

use Illuminate\Support\Facades\Http;
use Whilesmart\Agents\Enums\ToolPermission;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * Fetch the contents of a URL. Constrained by a host allowlist (empty = deny
 * all), an https/http scheme check, and a response-size cap.
 */
class HttpFetchTool extends AbstractTool
{
    public function name(): string
    {
        return 'http.fetch';
    }

    public function description(): string
    {
        return 'Fetch the contents of an HTTP(S) URL. Only allowlisted hosts are reachable.';
    }

    public function permission(): ToolPermission
    {
        return ToolPermission::EXTERNAL;
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::string('url', 'The absolute http(s) URL to fetch'),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $url = (string) ($arguments['url'] ?? '');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! in_array($scheme, ['http', 'https'], true) || ! is_string($host)) {
            return 'Invalid URL. Provide an absolute http(s) URL.';
        }

        $allowed = (array) config('agents.http.allowed_hosts', []);

        if ($allowed === [] || ! in_array($host, $allowed, true)) {
            return "Host '{$host}' is not allowed.";
        }

        $maxBytes = (int) config('agents.http.max_bytes', 100_000);

        try {
            $response = Http::timeout((int) config('agents.http.timeout', 10))->get($url);
        } catch (\Throwable $e) {
            return 'Request failed: '.$e->getMessage();
        }

        return [
            'status' => $response->status(),
            'url' => $url,
            'body' => mb_substr($response->body(), 0, $maxBytes),
        ];
    }
}
