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
        Schema::create('notificationsurvays', function (Blueprint $table) {
            $table->id();
            $table->json('data')->nullable();
            $table->string('state')->nullable();
            $table->string('state_results')->nullable();
            $table->timestamp('date_insert')->nullable();
            $table->unsignedBigInteger('id_survey')->nullable();
            $table->json('email')->nullable();
            $table->timestamp('expired_date')->nullable();
            $table->string('respondent_name')->nullable();
            $table->json('response_data')->nullable();
            // Relación con surveys (si existe la tabla surveys)
            $table->foreign('id_survey')->references('id')->on('surveys')->onDelete('set null');
            // No timestamps automáticos
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificationsurvays');
    }
};
