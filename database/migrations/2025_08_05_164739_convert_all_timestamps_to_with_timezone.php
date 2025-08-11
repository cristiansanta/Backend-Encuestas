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
        // Convertir todas las columnas timestamp without time zone a timestamp with time zone
        
        $statements = [
            // assignment_types
            'ALTER TABLE "Produc"."assignment_types" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."assignment_types" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // assignments
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN end_date TYPE timestamp with time zone USING end_date AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN start_date TYPE timestamp with time zone USING start_date AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // categories
            'ALTER TABLE "Produc"."categories" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."categories" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // conditions
            'ALTER TABLE "Produc"."conditions" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."conditions" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // failed_jobs
            'ALTER TABLE "Produc"."failed_jobs" ALTER COLUMN failed_at TYPE timestamp with time zone USING failed_at AT TIME ZONE \'America/Bogota\'',
            
            // group_users
            'ALTER TABLE "Produc"."group_users" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."group_users" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // groups
            'ALTER TABLE "Produc"."groups" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."groups" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // notificationsurvays (ya convertidas, pero por consistencia)
            'ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN date_insert TYPE timestamp with time zone USING date_insert AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN expired_date TYPE timestamp with time zone USING expired_date AT TIME ZONE \'America/Bogota\'',
            
            // password_reset_tokens
            'ALTER TABLE "Produc"."password_reset_tokens" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            
            // permissions
            'ALTER TABLE "Produc"."permissions" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."permissions" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // personal_access_tokens
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN expires_at TYPE timestamp with time zone USING expires_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN last_used_at TYPE timestamp with time zone USING last_used_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // question_integrity_audit
            'ALTER TABLE "Produc"."question_integrity_audit" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."question_integrity_audit" ALTER COLUMN timestamp TYPE timestamp with time zone USING timestamp AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."question_integrity_audit" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // question_options
            'ALTER TABLE "Produc"."question_options" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."question_options" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // questions
            'ALTER TABLE "Produc"."questions" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."questions" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // roles
            'ALTER TABLE "Produc"."roles" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."roles" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // sections
            'ALTER TABLE "Produc"."sections" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."sections" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // survey_answers
            'ALTER TABLE "Produc"."survey_answers" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."survey_answers" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // survey_questions
            'ALTER TABLE "Produc"."survey_questions" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."survey_questions" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // survey_respondents
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN responded_at TYPE timestamp with time zone USING responded_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN sent_at TYPE timestamp with time zone USING sent_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // surveys
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN end_date TYPE timestamp with time zone USING end_date AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN start_date TYPE timestamp with time zone USING start_date AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // temporary_surveys
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN end_date TYPE timestamp with time zone USING end_date AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN last_saved_at TYPE timestamp with time zone USING last_saved_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN start_date TYPE timestamp with time zone USING start_date AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // type_questions
            'ALTER TABLE "Produc"."type_questions" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."type_questions" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\'',
            
            // users
            'ALTER TABLE "Produc"."users" ALTER COLUMN created_at TYPE timestamp with time zone USING created_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."users" ALTER COLUMN email_verified_at TYPE timestamp with time zone USING email_verified_at AT TIME ZONE \'America/Bogota\'',
            'ALTER TABLE "Produc"."users" ALTER COLUMN updated_at TYPE timestamp with time zone USING updated_at AT TIME ZONE \'America/Bogota\''
        ];
        
        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (\Exception $e) {
                // Log error but continue with other statements
                echo "Error en: " . $statement . " - " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir todas las columnas a timestamp without time zone
        $statements = [
            'ALTER TABLE "Produc"."assignment_types" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."assignment_types" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN end_date TYPE timestamp without time zone USING end_date::timestamp',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN start_date TYPE timestamp without time zone USING start_date::timestamp',
            'ALTER TABLE "Produc"."assignments" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."categories" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."categories" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."conditions" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."conditions" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."failed_jobs" ALTER COLUMN failed_at TYPE timestamp without time zone USING failed_at::timestamp',
            'ALTER TABLE "Produc"."group_users" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."group_users" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."groups" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."groups" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN date_insert TYPE timestamp without time zone USING date_insert::timestamp',
            'ALTER TABLE "Produc"."notificationsurvays" ALTER COLUMN expired_date TYPE timestamp without time zone USING expired_date::timestamp',
            'ALTER TABLE "Produc"."password_reset_tokens" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."permissions" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."permissions" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN expires_at TYPE timestamp without time zone USING expires_at::timestamp',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN last_used_at TYPE timestamp without time zone USING last_used_at::timestamp',
            'ALTER TABLE "Produc"."personal_access_tokens" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."question_integrity_audit" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."question_integrity_audit" ALTER COLUMN timestamp TYPE timestamp without time zone USING timestamp::timestamp',
            'ALTER TABLE "Produc"."question_integrity_audit" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."question_options" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."question_options" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."questions" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."questions" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."roles" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."roles" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."sections" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."sections" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."survey_answers" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."survey_answers" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."survey_questions" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."survey_questions" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN responded_at TYPE timestamp without time zone USING responded_at::timestamp',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN sent_at TYPE timestamp without time zone USING sent_at::timestamp',
            'ALTER TABLE "Produc"."survey_respondents" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN end_date TYPE timestamp without time zone USING end_date::timestamp',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN start_date TYPE timestamp without time zone USING start_date::timestamp',
            'ALTER TABLE "Produc"."surveys" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN end_date TYPE timestamp without time zone USING end_date::timestamp',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN last_saved_at TYPE timestamp without time zone USING last_saved_at::timestamp',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN start_date TYPE timestamp without time zone USING start_date::timestamp',
            'ALTER TABLE "Produc"."temporary_surveys" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."type_questions" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."type_questions" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp',
            'ALTER TABLE "Produc"."users" ALTER COLUMN created_at TYPE timestamp without time zone USING created_at::timestamp',
            'ALTER TABLE "Produc"."users" ALTER COLUMN email_verified_at TYPE timestamp without time zone USING email_verified_at::timestamp',
            'ALTER TABLE "Produc"."users" ALTER COLUMN updated_at TYPE timestamp without time zone USING updated_at::timestamp'
        ];
        
        foreach ($statements as $statement) {
            try {
                DB::statement($statement);
            } catch (\Exception $e) {
                echo "Error en rollback: " . $statement . " - " . $e->getMessage() . "\n";
            }
        }
    }
};
