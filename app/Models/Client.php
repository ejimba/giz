<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'clients';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
    
    /**
     * Get all conversations for this client
     */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }
    
    /**
     * Get the active conversation for this client, if any
     */
    public function activeConversation()
    {
        return $this->conversations()->where('status', 'active')->latest()->first();
    }
    
    /**
     * Get all responses from this client
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }
}
