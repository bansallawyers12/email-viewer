<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Email;
use App\Models\Label;
use Illuminate\Support\Facades\Log;

class AutoLabelEmails extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'emails:auto-label {--user-id= : Specific user ID to process}';

    /**
     * The console command description.
     */
    protected $description = 'Automatically label emails as Inbox or Sent based on sender domain';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting automatic email labeling...');
        
        $userId = $this->option('user-id');
        
        if ($userId) {
            $users = collect([\App\Models\User::find($userId)]);
            if (!$users->first()) {
                $this->error("User with ID {$userId} not found.");
                return 1;
            }
        } else {
            $users = \App\Models\User::all();
        }
        
        $totalProcessed = 0;
        $totalLabeled = 0;
        
        foreach ($users as $user) {
            $this->info("Processing emails for user: {$user->name} (ID: {$user->id})");
            
            // Get system labels for this user
            $inboxLabel = Label::forUser($user->id)->where('name', 'Inbox')->first();
            $sentLabel = Label::forUser($user->id)->where('name', 'Sent')->first();
            
            if (!$inboxLabel || !$sentLabel) {
                $this->error("System labels not found for user {$user->id}. Run the label seeder first.");
                continue;
            }
            
            // Get all emails for this user
            $emails = Email::where('user_id', $user->id)->get();
            $userProcessed = 0;
            $userLabeled = 0;
            
            $progressBar = $this->output->createProgressBar($emails->count());
            $progressBar->start();
            
            foreach ($emails as $email) {
                try {
                    $userProcessed++;
                    
                    // Check if email already has primary labels (Inbox/Sent)
                    $hasPrimaryLabel = $email->labels()
                        ->whereIn('name', ['Inbox', 'Sent'])
                        ->exists();
                    
                    if ($hasPrimaryLabel) {
                        $progressBar->advance();
                        continue;
                    }
                    
                    // Determine the appropriate label
                    $labelToApply = null;
                    
                    if ($email->isSentItem()) {
                        $labelToApply = $sentLabel;
                        $this->line("Email {$email->id} is a sent item - applying 'Sent' label");
                    } else {
                        $labelToApply = $inboxLabel;
                        $this->line("Email {$email->id} is an inbox item - applying 'Inbox' label");
                    }
                    
                    // Apply the label
                    if ($labelToApply) {
                        $email->labels()->attach($labelToApply->id);
                        $userLabeled++;
                        $totalLabeled++;
                        $this->info("✓ Applied '{$labelToApply->name}' label to email {$email->id}");
                    }
                    
                } catch (Exception $e) {
                    $this->error("Failed to label email {$email->id}: " . $e->getMessage());
                    Log::error("Auto-label failed for email {$email->id}", [
                        'error' => $e->getMessage(),
                        'user_id' => $user->id
                    ]);
                }
                
                $progressBar->advance();
            }
            
            $progressBar->finish();
            $this->newLine();
            
            $this->info("User {$user->name}: {$userProcessed} emails processed, {$userLabeled} labeled");
            $totalProcessed += $userProcessed;
        }
        
        $this->newLine(2);
        $this->info("Auto-labeling completed!");
        $this->info("Total emails processed: {$totalProcessed}");
        $this->info("Total emails labeled: {$totalLabeled}");
        
        return 0;
    }
}
