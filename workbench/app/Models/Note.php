<?php

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

class Note extends Model
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
}
