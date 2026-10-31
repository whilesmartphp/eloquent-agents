<?php

namespace Whilesmart\Agents\Mcp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A JSON-RPC 2.0 client for one MCP server over the streamable HTTP transport.
 * It opens a session on first use and carries the server's session id on every
 * later request when the server hands one out.
 */
class McpClient
{
    public const PROTOCOL_VERSION = '2025-03-26';

    protected ?string $sessionId = null;

    protected bool $initialized = false;

    protected int $nextId = 0;

    /**
     * @param  array<string, string>  $headers
     */
    public function __construct(
        protected string $url,
        protected array $headers = [],
        protected int $timeout = 10,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function initialize(): array
    {
        $this->sessionId = null;

        $result = $this->send('initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'whilesmart/eloquent-agents', 'version' => '1'],
        ]);

        if ($this->sessionId !== null) {
            $this->notify('notifications/initialized');
        }

        $this->initialized = true;

        return $result;
    }

    /**
     * Every tool the server offers, following pagination to the end.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTools(): array
    {
        $tools = [];
        $cursor = null;

        do {
            $result = $this->request('tools/list', $cursor === null ? [] : ['cursor' => $cursor]);

            foreach ((array) ($result['tools'] ?? []) as $tool) {
                $tools[] = $tool;
            }

            $cursor = $result['nextCursor'] ?? null;
        } while (is_string($cursor) && $cursor !== '');

        return $tools;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed> The JSON-RPC result: content, and structuredContent or isError when given.
     */
    public function callTool(string $name, array $arguments = []): array
    {
        return $this->request('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    public function request(string $method, array $params = []): array
    {
        if (! $this->initialized) {
            $this->initialize();
        }

        try {
            return $this->send($method, $params);
        } catch (McpException $e) {
            // A server that forgot the session answers 404; the transport says to
            // open a fresh session and try once more.
            if ($this->sessionId === null || $e->getCode() !== 404) {
                throw $e;
            }

            $this->initialize();

            return $this->send($method, $params);
        }
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    protected function send(string $method, array $params): array
    {
        $id = $this->nextId++;

        $response = $this->post([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => (object) $params,
        ]);

        if ($response->failed()) {
            throw McpException::http($method, $response->status(), $response->body());
        }

        $reply = $this->replyTo($response, $id) ?? throw McpException::noReply($method);

        if (isset($reply['error']) && is_array($reply['error'])) {
            throw McpException::rpc($method, $reply['error']);
        }

        return is_array($reply['result'] ?? null) ? $reply['result'] : [];
    }

    protected function notify(string $method): void
    {
        $this->post(['jsonrpc' => '2.0', 'method' => $method]);
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected function post(array $message): Response
    {
        $headers = [
            ...$this->headers,
            'Accept' => 'application/json, text/event-stream',
            'Content-Type' => 'application/json',
        ];

        if ($this->sessionId !== null) {
            $headers['mcp-session-id'] = $this->sessionId;
        }

        $response = Http::timeout($this->timeout)->withHeaders($headers)->post($this->url, $message);

        $session = $response->header('mcp-session-id');

        if ($session !== '') {
            $this->sessionId = $session;
        }

        return $response;
    }

    /**
     * The JSON-RPC message answering $id, from either a JSON body or an event
     * stream of data frames.
     *
     * @return array<string, mixed>|null
     */
    protected function replyTo(Response $response, int $id): ?array
    {
        if (! str_contains(strtolower($response->header('Content-Type')), 'text/event-stream')) {
            $message = $response->json();

            return is_array($message) ? $message : null;
        }

        foreach ($this->frames($response->body()) as $message) {
            if (($message['id'] ?? null) === $id && (isset($message['result']) || isset($message['error']))) {
                return $message;
            }
        }

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function frames(string $body): array
    {
        $messages = [];
        $events = preg_split('/\n\n+/', str_replace("\r\n", "\n", $body)) ?: [];

        foreach ($events as $event) {
            $data = [];

            foreach (explode("\n", $event) as $line) {
                if (str_starts_with($line, 'data:')) {
                    $data[] = ltrim(substr($line, 5), ' ');
                }
            }

            if ($data === []) {
                continue;
            }

            $message = json_decode(implode("\n", $data), true);

            if (is_array($message)) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
