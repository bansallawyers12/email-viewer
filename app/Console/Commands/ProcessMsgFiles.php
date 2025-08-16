<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Email;
use App\Models\Attachment;
use App\Services\MsgParserService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ProcessMsgFiles extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'emails:process-files {--user-id=1 : User ID to assign emails to}';

    /**
     * The console command description.
     */
    protected $description = 'Scan storage directory for .msg files and process them into the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting .msg file processing...');
        
        $userId = $this->option('user-id');
        $storagePath = storage_path('app/emails');
        
        if (!is_dir($storagePath)) {
            $this->error("Storage directory not found: {$storagePath}");
            return 1;
        }
        
        // Find all .msg files
        $msgFiles = $this->findMsgFiles($storagePath);
        
        if (empty($msgFiles)) {
            $this->info('No .msg files found in storage directory.');
            return 0;
        }
        
        $this->info("Found " . count($msgFiles) . " .msg files to process.");
        
        $processedCount = 0;
        $errorCount = 0;
        
        $progressBar = $this->output->createProgressBar(count($msgFiles));
        $progressBar->start();
        
        foreach ($msgFiles as $filePath) {
            try {
                $this->processMsgFile($filePath, $userId);
                $processedCount++;
            } catch (\Exception $e) {
                $this->error("Failed to process file {$filePath}: " . $e->getMessage());
                $errorCount++;
                Log::error("Failed to process .msg file", [
                    'file_path' => $filePath,
                    'error' => $e->getMessage()
                ]);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        $this->info("Processing completed!");
        $this->info("Successfully processed: {$processedCount} files");
        if ($errorCount > 0) {
            $this->warn("Failed to process: {$errorCount} files");
        }
        
        return 0;
    }

    /**
     * Find all .msg files in the storage directory.
     */
    private function findMsgFiles(string $directory): array
    {
        $files = [];
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            
            $msgFiles = new RegexIterator($iterator, '/\.msg$/i', RegexIterator::GET_MATCH);
            
            foreach ($msgFiles as $match) {
                $files[] = realpath($match[0]);
            }
        } catch (\Exception $e) {
            $this->error("Error scanning directory: " . $e->getMessage());
        }
        
        return $files;
    }

    /**
     * Process a single .msg file.
     */
    private function processMsgFile(string $filePath, int $userId): void
    {
        // Check if file already exists in database
        $existingEmail = Email::where('file_path', $filePath)->first();
        if ($existingEmail) {
            $this->line("File already processed: " . basename($filePath));
            return;
        }
        
        try {
            $msgParserService = new MsgParserService();
            $msgParserService->parseMsgFile($filePath, $userId);
            
            $this->line("Successfully processed: " . basename($filePath));
            
        } catch (\Exception $e) {
            throw new \Exception("Failed to parse .msg file: " . $e->getMessage());
        }
    }
}
