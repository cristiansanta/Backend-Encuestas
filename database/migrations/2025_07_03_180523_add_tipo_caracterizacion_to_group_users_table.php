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
        Schema::table('group_users', function (Blueprint $table) {
            $table->string('tipo_caracterizacion')->nullable()->after('ficha_grupo');
            
            // Índice para mejorar el rendimiento en consultas
            $table->index('tipo_caracterizacion');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_users', function (Blueprint $table) {
            $table->dropIndex(['tipo_caracterizacion']);
            $table->dropColumn('tipo_caracterizacion');
        });
    }
};
