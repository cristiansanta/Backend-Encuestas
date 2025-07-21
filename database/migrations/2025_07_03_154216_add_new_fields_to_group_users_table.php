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
            $table->string('tipo_documento')->after('categoria');
            $table->string('numero_documento')->after('tipo_documento');
            $table->string('regional')->nullable()->after('numero_documento');
            $table->string('centro_formacion')->nullable()->after('regional');
            $table->string('programa_formacion')->nullable()->after('centro_formacion');
            $table->string('ficha_grupo')->nullable()->after('programa_formacion');
            
            // Índices para mejorar el rendimiento
            $table->index('tipo_documento');
            $table->index('numero_documento');
            $table->index('regional');
            $table->index('centro_formacion');
            $table->index('programa_formacion');
            $table->index('ficha_grupo');
            
            // Índice único para evitar duplicados por documento
            $table->unique(['tipo_documento', 'numero_documento']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_users', function (Blueprint $table) {
            $table->dropIndex(['tipo_documento', 'numero_documento']);
            $table->dropIndex(['tipo_documento']);
            $table->dropIndex(['numero_documento']);
            $table->dropIndex(['regional']);
            $table->dropIndex(['centro_formacion']);
            $table->dropIndex(['programa_formacion']);
            $table->dropIndex(['ficha_grupo']);
            
            $table->dropColumn([
                'tipo_documento',
                'numero_documento',
                'regional',
                'centro_formacion',
                'programa_formacion',
                'ficha_grupo'
            ]);
        });
    }
};
