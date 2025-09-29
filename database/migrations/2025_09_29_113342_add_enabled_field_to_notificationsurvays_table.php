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
            // Campo para controlar si el encuestado está habilitado para responder la encuesta
            $table->boolean('enabled')->default(true)->after('respondent_name')
                  ->comment('Indica si el encuestado está habilitado para responder la encuesta');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificationsurvays', function (Blueprint $table) {
            $table->dropColumn('enabled');
        });
    }
};
