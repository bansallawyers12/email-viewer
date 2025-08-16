<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subject',
        'sender_name',
        'sender_email',
        'recipients',
        'cc',
        'bcc',
        'sent_date',
        'received_date',
        'file_path',
        'file_name',
        'file_size',
        'html_content',
        'text_content',
        'raw_content',
        'headers',
        'message_id',
        'thread_id',
        'tags',
        'status',
        'error_message',
    ];

    protected $casts = [
        'sent_date' => 'datetime',
        'received_date' => 'datetime',
        'recipients' => 'array',
        'cc' => 'array',
        'bcc' => 'array',
        'headers' => 'array',
        'tags' => 'array',
    ];

    /**
     * Get the user that owns the email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attachments for the email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    /**
     * Get the labels for the email.
     */
    public function labels()
    {
        return $this->belongsToMany(Label::class, 'email_label')
                    ->withTimestamps();
    }

    /**
     * Check if the email is from the user's domain (sent item).
     */
    public function isSentItem(): bool
    {
        $userDomain = 'bansalimmigration.com.au';
        return $this->sender_email && str_contains($this->sender_email, $userDomain);
    }

    /**
     * Get the primary label (Inbox or Sent).
     */
    public function getPrimaryLabelAttribute(): string
    {
        if ($this->isSentItem()) {
            return 'Sent';
        }
        return 'Inbox';
    }
    
    /**
     * Check if the email has a primary label (Inbox or Sent).
     */
    public function hasPrimaryLabel(): bool
    {
        return $this->labels()
            ->whereIn('name', ['Inbox', 'Sent'])
            ->exists();
    }
    
    /**
     * Get the primary label object if it exists.
     */
    public function getPrimaryLabelObject()
    {
        return $this->labels()
            ->whereIn('name', ['Inbox', 'Sent'])
            ->first();
    }

    /**
     * Get the formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get the formatted sent date.
     */
    public function getFormattedSentDateAttribute(): string
    {
        if (!$this->sent_date) {
            return 'Unknown';
        }
        
        return $this->sent_date->format('M j, Y g:i A');
    }

    /**
     * Get the sender display name.
     */
    public function getSenderDisplayAttribute(): string
    {
        if ($this->sender_name && $this->sender_email) {
            return $this->sender_name . ' <' . $this->sender_email . '>';
        }
        
        return $this->sender_email ?? $this->sender_name ?? 'Unknown';
    }

    /**
     * Get the subject with fallback.
     */
    public function getDisplaySubjectAttribute(): string
    {
        return $this->subject ?? '(No Subject)';
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to search emails.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('subject', 'like', "%{$search}%")
              ->orWhere('sender_name', 'like', "%{$search}%")
              ->orWhere('sender_email', 'like', "%{$search}%")
              ->orWhere('text_content', 'like', "%{$search}%")
              ->orWhere('html_content', 'like', "%{$search}%");
        });
    }

    /**
     * Check if email has attachments.
     */
    public function hasAttachments(): bool
    {
        return $this->attachments()->count() > 0;
    }

    /**
     * Get the number of attachments.
     */
    public function getAttachmentCountAttribute(): int
    {
        return $this->attachments()->count();
    }

    /**
     * Get the primary content (HTML or text).
     */
    public function getPrimaryContentAttribute(): string
    {
        return $this->html_content ?? $this->text_content ?? '';
    }

    /**
     * Check if the email has HTML content.
     */
    public function hasHtmlContent(): bool
    {
        return !empty($this->html_content);
    }

    /**
     * Check if the email has text content.
     */
    public function hasTextContent(): bool
    {
        return !empty($this->text_content);
    }
}
