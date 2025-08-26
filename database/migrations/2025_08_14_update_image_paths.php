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
        // Actualizar rutas de imágenes en la tabla surveys
        DB::statement("
            UPDATE surveys 
            SET descrip = REPLACE(descrip, '/storage/images/', '/api/storage/images/')
            WHERE descrip LIKE '%/storage/images/%'
        ");

        // Actualizar rutas de imágenes en la tabla questions
        DB::statement("
            UPDATE questions 
            SET descrip = REPLACE(descrip, '/storage/images/', '/api/storage/images/')
            WHERE descrip LIKE '%/storage/images/%'
        ");

        echo "Rutas de imágenes actualizadas correctamente.\n";
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir cambios
        DB::statement("
            UPDATE surveys 
            SET descrip = REPLACE(descrip, '/api/storage/images/', '/storage/images/')
            WHERE descrip LIKE '%/api/storage/images/%'
        ");

        DB::statement("
            UPDATE questions 
            SET descrip = REPLACE(descrip, '/api/storage/images/', '/storage/images/')
            WHERE descrip LIKE '%/api/storage/images/%'
        ");

        echo "Rutas de imágenes revertidas.\n";
    }
};