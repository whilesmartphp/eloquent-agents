<?php

namespace Whilesmart\Agents\Mcp;

use RuntimeException;

class McpException extends RuntimeException
{
    public static function http(string $method, int $status, string $body): self
    {
        return new self("MCP {$method} failed with HTTP {$status}: ".mb_substr($body, 0, 500), $status);
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public static function rpc(string $method, array $error): self
    {
        $message = is_string($error['message'] ?? null) ? $error['message'] : 'Unknown error';

        return new self("MCP {$method} failed: {$message}", (int) ($error['code'] ?? 0));
    }

    public static function noReply(string $method): self
    {
        return new self("MCP {$method} returned no reply to the request.");
    }
}
