<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey_respondents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained('surveys')->onDelete('cascade');
            $table->string('respondent_name');
            $table->string('respondent_email');
            $table->enum('status', ['Enviada', 'Contestada'])->default('Enviada');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->foreignId('notification_id')->nullable()->constrained('notificationsurvays')->onDelete('set null');
            $table->foreignId('group_id')->nullable()->constrained('groups')->onDelete('set null');
            $table->string('group_name')->nullable();
            $table->json('response_data')->nullable();
            $table->string('email_token')->nullable()->unique();
            $table->timestamps();

            // Índices para mejorar el rendimiento
            $table->index(['survey_id', 'status']);
            $table->index(['respondent_email']);
            $table->index(['status']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('survey_respondents');
    }
};