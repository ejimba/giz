<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Response extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    
    protected $fillable = [
        'client_id',
        'prompt_id',
        'conversation_id',
        'content',
        'metadata',
        'received_at',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'received_at' => 'datetime',
    ];
    
    /**
     * Get the client that created the response
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    
    /**
     * Get the prompt this is responding to
     */
    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
    
    /**
     * Get the conversation this response is part of
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
