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
            // Agregar timestamps estándar de Laravel
            $table->timestamp('created_at')->nullable()->after('scheduled_at');
            $table->timestamp('updated_at')->nullable()->after('created_at');

            // Agregar campo sent_at para tracking de envío
            $table->timestamp('sent_at')->nullable()->after('updated_at');

            // Agregar campo estado con enum para manejo de estados
            $table->enum('estado', ['pending', 'sent', 'delivered', 'failed', 'bounced'])->default('pending')->after('sent_at');

            // Agregar campos para manejo de reintentos y errores
            $table->integer('retry_count')->default(0)->after('estado');
            $table->text('last_error')->nullable()->after('retry_count');
            $table->timestamp('next_retry_at')->nullable()->after('last_error');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificationsurvays', function (Blueprint $table) {
            // Eliminar campos en orden inverso
            $table->dropColumn('next_retry_at');
            $table->dropColumn('last_error');
            $table->dropColumn('retry_count');
            $table->dropColumn('estado');
            $table->dropColumn('sent_at');
            $table->dropColumn('updated_at');
            $table->dropColumn('created_at');
        });
    }
};
