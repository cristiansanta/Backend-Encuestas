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
        Schema::table('temporary_surveys', function (Blueprint $table) {
            $table->json('child_question_conditions')->nullable()->after('categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('temporary_surveys', function (Blueprint $table) {
            $table->dropColumn('child_question_conditions');
        });
    }
};