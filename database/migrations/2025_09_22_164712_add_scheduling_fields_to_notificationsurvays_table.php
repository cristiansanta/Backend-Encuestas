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
        Schema::connection('pgsql')->table('notificationsurvays', function (Blueprint $table) {
            // Agregar nuevas columnas para programación de envíos
            $table->boolean('scheduled_sending')->nullable()->default(false);
            $table->timestampTz('scheduled_date')->nullable();
            $table->boolean('send_immediately')->nullable()->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->table('notificationsurvays', function (Blueprint $table) {
            // Eliminar las columnas agregadas
            $table->dropColumn(['scheduled_sending', 'scheduled_date', 'send_immediately']);
        });
    }
};
