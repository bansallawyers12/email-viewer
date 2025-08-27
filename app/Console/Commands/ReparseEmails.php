<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Email;
use App\Services\MsgParserService;
use Illuminate\Support\Facades\Log;

class ReparseEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'emails:reparse {--email-id= : Specific email ID to reparse} {--all : Reparse all emails} {--test-file= : Test parsing on a specific file path}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reparse existing emails with improved parsing logic';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $emailId = $this->option('email-id');
        $reparseAll = $this->option('all');
        $testFile = $this->option('test-file');

        if ($testFile) {
            $this->testFile($testFile);
        } elseif ($emailId) {
            $this->reparseEmail($emailId);
        } elseif ($reparseAll) {
            $this->reparseAllEmails();
        } else {
            $this->error('Please specify --email-id=X, --all, or --test-file=path');
            return 1;
        }

        return 0;
    }

    private function reparseEmail($emailId)
    {
        $email = Email::find($emailId);
        if (!$email) {
            $this->error("Email with ID {$emailId} not found");
            return;
        }

        $this->info("Reparsing email ID {$emailId}: {$email->subject}");
        
        try {
            $parser = new MsgParserService();
            $parsed = $parser->parseWithPython($email->file_path);
            
            if ($parsed) {
                $parser->updateEmailWithParsedData($email, $parsed);
                $this->info("Successfully reparsed email ID {$emailId}");
                
                // Show the parsed data
                $this->table(
                    ['Field', 'Value'],
                    [
                        ['Subject', $parsed['subject'] ?? 'N/A'],
                        ['Sender Name', $parsed['sender_name'] ?? 'N/A'],
                        ['Sender Email', $parsed['sender_email'] ?? 'N/A'],
                        ['Recipients', is_array($parsed['recipients']) ? implode(', ', $parsed['recipients']) : 'N/A'],
                        ['Received Date', $parsed['received_date'] ?? 'N/A'],
                    ]
                );
            } else {
                $this->error("Failed to parse email ID {$emailId}");
            }
        } catch (\Exception $e) {
            $this->error("Error reparsing email ID {$emailId}: " . $e->getMessage());
            Log::error("Failed to reparse email", [
                'email_id' => $emailId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function reparseAllEmails()
    {
        $emails = Email::where('status', 'processed')->get();
        $this->info("Found {$emails->count()} emails to reparse");
        
        $bar = $this->output->createProgressBar($emails->count());
        $bar->start();
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($emails as $email) {
            try {
                $parser = new MsgParserService();
                $parsed = $parser->parseWithPython($email->file_path);
                
                if ($parsed) {
                    $parser->updateEmailWithParsedData($email, $parsed);
                    $successCount++;
                } else {
                    $errorCount++;
                }
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Failed to reparse email", [
                    'email_id' => $email->id,
                    'error' => $e->getMessage()
                ]);
            }
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info("Reparsing completed: {$successCount} successful, {$errorCount} failed");
    }

    private function testFile($filePath)
    {
        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return;
        }

        $this->info("Testing parsing on file: {$filePath}");
        
        try {
            $parser = new MsgParserService();
            $result = $parser->testParsing($filePath);
            
            if ($result['success']) {
                $this->info("Parsing test completed successfully");
                $this->info("Command executed: " . $result['command']);
                
                if ($result['parsed']) {
                    $this->info("Parsed data:");
                    $this->table(
                        ['Field', 'Value'],
                        [
                            ['Subject', $result['parsed']['subject'] ?? 'N/A'],
                            ['Sender Name', $result['parsed']['sender_name'] ?? 'N/A'],
                            ['Sender Email', $result['parsed']['sender_email'] ?? 'N/A'],
                            ['Recipients', is_array($result['parsed']['recipients']) ? implode(', ', $result['parsed']['recipients']) : 'N/A'],
                            ['Received Date', $result['parsed']['received_date'] ?? 'N/A'],
                        ]
                    );
                } else {
                    $this->warn("No parsed data returned");
                    $this->info("Raw output: " . $result['output']);
                    if ($result['json_error']) {
                        $this->error("JSON error: " . $result['json_error_msg']);
                    }
                }
            } else {
                $this->error("Parsing test failed: " . $result['error']);
            }
        } catch (\Exception $e) {
            $this->error("Error testing file: " . $e->getMessage());
        }
    }
}
