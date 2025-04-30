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
        'client_id',
        'twilio_message_sid',
        'from_number',
        'message',
        'media',
        'processed_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'media' => 'array',
        'metadata' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the client that owns the message
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
