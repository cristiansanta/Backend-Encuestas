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
        Schema::create('survey_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_id');
            $table->string('email');
            $table->string('hash'); // Hash proporcionado en el enlace
            $table->string('device_fingerprint'); // Device fingerprint del primer acceso
            $table->string('ip_address');
            $table->text('user_agent');
            $table->timestamp('first_access_at');
            $table->timestamp('last_access_at')->nullable();
            $table->integer('access_count')->default(1);
            $table->enum('status', ['active', 'blocked', 'expired'])->default('active');
            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->unique(['survey_id', 'email', 'hash'], 'survey_email_hash_unique');
            $table->index(['survey_id', 'email']);
            $table->index('device_fingerprint');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_access_tokens');
    }
};
