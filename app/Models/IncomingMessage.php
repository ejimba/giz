<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncomingMessage extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'incoming_messages';

    protected $fillable = [
        'type',
        'provider_id',
        'from',
        'subject',
        'message',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];
}
