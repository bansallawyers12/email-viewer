<?php

namespace App\Services;

use App\Models\Email;
use App\Models\Attachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Exception;

class MsgParserService
{
    /**
     * Parse a .msg file and extract all information.
     */
    public function parseMsgFile(string $filePath, int $userId): Email
    {
        try {
            // Create email record first
            $email = $this->createEmailRecord($filePath, $userId);
            
            // Use Python as the primary parsing method
            $parsed = $this->parseWithPython($filePath);
            
            if (!$parsed) {
                throw new Exception('Unable to parse .msg file with Python');
            }
            
            // Update email with parsed data
            $this->updateEmailWithParsedData($email, $parsed);
            
            // Process attachments
            $this->processAttachments($email, $parsed);
            
            // Mark as processed
            $email->update(['status' => 'processed']);
            
            // Automatically assign Inbox/Sent labels
            $this->assignPrimaryLabels($email);
            
            return $email;
            
        } catch (Exception $e) {
            Log::error('Failed to parse .msg file: ' . $e->getMessage(), [
                'file_path' => $filePath,
                'user_id' => $userId
            ]);
            
            if (isset($email)) {
                $email->update([
                    'status' => 'error',
                    'error_message' => $e->getMessage()
                ]);
            }
            
            throw $e;
        }
    }
    
    /**
     * Create initial email record.
     */
    private function createEmailRecord(string $filePath, int $userId): Email
    {
        $fileInfo = pathinfo($filePath);
        
        return Email::create([
            'user_id' => $userId,
            'file_path' => $filePath,
            'file_name' => $fileInfo['basename'],
            'file_size' => filesize($filePath),
            'status' => 'processing'
        ]);
    }
    
    /**
     * Parse .msg file using Python with extract-msg library.
     */
    private function parseWithPython(string $filePath): ?array
    {
        try {
            $pythonScript = storage_path('app/scripts/parse_msg.py');
            
            if (!file_exists($pythonScript)) {
                $this->createPythonScript($pythonScript);
            }
            
            $command = "py \"{$pythonScript}\" " . escapeshellarg($filePath);
            $output = shell_exec($command);
            
            if ($output) {
                $parsed = json_decode($output, true);
                
                if (json_last_error() === JSON_ERROR_NONE && !isset($parsed['error'])) {
                    return $parsed;
                } else {
                    Log::warning('Python parsing returned error: ' . ($parsed['error'] ?? 'Unknown error'));
                    return null;
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            Log::warning('Python parsing failed: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create Python script for .msg parsing.
     */
    private function createPythonScript(string $scriptPath): void
    {
        $script = <<<'PYTHON'
#!/usr/bin/env python3
import sys
import json
import os
from datetime import datetime

try:
    import extract_msg
except ImportError:
    print(json.dumps({'error': 'extract_msg library not installed'}))
    sys.exit(1)

def safe_json_serialize(obj):
    """Convert object to JSON-serializable format"""
    if isinstance(obj, bytes):
        return obj.decode('utf-8', errors='ignore')
    elif isinstance(obj, datetime):
        return obj.isoformat()
    elif hasattr(obj, '__dict__'):
        return str(obj)
    else:
        return str(obj)

def parse_msg_file(file_path):
    try:
        msg = extract_msg.Message(file_path)
        
        # Extract basic information
        data = {
            'subject': safe_json_serialize(msg.subject or ''),
            'sender_name': safe_json_serialize(msg.sender or ''),
            'sender_email': safe_json_serialize(getattr(msg, 'senderEmail', '') or ''),
            'sent_date': safe_json_serialize(msg.date) if msg.date else None,
            'html_content': safe_json_serialize(msg.htmlBody or ''),
            'text_content': safe_json_serialize(msg.body or ''),
            'recipients': [],
            'attachments': [],
            'headers': {},
            'message_id': safe_json_serialize(getattr(msg, 'messageId', '') or ''),
        }
        
        # Extract recipients
        if hasattr(msg, 'to') and msg.to:
            recipients = [email.strip() for email in msg.to.split(',')]
            data['recipients'] = [safe_json_serialize(email) for email in recipients]
        
        # Extract attachments
        for attachment in msg.attachments:
            data['attachments'].append({
                'filename': safe_json_serialize(attachment.longFilename or attachment.shortFilename),
                'content_type': safe_json_serialize(getattr(attachment, 'contentType', 'application/octet-stream') or 'application/octet-stream'),
                'content_id': safe_json_serialize(getattr(attachment, 'contentId', '') or ''),
                'is_inline': bool(getattr(attachment, 'contentId', None)),
                'size': len(attachment.data) if attachment.data else 0,
                'data': attachment.data if attachment.data else None,
            })
        
        print(json.dumps(data))
        
    except Exception as e:
        print(json.dumps({'error': str(e)}))

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'error': 'Usage: python script.py <msg_file_path>'}))
        sys.exit(1)
    
    parse_msg_file(sys.argv[1])
PYTHON;
        
        // Create scripts directory if it doesn't exist
        $scriptsDir = dirname($scriptPath);
        if (!is_dir($scriptsDir)) {
            mkdir($scriptsDir, 0755, true);
        }
        
        file_put_contents($scriptPath, $script);
        chmod($scriptPath, 0755);
    }
    
    /**
     * Update email record with parsed data.
     */
    private function updateEmailWithParsedData(Email $email, array $parsed): void
    {
        // Sanitize fields to ensure valid UTF-8 before JSON encoding/casting
        $sanitized = [
            'subject' => $this->sanitizeForJson($parsed['subject'] ?? null),
            'sender_name' => $this->sanitizeForJson($parsed['sender_name'] ?? null),
            'sender_email' => $this->sanitizeForJson($parsed['sender_email'] ?? null),
            'sent_date' => $this->parseDate($parsed['sent_date'] ?? null),
            'html_content' => $this->sanitizeForJson($parsed['html_content'] ?? null),
            'text_content' => $this->sanitizeForJson($parsed['text_content'] ?? null),
            'recipients' => $this->sanitizeForJson($parsed['recipients'] ?? []),
            'headers' => $this->sanitizeForJson($parsed['headers'] ?? []),
            'message_id' => $this->sanitizeForJson($parsed['message_id'] ?? null),
        ];

        $email->update($sanitized);
    }
    
    /**
     * Process attachments from parsed data.
     */
    private function processAttachments(Email $email, array $parsed): void
    {
        if (!isset($parsed['attachments']) || empty($parsed['attachments'])) {
            return;
        }
        
        $attachmentDir = storage_path("app/emails/{$email->id}/attachments");
        if (!is_dir($attachmentDir)) {
            mkdir($attachmentDir, 0755, true);
        }
        
        foreach ($parsed['attachments'] as $attachmentData) {
            $this->createAttachmentRecord($email, $attachmentData, $attachmentDir);
        }
    }
    
    /**
     * Create attachment record and save file.
     */
    private function createAttachmentRecord(Email $email, array $attachmentData, string $attachmentDir): void
    {
        $filename = $attachmentData['filename'] ?? 'unknown';
        $filePath = $attachmentDir . '/' . $filename;
        
        // Save attachment file if data is available
        if (isset($attachmentData['data']) && $attachmentData['data'] !== null) {
            // Decode base64 data if it's encoded
            $data = $attachmentData['data'];
            if (is_string($data) && base64_encode(base64_decode($data, true)) === $data) {
                // It's base64 encoded, decode it
                $data = base64_decode($data);
            }
            file_put_contents($filePath, $data);
        }
        
        // Detect actual MIME type from file content or extension
        $detectedContentType = $this->detectMimeType($filePath, $attachmentData['content_type'] ?? 'application/octet-stream');
        
        Attachment::create([
            'email_id' => $email->id,
            'filename' => $this->sanitizeForJson($filename),
            'display_name' => $this->sanitizeForJson($attachmentData['display_name'] ?? $filename),
            'content_type' => $detectedContentType,
            'file_path' => $filePath,
            'file_size' => $attachmentData['size'] ?? 0,
            'content_id' => $this->sanitizeForJson($attachmentData['content_id'] ?? null),
            'is_inline' => $attachmentData['is_inline'] ?? false,
            'description' => $this->sanitizeForJson($attachmentData['description'] ?? null),
            'headers' => $this->sanitizeForJson($attachmentData['headers'] ?? []),
        ]);
    }

    /**
     * Detect MIME type from file content or extension.
     */
    private function detectMimeType(string $filePath, string $fallbackType): string
    {
        // First try to detect from file content using PHP's mime_content_type
        if (function_exists('mime_content_type') && file_exists($filePath)) {
            $detectedType = mime_content_type($filePath);
            if ($detectedType && $detectedType !== 'application/octet-stream') {
                return $detectedType;
            }
        }
        
        // Fallback to extension-based detection
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'tiff' => 'image/tiff',
            'ico' => 'image/x-icon',
            
            // PDFs
            'pdf' => 'application/pdf',
            
            // Documents
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'csv' => 'text/csv',
            
            // Archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            'bz2' => 'application/x-bzip2',
        ];
        
        if (isset($mimeTypes[$extension])) {
            return $mimeTypes[$extension];
        }
        
        // If we still can't determine, return the fallback type
        return $fallbackType;
    }
    
    /**
     * Parse date string to Carbon instance.
     */
    private function parseDate(?string $dateString): ?string
    {
        if (!$dateString) {
            return null;
        }
        
        try {
            return \Carbon\Carbon::parse($dateString)->toDateTimeString();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Ensure a value (string/array) can be safely JSON-encoded by converting to valid UTF-8.
     */
    private function sanitizeForJson($value)
    {
        if (is_array($value)) {
            $sanitizedArray = [];
            foreach ($value as $key => $val) {
                // Sanitize keys too, since json_encode expects UTF-8 keys
                $sanitizedKey = is_string($key) ? $this->sanitizeForJson($key) : $key;
                $sanitizedArray[$sanitizedKey] = $this->sanitizeForJson($val);
            }
            return $sanitizedArray;
        }

        if (is_string($value)) {
            // If already valid UTF-8, return as is
            if (function_exists('mb_check_encoding') && mb_check_encoding($value, 'UTF-8')) {
                return $value;
            }
            // Try common conversions; fall back to stripping invalid bytes
            $converted = @mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
            if ($converted === false) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $value);
            }
            return $converted !== false ? $converted : '';
        }

        return $value;
    }
    
    /**
     * Automatically assign primary labels (Inbox/Sent) to an email.
     */
    private function assignPrimaryLabels(Email $email): void
    {
        try {
            // Get system labels for this user
            $inboxLabel = \App\Models\Label::forUser($email->user_id)
                ->where('name', 'Inbox')
                ->first();
            
            $sentLabel = \App\Models\Label::forUser($email->user_id)
                ->where('name', 'Sent')
                ->first();
            
            if (!$inboxLabel || !$sentLabel) {
                Log::warning('System labels not found for user', [
                    'user_id' => $email->user_id,
                    'email_id' => $email->id
                ]);
                return;
            }
            
            // Check if email already has labels
            if ($email->labels()->count() > 0) {
                return; // Already labeled
            }
            
            // Determine and apply the appropriate label
            $labelToApply = $email->isSentItem() ? $sentLabel : $inboxLabel;
            
            if ($labelToApply) {
                $email->labels()->attach($labelToApply->id);
                Log::info('Primary label assigned automatically', [
                    'email_id' => $email->id,
                    'label_name' => $labelToApply->name,
                    'user_id' => $email->user_id
                ]);
            }
            
        } catch (Exception $e) {
            Log::error('Failed to assign primary labels', [
                'email_id' => $email->id,
                'user_id' => $email->user_id,
                'error' => $e->getMessage()
            ]);
        }
    }
} 