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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->onDelete('cascade');
            $table->string('filename'); // Original filename
            $table->string('display_name')->nullable(); // Display name from email
            $table->string('content_type')->nullable(); // MIME type
            $table->string('file_path'); // Path to the extracted attachment
            $table->bigInteger('file_size'); // File size in bytes
            $table->string('content_id')->nullable(); // Content-ID for inline attachments
            $table->boolean('is_inline')->default(false); // Whether it's an inline attachment
            $table->text('description')->nullable(); // Attachment description
            $table->json('headers')->nullable(); // Attachment headers as JSON
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['email_id', 'filename']);
            $table->index('content_type');
            $table->index('is_inline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
