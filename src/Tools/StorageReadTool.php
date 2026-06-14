<?php

namespace Whilesmart\Agents\Tools;

use Illuminate\Support\Facades\Storage;
use Whilesmart\Agents\ValueObjects\ParameterSpec;
use Whilesmart\Agents\ValueObjects\ToolContext;

/**
 * List and read files on a configured disk, jailed to a path prefix. Path
 * traversal is rejected and reads are size-capped.
 */
class StorageReadTool extends AbstractTool
{
    public function name(): string
    {
        return 'storage.read';
    }

    public function description(): string
    {
        return 'List files in a directory or read a file from application storage. Paths are relative to the configured storage root.';
    }

    public function parameters(): array
    {
        return [
            ParameterSpec::enum('operation', 'Whether to list a directory or read a file', ['list', 'read']),
            ParameterSpec::string('path', 'Path relative to the storage root', required: false),
        ];
    }

    public function handle(array $arguments, ToolContext $context): string|array
    {
        $operation = $arguments['operation'] ?? 'list';
        $path = $this->jail((string) ($arguments['path'] ?? ''));

        if ($path === null) {
            return 'Invalid path.';
        }

        $disk = Storage::disk(config('agents.storage.disk', 'local'));

        if ($operation === 'read') {
            if (! $disk->exists($path)) {
                return 'File not found.';
            }

            $maxBytes = (int) config('agents.storage.max_bytes', 100_000);

            return [
                'path' => $path,
                'contents' => mb_substr((string) $disk->get($path), 0, $maxBytes),
            ];
        }

        return [
            'path' => $path,
            'files' => $disk->files($path),
            'directories' => $disk->directories($path),
        ];
    }

    /**
     * Resolve a user-supplied path inside the configured prefix, rejecting
     * traversal. Returns null when the path escapes the jail.
     */
    protected function jail(string $path): ?string
    {
        $path = ltrim($path, '/');

        if (str_contains($path, '..')) {
            return null;
        }

        $prefix = trim((string) config('agents.storage.path_prefix', ''), '/');
        $full = $prefix === '' ? $path : ($path === '' ? $prefix : $prefix.'/'.$path);

        return trim($full, '/');
    }
}
