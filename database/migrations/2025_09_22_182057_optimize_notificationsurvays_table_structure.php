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
            // 1. Renombrar campo email a destinatario
            $table->renameColumn('email', 'destinatario');

            // 2. Agregar nuevos campos para separar estructura del correo
            $table->string('asunto', 255)->nullable()->after('destinatario');
            $table->text('body')->nullable()->after('asunto');

            // 3. Agregar campo para fecha programada
            $table->timestamp('scheduled_at')->nullable()->after('send_immediately');

            // 4. Optimizar estructura de datos - la columna data se mantendrá para metadatos
            // pero ya no contendrá HTML ni información redundante
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificationsurvays', function (Blueprint $table) {
            // Revertir los cambios en orden inverso
            $table->dropColumn('scheduled_at');
            $table->dropColumn('body');
            $table->dropColumn('asunto');
            $table->renameColumn('destinatario', 'email');
        });
    }
};
