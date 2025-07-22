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
        Schema::table('questions', function (Blueprint $table) {
            // Add mother_answer_condition field to store the parent answer that triggers this child question
            // (cod_padre and questions_conditions already exist)
            $table->string('mother_answer_condition', 500)->nullable()->after('questions_conditions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropColumn('mother_answer_condition');
        });
    }
};