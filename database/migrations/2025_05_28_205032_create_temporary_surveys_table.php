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
        Schema::create('temporary_surveys', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('start_date')->nullable();
            $table->timestamp('end_date')->nullable();
            $table->json('survey_data'); // Stores complete survey structure
            $table->json('sections')->nullable(); // Stores sections array
            $table->json('questions')->nullable(); // Stores questions array
            $table->json('categories')->nullable(); // Stores selected categories
            $table->string('status')->default('draft'); // draft, in_progress, etc
            $table->timestamp('last_saved_at')->nullable();
            $table->timestamps();
            
            // Index for faster queries
            $table->index('user_id');
            $table->index('status');
            $table->index('last_saved_at');
            
            // Foreign key
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temporary_surveys');
    }
};
