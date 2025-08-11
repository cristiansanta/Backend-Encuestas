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
        Schema::connection('pgsql')->table('notificationsurvays', function (Blueprint $table) {
            // Cambiar date_insert de timestamp without time zone a timestamp with time zone
            DB::statement('ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN date_insert TYPE timestamp with time zone USING date_insert AT TIME ZONE \'America/Bogota\'');
            
            // Cambiar expired_date de timestamp without time zone a timestamp with time zone  
            DB::statement('ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN expired_date TYPE timestamp with time zone USING expired_date AT TIME ZONE \'America/Bogota\'');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('pgsql')->table('notificationsurvays', function (Blueprint $table) {
            // Revertir date_insert a timestamp without time zone
            DB::statement('ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN date_insert TYPE timestamp without time zone USING date_insert::timestamp');
            
            // Revertir expired_date a timestamp without time zone
            DB::statement('ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN expired_date TYPE timestamp without time zone USING expired_date::timestamp');
        });
    }
};
