<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'title',
        'content',
        'next_prompt_id',
        'parent_prompt_id',
        'metadata',
        'active',
        'order',
        'type',
    ];

    protected $casts = [
        'metadata' => 'array',
        'active' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the next prompt in the conversation flow
     */
    public function nextPrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'next_prompt_id');
    }

    /**
     * Get the parent prompt
     */
    public function parentPrompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class, 'parent_prompt_id');
    }

    /**
     * Get child prompts that branch from this prompt
     */
    public function childPrompts(): HasMany
    {
        return $this->hasMany(Prompt::class, 'parent_prompt_id');
    }

    /**
     * Get all responses to this prompt
     */
    public function responses(): HasMany
    {
        return $this->hasMany(Response::class);
    }

    /**
     * Get all conversations currently at this prompt
     */
    public function activeConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'current_prompt_id');
    }
}
