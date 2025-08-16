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
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Label name (e.g., "Inbox", "Sent", "Work", "Personal")
            $table->string('color', 7)->default('#3B82F6'); // Hex color code
            $table->string('type')->default('custom'); // 'system' or 'custom'
            $table->string('icon')->nullable(); // FontAwesome icon class
            $table->text('description')->nullable(); // Optional description
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_active']);
            
            // Unique constraint for user + name combination
            $table->unique(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
