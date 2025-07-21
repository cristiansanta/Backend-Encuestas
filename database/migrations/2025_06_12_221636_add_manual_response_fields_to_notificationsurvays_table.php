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
            $table->string('respondent_name')->nullable()->after('email');
            $table->json('response_data')->nullable()->after('respondent_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notificationsurvays', function (Blueprint $table) {
            $table->dropColumn(['respondent_name', 'response_data']);
        });
    }
};
