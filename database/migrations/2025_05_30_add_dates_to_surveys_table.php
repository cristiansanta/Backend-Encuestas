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
        Schema::table('surveys', function (Blueprint $table) {
            if (!Schema::hasColumn('surveys', 'start_date')) {
                $table->timestamp('start_date')->nullable()->after('status');
            }
            if (!Schema::hasColumn('surveys', 'end_date')) {
                $table->timestamp('end_date')->nullable()->after('start_date');
            }
            if (!Schema::hasColumn('surveys', 'user_create')) {
                $table->string('user_create')->nullable()->after('end_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'end_date', 'user_create']);
        });
    }
};