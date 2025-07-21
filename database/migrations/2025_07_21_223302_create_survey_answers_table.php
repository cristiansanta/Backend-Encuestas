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
        Schema::create('survey_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('survey_question_id');
            $table->json('answer')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
            $table->foreign('survey_question_id')->references('id')->on('survey_questions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_answers');
    }
};
