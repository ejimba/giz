<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutgoingMessage extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'outgoing_messages';

    protected $fillable = [
        'type',
        'provider_id',
        'to',
        'subject',
        'message',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];
}
