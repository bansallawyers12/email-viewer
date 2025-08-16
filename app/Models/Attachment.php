<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'filename',
        'display_name',
        'content_type',
        'file_path',
        'file_size',
        'content_id',
        'is_inline',
        'description',
        'headers',
    ];

    protected $casts = [
        'is_inline' => 'boolean',
        'headers' => 'array',
    ];

    /**
     * Get the email that owns the attachment.
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
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
     * Get the display name or filename.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->display_name ?? $this->filename;
    }

    /**
     * Get the file extension.
     */
    public function getExtensionAttribute(): string
    {
        return pathinfo($this->filename, PATHINFO_EXTENSION);
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        $imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
        return in_array($this->content_type, $imageTypes);
    }

    /**
     * Check if the attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->content_type === 'application/pdf';
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        $documentTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            'application/rtf',
            'text/html',
            'text/css',
            'application/json',
            'application/xml',
            'text/csv',
        ];
        return in_array($this->content_type, $documentTypes);
    }

    /**
     * Check if the attachment is a video file.
     */
    public function isVideo(): bool
    {
        $videoTypes = [
            'video/mp4',
            'video/avi',
            'video/mov',
            'video/wmv',
            'video/flv',
            'video/webm',
            'video/mkv',
        ];
        return in_array($this->content_type, $videoTypes);
    }

    /**
     * Check if the attachment is an audio file.
     */
    public function isAudio(): bool
    {
        $audioTypes = [
            'audio/mp3',
            'audio/wav',
            'audio/ogg',
            'audio/aac',
            'audio/flac',
            'audio/m4a',
        ];
        return in_array($this->content_type, $audioTypes);
    }

    /**
     * Check if the attachment is an archive file.
     */
    public function isArchive(): bool
    {
        $archiveTypes = [
            'application/zip',
            'application/x-rar-compressed',
            'application/x-7z-compressed',
            'application/x-tar',
            'application/gzip',
            'application/x-bzip2',
        ];
        return in_array($this->content_type, $archiveTypes);
    }

    /**
     * Check if the attachment can be previewed.
     */
    public function canPreview(): bool
    {
        return $this->isImage() || $this->isPdf();
    }

    /**
     * Get the preview type for the attachment.
     */
    public function getPreviewType(): string
    {
        if ($this->isImage()) {
            return 'image';
        }
        
        if ($this->isPdf()) {
            return 'pdf';
        }
        
        return 'none';
    }

    /**
     * Check if the attachment can be converted to HTML for preview.
     */
    public function canConvertToHtml(): bool
    {
        $convertibleTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/rtf',
        ];
        return in_array($this->content_type, $convertibleTypes);
    }

    /**
     * Get the icon class for the attachment type.
     */
    public function getIconClassAttribute(): string
    {
        if ($this->isImage()) {
            return 'fas fa-image';
        }
        
        if ($this->isPdf()) {
            return 'fas fa-file-pdf';
        }
        
        if ($this->isDocument()) {
            return 'fas fa-file-alt';
        }
        
        return 'fas fa-paperclip';
    }

    /**
     * Scope to filter by content type.
     */
    public function scopeOfType($query, $contentType)
    {
        return $query->where('content_type', $contentType);
    }

    /**
     * Scope to filter inline attachments.
     */
    public function scopeInline($query)
    {
        return $query->where('is_inline', true);
    }

    /**
     * Scope to filter regular attachments.
     */
    public function scopeRegular($query)
    {
        return $query->where('is_inline', false);
    }
}
