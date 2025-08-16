<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Email;
use App\Models\Attachment; // Added this import
use App\Services\MsgParserService;
use Illuminate\Support\Facades\Log;

class ReprocessEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:reprocess {--id= : Specific email ID to reprocess}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reprocess emails that are stuck in processing status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting email reprocessing...');
        
        $emails = Email::all();
        $totalEmails = $emails->count();
        $processedCount = 0;
        $errorCount = 0;
        
        $progressBar = $this->output->createProgressBar($totalEmails);
        $progressBar->start();
        
        foreach ($emails as $email) {
            try {
                $this->reprocessEmail($email);
                $processedCount++;
            } catch (Exception $e) {
                $this->error("Failed to reprocess email {$email->id}: " . $e->getMessage());
                $errorCount++;
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("Reprocessing completed!");
        $this->info("Successfully processed: {$processedCount} emails");
        if ($errorCount > 0) {
            $this->warn("Failed to process: {$errorCount} emails");
        }
        
        // Now reprocess attachments to update MIME types
        $this->info('Starting attachment MIME type reprocessing...');
        $this->reprocessAttachments();
        
        return 0;
    }

    /**
     * Reprocess a single email.
     */
    private function reprocessEmail(Email $email)
    {
        try {
            if (!file_exists($email->file_path)) {
                $this->error("File not found: {$email->file_path}");
                $email->update(['status' => 'error', 'error_message' => 'File not found']);
                return;
            }
            
            $msgParserService = new MsgParserService();
            $msgParserService->parseMsgFile($email->file_path, $email->user_id);
            $this->info("Successfully reprocessed email ID: {$email->id}");
            
        } catch (\Exception $e) {
            $this->error("Failed to reprocess email ID {$email->id}: {$e->getMessage()}");
            Log::error("Reprocess failed for email {$email->id}", [
                'error' => $e->getMessage(),
                'file_path' => $email->file_path
            ]);
        }
    }

    /**
     * Reprocess attachments to update MIME types.
     */
    private function reprocessAttachments()
    {
        $attachments = Attachment::all();
        $totalAttachments = $attachments->count();
        $updatedCount = 0;
        
        if ($totalAttachments === 0) {
            $this->info('No attachments found to reprocess.');
            return;
        }
        
        $progressBar = $this->output->createProgressBar($totalAttachments);
        $progressBar->start();
        
        foreach ($attachments as $attachment) {
            try {
                if (file_exists($attachment->file_path)) {
                    $oldContentType = $attachment->content_type;
                    $newContentType = $this->detectMimeType($attachment->file_path, $oldContentType);
                    
                    if ($newContentType !== $oldContentType) {
                        $attachment->update(['content_type' => $newContentType]);
                        $updatedCount++;
                        
                        $this->line("Updated attachment {$attachment->filename}: {$oldContentType} → {$newContentType}");
                    }
                }
            } catch (Exception $e) {
                $this->error("Failed to reprocess attachment {$attachment->id}: " . $e->getMessage());
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("Attachment reprocessing completed!");
        $this->info("Updated MIME types for: {$updatedCount} attachments");
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
}
