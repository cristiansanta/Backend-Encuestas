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
            // Permiso para permitir que otros usuarios vean sus preguntas y categorías
            $table->boolean('allow_view_questions_categories')->default(false)->after('document_number')
                ->comment('Permitir que otros usuarios vean las preguntas y categorías creadas por este usuario');
            
            // Permiso para permitir que otros usuarios vean sus encuestas y secciones
            $table->boolean('allow_view_surveys_sections')->default(false)->after('allow_view_questions_categories')
                ->comment('Permitir que otros usuarios vean las encuestas y secciones creadas por este usuario');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['allow_view_questions_categories', 'allow_view_surveys_sections']);
        });
    }
};