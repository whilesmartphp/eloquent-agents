<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Whilesmart\Agents\Contracts\HasAgentResource;
use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceField;

class Currency extends Model implements HasAgentResource
{
    public $timestamps = false;

    protected $fillable = [
        'code',
        'rate',
    ];

    public static function agentResource(): AgentResource
    {
        return new AgentResource(
            name: 'currencies',
            model: self::class,
            table: 'currencies',
            description: 'Reference currency rates, the same for everyone.',
            labelColumn: 'code',
            ownerKey: null,
            global: true,
            readable: [
                ResourceField::key(),
                ResourceField::string('code', 'Currency code'),
                ResourceField::decimal('rate', 'Rate against the base currency'),
            ],
        );
    }
}
