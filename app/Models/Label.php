<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Label extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'color',
        'type',
        'icon',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the user that owns the label.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the emails that have this label.
     */
    public function emails(): BelongsToMany
    {
        return $this->belongsToMany(Email::class, 'email_label')
                    ->withTimestamps();
    }

    /**
     * Check if this is a system label.
     */
    public function isSystem(): bool
    {
        return $this->type === 'system';
    }

    /**
     * Check if this is a custom label.
     */
    public function isCustom(): bool
    {
        return $this->type === 'custom';
    }

    /**
     * Get the display icon for the label.
     */
    public function getDisplayIconAttribute(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        // Default icons based on label name
        $defaultIcons = [
            'inbox' => 'fas fa-inbox',
            'sent' => 'fas fa-paper-plane',
            'draft' => 'fas fa-edit',
            'trash' => 'fas fa-trash',
            'spam' => 'fas fa-ban',
            'archive' => 'fas fa-archive',
            'work' => 'fas fa-briefcase',
            'personal' => 'fas fa-user',
            'important' => 'fas fa-star',
            'urgent' => 'fas fa-exclamation-triangle',
        ];

        $labelName = strtolower($this->name);
        return $defaultIcons[$labelName] ?? 'fas fa-tag';
    }

    /**
     * Get the formatted color with fallback.
     */
    public function getFormattedColorAttribute(): string
    {
        return $this->color ?: '#3B82F6';
    }

    /**
     * Scope to filter active labels.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter system labels.
     */
    public function scopeSystem($query)
    {
        return $query->where('type', 'system');
    }

    /**
     * Scope to filter custom labels.
     */
    public function scopeCustom($query)
    {
        return $query->where('type', 'custom');
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
