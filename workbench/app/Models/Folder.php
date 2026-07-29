<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Whilesmart\Agents\Contracts\HasAgentResource;
use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceField;

class Folder extends Model implements HasAgentResource
{
    protected $fillable = [
        'owner_id',
        'owner_type',
        'name',
    ];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public static function agentResource(): AgentResource
    {
        return new AgentResource(
            name: 'folders',
            model: self::class,
            table: 'folders',
            description: 'Folders the user groups notes into.',
            labelColumn: 'name',
            ownerKey: 'owner_id',
            ownerParam: 'user_id',
            ownerConstants: ['owner_type' => User::class],
            readable: [
                ResourceField::key(),
                ResourceField::string('name', 'Folder name'),
                ResourceField::internal('owner_id'),
                ResourceField::internal('owner_type', 'string'),
            ],
            writable: [
                ResourceField::string('name', 'Folder name', required: true, rules: 'required|string|max:60'),
            ],
            writeEnabled: true,
        );
    }
}
