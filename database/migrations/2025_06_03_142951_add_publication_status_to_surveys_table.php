<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            // Agregar nuevo campo para el estado de publicación
            $table->enum('publication_status', ['draft', 'unpublished', 'published'])->default('draft');
            
            // Índice para búsquedas rápidas por estado
            $table->index('publication_status');
        });
        
        // Migrar datos existentes: 
        // status true -> published
        // status false -> draft
        DB::table('surveys')->where('status', true)->update(['publication_status' => 'published']);
        DB::table('surveys')->where('status', false)->update(['publication_status' => 'draft']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropIndex(['publication_status']);
            $table->dropColumn('publication_status');
        });
    }
};
