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
        // Use raw SQL to change column type from varchar(255) to text
        DB::statement('ALTER TABLE notificationsurvays ALTER COLUMN data TYPE TEXT');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Note: This will fail if there's data longer than 255 chars
        DB::statement('ALTER TABLE notificationsurvays ALTER COLUMN data TYPE VARCHAR(255)');
    }
};
