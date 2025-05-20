<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory, HasUuids, SoftDeletes;
    
    protected $fillable = [
        'client_id',
        'title',
        'current_prompt_id',
        'status',
        'started_at',
        'completed_at',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
    
    /**
     * Get the client associated with this conversation
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
    
    /**
     * Get the current prompt in the conversation
     */
    public function currentPrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'current_prompt_id');
    }
    
    /**
     * Get all responses in this conversation
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }
    
    /**
     * Mark the conversation as completed
     */
    public function complete(): self
    {
        $this->status = 'completed';
        $this->completed_at = now();
        $this->save();
        
        return $this;
    }
    
    /**
     * Mark the conversation as abandoned
     */
    public function abandon(): self
    {
        $this->status = 'abandoned';
        $this->save();
        
        return $this;
    }
    
    /**
     * Advance to the next prompt
     */
    public function advanceToNextPrompt(): self
    {
        if ($this->currentPrompt && $this->currentPrompt->nextPrompt) {
            $this->current_prompt_id = $this->currentPrompt->next_prompt_id;
            $this->save();
        }
        
        return $this;
    }
    
    /**
     * Set the current prompt to a specific prompt
     */
    public function setCurrentPrompt(Prompt $prompt): self
    {
        $this->current_prompt_id = $prompt->id;
        $this->save();
        
        return $this;
    }
}
