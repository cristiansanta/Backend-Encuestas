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
        Schema::table('notificationsurvays', function (Blueprint $table) {
            // Quitar el valor por defecto de la columna estado
            $table->enum('estado', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->nullable()->default(null)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificationsurvays', function (Blueprint $table) {
            // Restaurar el valor por defecto
            $table->enum('estado', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->default('pending')->change();
        });
    }
};