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
        'phone',
        'name',
        'status',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get all incoming messages for this client
     */
    public function incomingMessages(): HasMany
    {
        return $this->hasMany(IncomingMessage::class);
    }

    /**
     * Get all outgoing messages for this client
     */
    public function outgoingMessages(): HasMany
    {
        return $this->hasMany(OutgoingMessage::class, 'phone', 'phone');
    }
}
