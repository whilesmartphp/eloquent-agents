<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Whilesmart\Agents\Contracts\HasAgentResource;
use Whilesmart\Agents\Resources\AgentResource;
use Whilesmart\Agents\Resources\ResourceField;
use Whilesmart\Agents\Resources\ResourceRelationship;
use Whilesmart\Agents\Resources\ThroughScope;

class NoteComment extends Model implements HasAgentResource
{
    protected $fillable = [
        'note_id',
        'body',
    ];

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public static function agentResource(): AgentResource
    {
        return new AgentResource(
            name: 'note_comments',
            model: self::class,
            table: 'note_comments',
            description: 'Comments left on a note.',
            ownerKey: null,
            scopeThrough: new ThroughScope(
                relation: 'note',
                resource: 'notes',
                column: 'note_id',
                references: 'notes.id',
            ),
            readable: [
                ResourceField::key(),
                ResourceField::reference('note_id', 'notes.id', 'Note this comment belongs to'),
                ResourceField::text('body', 'Comment text'),
            ],
            relationships: [
                ResourceRelationship::belongsTo('comment_note', 'notes', 'note_id'),
            ],
        );
    }
}
