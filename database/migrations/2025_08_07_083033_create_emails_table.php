<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subject')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->text('recipients')->nullable(); // JSON array of recipients
            $table->text('cc')->nullable(); // JSON array of CC recipients
            $table->text('bcc')->nullable(); // JSON array of BCC recipients
            $table->datetime('sent_date')->nullable();
            $table->datetime('received_date')->nullable();
            $table->string('file_path'); // Path to the .msg file
            $table->string('file_name'); // Original filename
            $table->bigInteger('file_size'); // File size in bytes
            $table->longText('html_content')->nullable(); // HTML body content
            $table->text('text_content')->nullable(); // Plain text body content
            $table->text('raw_content')->nullable(); // Raw email content
            $table->json('headers')->nullable(); // Email headers as JSON
            $table->string('message_id')->nullable(); // Unique message ID
            $table->string('thread_id')->nullable(); // Thread/conversation ID
            $table->json('tags')->nullable(); // User-defined tags
            $table->enum('status', ['processed', 'processing', 'error'])->default('processing');
            $table->text('error_message')->nullable(); // Error message if parsing failed
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'sent_date']);
            $table->index(['user_id', 'subject']);
            $table->index(['user_id', 'sender_email']);
            $table->index('message_id');
            $table->index('thread_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
