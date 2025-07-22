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
        Schema::create('question_integrity_audit', function (Blueprint $table) {
            $table->id();
            $table->timestamp('timestamp')->useCurrent();
            $table->string('operation', 50); // CREATE, UPDATE, DELETE, etc.
            $table->unsignedBigInteger('question_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->json('changes'); // JSON con los cambios detectados
            $table->timestamps();
            
            // Índices para mejorar rendimiento de consultas
            $table->index(['question_id', 'timestamp']);
            $table->index(['user_id', 'timestamp']);
            $table->index(['operation', 'timestamp']);
            
            // Claves foráneas
            $table->foreign('question_id')->references('id')->on('questions')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_integrity_audit');
    }
};
