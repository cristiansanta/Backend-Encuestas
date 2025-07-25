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
        Schema::table('notificationsurvays', function (Blueprint $table) {
            // $table->json('response_data')->nullable()->after('respondent_name'); // Ya existe en la migración de creación
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificationsurvays', function (Blueprint $table) {
            $table->dropColumn(['response_data']);
        });
    }
};
