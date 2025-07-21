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
        // Para PostgreSQL: Eliminar el constraint existente y crear uno nuevo con los valores adicionales
        DB::statement("ALTER TABLE surveys DROP CONSTRAINT IF EXISTS surveys_publication_status_check");
        DB::statement("ALTER TABLE surveys ADD CONSTRAINT surveys_publication_status_check CHECK (publication_status IN ('draft', 'unpublished', 'published', 'finished', 'scheduled'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Para PostgreSQL: Revertir el constraint a los valores originales
        DB::statement("ALTER TABLE surveys DROP CONSTRAINT IF EXISTS surveys_publication_status_check");
        DB::statement("ALTER TABLE surveys ADD CONSTRAINT surveys_publication_status_check CHECK (publication_status IN ('draft', 'unpublished', 'published'))");
    }
};
