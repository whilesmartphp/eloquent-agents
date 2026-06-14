<?php

namespace Whilesmart\Agents\Enums;

enum ToolPermission: string
{
    case READ = 'read';
    case WRITE = 'write';
    case EXTERNAL = 'external';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
