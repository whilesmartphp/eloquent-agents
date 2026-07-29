<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Whilesmart\Agents\Contracts\HasAgentResource;
use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceField;
use Whilesmart\Agents\Resources\ResourceRelationship;

class Note extends Model implements HasAgentResource
{
    protected $fillable = [
        'user_id',
        'title',
        'body',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function comments(): HasMany
    {
        return $this->hasMany(NoteComment::class);
    }

    public static function agentResource(): AgentResource
    {
        return new AgentResource(
            name: 'notes',
            model: self::class,
            table: 'notes',
            description: 'Notes the user has written.',
            aliases: ['memos'],
            labelColumn: 'title',
            ownerKey: 'user_id',
            ownerBypassRoles: ['admin'],
            readable: [
                ResourceField::key(),
                ResourceField::string('title', 'Note title'),
                ResourceField::text('body', 'Note text'),
                ResourceField::decimal('amount', 'Amount attached to the note'),
                ResourceField::internal('user_id'),
                ResourceField::datetime('created_at', 'When the note was written'),
            ],
            writable: [
                ResourceField::string('title', 'Note title', required: true, rules: 'required|string|max:120'),
                ResourceField::text('body', 'Note text'),
                ResourceField::decimal('amount', 'Amount attached to the note'),
            ],
            relationships: [
                ResourceRelationship::hasMany('note_comments', 'note_comments', 'note_id'),
            ],
            writeEnabled: true,
        );
    }
}
