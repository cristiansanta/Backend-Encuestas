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
            // Remover el permiso para encuestas y secciones ya que por defecto no deben ser vistas por otros usuarios
            $table->dropColumn('allow_view_surveys_sections');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('allow_view_surveys_sections')->default(false)->after('allow_view_questions_categories')
                ->comment('Permitir que otros usuarios vean las encuestas y secciones creadas por este usuario');
        });
    }
};