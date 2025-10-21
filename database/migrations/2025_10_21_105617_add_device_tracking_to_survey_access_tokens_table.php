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
        Schema::table('survey_access_tokens', function (Blueprint $table) {
            // Contador de cambios de dispositivo
            $table->integer('device_changes_count')->default(0)->after('access_count');

            // Timestamp del último cambio de dispositivo
            $table->timestamp('last_device_change_at')->nullable()->after('device_changes_count');

            // Device fingerprint anterior (para comparación)
            $table->string('previous_device_fingerprint', 16)->nullable()->after('last_device_change_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('survey_access_tokens', function (Blueprint $table) {
            $table->dropColumn(['device_changes_count', 'last_device_change_at', 'previous_device_fingerprint']);
        });
    }
};
