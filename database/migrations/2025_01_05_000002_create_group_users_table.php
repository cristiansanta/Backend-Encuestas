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
        Schema::create('group_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('nombre');
            $table->string('correo');
            $table->string('categoria');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Índices
            $table->index('group_id');
            $table->index('correo');
            $table->index('categoria');
            $table->index('created_by');
            
            // Índice único para evitar duplicados en el mismo grupo
            $table->unique(['group_id', 'correo']);
            
            // Claves foráneas
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_users');
    }
};