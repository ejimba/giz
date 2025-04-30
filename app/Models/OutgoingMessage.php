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
        'id',
        'type',
        'user_id',
        'email',
        'phone',
        'subject',
        'message',
        'processed_at',
        'status',
        'status_date',
        'twilio_message_sid',
        'metadata',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'status_date' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that created the message
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the client associated with this message
     */
    public function client()
    {
        return $this->belongsTo(Client::class, 'phone', 'phone');
    }
}
