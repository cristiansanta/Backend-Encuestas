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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('document_type', [
                'cedula_ciudadania',
                'tarjeta_identidad', 
                'cedula_extranjeria',
                'pep',
                'permiso_proteccion_temporal'
            ])->nullable()->after('email');
            $table->string('document_number')->nullable()->after('document_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'document_number']);
        });
    }
};
