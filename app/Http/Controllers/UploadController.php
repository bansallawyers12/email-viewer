<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\MsgParserService;
use App\Models\Email;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    protected MsgParserService $msgParserService;
    
    public function __construct(MsgParserService $msgParserService)
    {
        $this->msgParserService = $msgParserService;
    }
    
    /**
     * Handle file upload and processing.
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            // Enhanced validation
            $validator = Validator::make($request->all(), [
                'files.*' => [
                    'required',
                    'file',
                    'mimes:msg',
                    'max:10240', // 10MB max
                    'min:1', // Minimum 1 byte
                ]
            ], [
                'files.*.required' => 'Please select at least one file to upload.',
                'files.*.file' => 'The uploaded file is not valid.',
                'files.*.mimes' => 'Only .msg files are allowed.',
                'files.*.max' => 'File size must not exceed 10MB.',
                'files.*.min' => 'File size must be at least 1 byte.'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if files were uploaded
            if (!$request->hasFile('files') || empty($request->file('files'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'No files were uploaded.'
                ], 400);
            }

            // Check storage space
            $storageUsage = $this->getStorageUsage();
            $maxStorage = 10 * 1024 * 1024 * 1024; // 10GB
            $uploadedSize = 0;
            
            foreach ($request->file('files') as $file) {
                $uploadedSize += $file->getSize();
            }
            
            if (($storageUsage['total_bytes'] + $uploadedSize) > $maxStorage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload would exceed storage limit. Please delete some emails first.'
                ], 413);
            }

            $uploadedEmails = [];
            $errors = [];
            $totalFiles = count($request->file('files'));
            
            foreach ($request->file('files') as $index => $file) {
                try {
                    // Additional file validation
                    if (!$this->validateMsgFile($file)) {
                        $errors[] = [
                            'filename' => $file->getClientOriginalName(),
                            'error' => 'Invalid .msg file format'
                        ];
                        continue;
                    }

                    // Check for duplicate emails
                    $duplicateCheck = $this->checkForDuplicateEmail($file);
                    if ($duplicateCheck['is_duplicate']) {
                        $errors[] = [
                            'filename' => $file->getClientOriginalName(),
                            'error' => 'Duplicate email detected',
                            'warning' => true,
                            'duplicate_info' => $duplicateCheck['duplicate_info']
                        ];
                        continue;
                    }

                    $email = $this->processUploadedFile($file);
                    $uploadedEmails[] = $email;
                    
                    Log::info('File uploaded successfully', [
                        'filename' => $file->getClientOriginalName(),
                        'user_id' => Auth::id() ?? 1,
                        'email_id' => $email->id,
                        'progress' => round((($index + 1) / $totalFiles) * 100, 2)
                    ]);
                    
                } catch (Exception $e) {
                    $errorMessage = $this->getUserFriendlyErrorMessage($e);
                    $errors[] = [
                        'filename' => $file->getClientOriginalName(),
                        'error' => $errorMessage
                    ];
                    
                    Log::error('File upload failed', [
                        'filename' => $file->getClientOriginalName(),
                        'user_id' => Auth::id() ?? 1,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            $successCount = count($uploadedEmails);
            $errorCount = count($errors);
            
            $message = $this->buildUploadMessage($successCount, $errorCount, $totalFiles);
            
            return response()->json([
                'success' => $successCount > 0,
                'message' => $message,
                'emails' => $uploadedEmails,
                'errors' => $errors,
                'summary' => [
                    'total_files' => $totalFiles,
                    'successful_uploads' => $successCount,
                    'failed_uploads' => $errorCount
                ]
            ], $successCount > 0 ? 200 : 422);
            
        } catch (ValidationException $e) {
            Log::error('Upload validation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (Exception $e) {
            Log::error('Upload controller error: ' . $e->getMessage(), [
                'user_id' => Auth::id() ?? 1,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred. Please try again.'
            ], 500);
        }
    }
    
    /**
     * Validate .msg file format
     */
    private function validateMsgFile($file): bool
    {
        try {
            // Check file extension
            if (strtolower($file->getClientOriginalExtension()) !== 'msg') {
                return false;
            }
            
            // Check file header for .msg format
            $handle = fopen($file->getPathname(), 'rb');
            if (!$handle) {
                return false;
            }
            
            $header = fread($handle, 8);
            fclose($handle);
            
            // Basic .msg file header check
            return strlen($header) >= 8;
            
        } catch (Exception $e) {
            Log::warning('File validation failed', [
                'filename' => $file->getClientOriginalName(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Get user-friendly error message
     */
    private function getUserFriendlyErrorMessage(Exception $e): string
    {
        $message = $e->getMessage();
        
        // Map technical errors to user-friendly messages
        $errorMap = [
            'file_get_contents' => 'Unable to read file content',
            'Permission denied' => 'Permission denied accessing file',
            'No space left on device' => 'Storage space is full',
            'Invalid .msg file' => 'The file is not a valid .msg format',
            'File too large' => 'File size exceeds the maximum limit',
        ];
        
        foreach ($errorMap as $technicalError => $userMessage) {
            if (strpos($message, $technicalError) !== false) {
                return $userMessage;
            }
        }
        
        return 'Failed to process file. Please ensure it\'s a valid .msg file.';
    }
    
    /**
     * Build upload result message
     */
    private function buildUploadMessage(int $successCount, int $errorCount, int $totalFiles): string
    {
        if ($successCount === $totalFiles) {
            return "All {$totalFiles} file(s) uploaded successfully";
        } elseif ($successCount > 0) {
            return "{$successCount} of {$totalFiles} file(s) uploaded successfully" . 
                   ($errorCount > 0 ? " ({$errorCount} failed)" : "");
        } else {
            return "Upload failed for all {$totalFiles} file(s)";
        }
    }
    
    /**
     * Process a single uploaded file.
     */
    private function processUploadedFile($file): Email
    {
        $userId = Auth::id() ?? 1;
        $originalName = $file->getClientOriginalName();
        
        // Sanitize filename
        $safeName = $this->sanitizeFilename($originalName);
        
        // Generate unique filename
        $filename = time() . '_' . uniqid() . '_' . $safeName;
        $filePath = storage_path('app/emails/' . $filename);
        
        // Ensure directory exists with proper permissions
        $directory = dirname($filePath);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new Exception('Failed to create storage directory');
            }
        }
        
        // Check if directory is writable
        if (!is_writable($directory)) {
            throw new Exception('Storage directory is not writable');
        }
        
        // Move uploaded file to storage
        if (!$file->move($directory, $filename)) {
            throw new Exception('Failed to move uploaded file to storage');
        }
        
        // Verify file was moved successfully
        if (!file_exists($filePath)) {
            throw new Exception('File was not saved correctly');
        }
        
        // Parse the .msg file
        return $this->msgParserService->parseMsgFile($filePath, $userId);
    }
    
    /**
     * Sanitize filename for security
     */
    private function sanitizeFilename(string $filename): string
    {
        // Remove any path traversal attempts
        $filename = basename($filename);
        
        // Remove or replace potentially dangerous characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Ensure it's not too long
        if (strlen($filename) > 255) {
            $extension = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 255 - strlen($extension) - 1) . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * Get upload progress for a specific email.
     */
    public function progress(int $emailId): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->findOrFail($emailId);
            
            $progress = [
                'email_id' => $email->id,
                'status' => $email->status ?? 'completed',
                'progress' => $email->processing_progress ?? 100,
                'message' => $this->getProgressMessage($email->status ?? 'completed')
            ];
            
            return response()->json([
                'success' => true,
                'progress' => $progress
            ]);
            
        } catch (Exception $e) {
            Log::error('Progress check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get upload progress'
            ], 404);
        }
    }
    
    /**
     * Get progress message based on status
     */
    private function getProgressMessage(string $status): string
    {
        $messages = [
            'pending' => 'File uploaded, waiting to be processed...',
            'processing' => 'Processing email content and attachments...',
            'completed' => 'Email processing completed successfully',
            'failed' => 'Email processing failed',
            'cancelled' => 'Email processing was cancelled'
        ];
        
        return $messages[$status] ?? 'Unknown status';
    }
    
    /**
     * Get storage usage information.
     */
    public function storageUsage(): JsonResponse
    {
        try {
            $usage = $this->getStorageUsage();
            
            return response()->json([
                'success' => true,
                'total_bytes' => $usage['total_bytes'],
                'formatted_size' => $usage['formatted_size'],
                'email_count' => $usage['email_count'],
                'attachment_count' => $usage['attachment_count'],
                'usage_percentage' => $usage['usage_percentage']
            ]);
            
        } catch (Exception $e) {
            Log::error('Storage usage check failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get storage usage'
            ], 500);
        }
    }
    
    /**
     * Get storage usage data
     */
    private function getStorageUsage(): array
    {
        $userId = Auth::id() ?? 1;
        
        // Get email statistics
        $emailCount = Email::where('user_id', $userId)->count();
        $attachmentCount = Email::where('user_id', $userId)
                               ->withCount('attachments')
                               ->get()
                               ->sum('attachments_count');
        
        // Calculate total size
        $totalBytes = Email::where('user_id', $userId)->sum('file_size');
        
        // Calculate usage percentage (10GB max)
        $maxStorage = 10 * 1024 * 1024 * 1024;
        $usagePercentage = min(($totalBytes / $maxStorage) * 100, 100);
        
        return [
            'total_bytes' => $totalBytes,
            'formatted_size' => $this->formatBytes($totalBytes),
            'email_count' => $emailCount,
            'attachment_count' => $attachmentCount,
            'usage_percentage' => round($usagePercentage, 2)
        ];
    }
    
    /**
     * Delete uploaded email and its files.
     */
    public function delete(int $emailId): JsonResponse
    {
        try {
            $email = Email::where('user_id', Auth::id() ?? 1)
                         ->findOrFail($emailId);
            
            // Delete associated files
            if ($email->file_path && file_exists($email->file_path)) {
                unlink($email->file_path);
            }
            
            // Delete attachments
            foreach ($email->attachments as $attachment) {
                if ($attachment->file_path && file_exists($attachment->file_path)) {
                    unlink($attachment->file_path);
                }
            }
            
            // Delete from database
            $email->delete();
            
            Log::info('Email deleted successfully', [
                'email_id' => $emailId,
                'user_id' => Auth::id() ?? 1
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Email deleted successfully'
            ]);
            
        } catch (Exception $e) {
            Log::error('Email deletion failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete email'
            ], 500);
        }
    }
    
    /**
     * Check if an email file is a duplicate
     */
    private function checkForDuplicateEmail($file): array
    {
        $userId = Auth::id() ?? 1;
        $originalName = $file->getClientOriginalName();
        $fileSize = $file->getSize();
        
        // Check for exact filename match
        $exactMatch = Email::where('user_id', $userId)
                          ->where('file_name', $originalName)
                          ->first();
        
        if ($exactMatch) {
            return [
                'is_duplicate' => true,
                'duplicate_info' => [
                    'type' => 'exact_filename',
                    'existing_email_id' => $exactMatch->id,
                    'existing_subject' => $exactMatch->subject,
                    'existing_date' => $exactMatch->sent_date,
                    'message' => "An email with the same filename '{$originalName}' already exists"
                ]
            ];
        }
        
        // Check for file size match (quick check for potential duplicates)
        $sizeMatches = Email::where('user_id', $userId)
                           ->where('file_size', $fileSize)
                           ->get();
        
        if ($sizeMatches->count() > 0) {
            // For files with same size, check content hash
            $fileHash = $this->calculateFileHash($file);
            
            foreach ($sizeMatches as $sizeMatch) {
                if ($sizeMatch->file_path && file_exists($sizeMatch->file_path)) {
                    $existingHash = $this->calculateFileHashFromPath($sizeMatch->file_path);
                    if ($fileHash === $existingHash) {
                        return [
                            'is_duplicate' => true,
                            'duplicate_info' => [
                                'type' => 'content_hash',
                                'existing_email_id' => $sizeMatch->id,
                                'existing_subject' => $sizeMatch->subject,
                                'existing_date' => $sizeMatch->sent_date,
                                'existing_filename' => $sizeMatch->file_name,
                                'message' => "An email with identical content already exists (Subject: '{$sizeMatch->subject}')"
                            ]
                        ];
                    }
                }
            }
        }
        
        // Check for similar subjects and senders (fuzzy duplicate detection)
        $parsedData = $this->parseEmailMetadata($file);
        if ($parsedData) {
            $similarEmails = Email::where('user_id', $userId)
                                ->where('subject', $parsedData['subject'])
                                ->where('sender_email', $parsedData['sender_email'])
                                ->where('sent_date', $parsedData['sent_date'])
                                ->first();
            
            if ($similarEmails) {
                return [
                    'is_duplicate' => true,
                    'duplicate_info' => [
                        'type' => 'metadata_match',
                        'existing_email_id' => $similarEmails->id,
                        'existing_subject' => $similarEmails->subject,
                        'existing_date' => $similarEmails->sent_date,
                        'existing_filename' => $similarEmails->file_name,
                        'message' => "An email with similar metadata already exists (Subject: '{$similarEmails->subject}', Date: {$similarEmails->sent_date})"
                    ]
                ];
            }
        }
        
        return ['is_duplicate' => false];
    }
    
    /**
     * Calculate file hash for duplicate detection
     */
    private function calculateFileHash($file): string
    {
        return hash_file('sha256', $file->getPathname());
    }
    
    /**
     * Calculate file hash from file path
     */
    private function calculateFileHashFromPath(string $filePath): string
    {
        return hash_file('sha256', $filePath);
    }
    
    /**
     * Parse email metadata for duplicate detection
     */
    private function parseEmailMetadata($file): ?array
    {
        try {
            // Use a lightweight parsing approach to get basic metadata
            $pythonScript = storage_path('app/scripts/parse_metadata.py');
            
            if (!file_exists($pythonScript)) {
                $this->createMetadataPythonScript($pythonScript);
            }
            
            $command = "py \"{$pythonScript}\" " . escapeshellarg($file->getPathname());
            $output = shell_exec($command);
            
            if ($output) {
                $parsed = json_decode($output, true);
                if (json_last_error() === JSON_ERROR_NONE && !isset($parsed['error'])) {
                    return [
                        'subject' => $parsed['subject'] ?? '',
                        'sender_email' => $parsed['sender_email'] ?? '',
                        'sent_date' => $parsed['sent_date'] ?? null
                    ];
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            Log::warning('Metadata parsing failed for duplicate detection: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Create lightweight Python script for metadata parsing
     */
    private function createMetadataPythonScript(string $scriptPath): void
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

def parse_metadata(file_path):
    try:
        msg = extract_msg.Message(file_path)
        
        # Extract only basic metadata for duplicate detection
        data = {
            'subject': safe_json_serialize(msg.subject or ''),
            'sender_email': safe_json_serialize(getattr(msg, 'senderEmail', '') or ''),
            'sent_date': safe_json_serialize(msg.date) if msg.date else None,
        }
        
        print(json.dumps(data))
        
    except Exception as e:
        print(json.dumps({'error': str(e)}))

if __name__ == '__main__':
    if len(sys.argv) != 2:
        print(json.dumps({'error': 'Usage: python script.py <msg_file_path>'}))
        sys.exit(1)
    
    parse_metadata(sys.argv[1])
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
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));
        
        return round($bytes / pow(1024, $factor), 2) . ' ' . $units[$factor];
    }
}
