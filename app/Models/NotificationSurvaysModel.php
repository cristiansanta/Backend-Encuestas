<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class NotificationSurvaysModel extends Model
{
    use HasFactory;
    protected $table = 'notificationsurvays';
  

    protected $fillable = [
        'data',
        'state',
        'state_results',
        'date_insert',
        'id_survey',
        'destinatario', // Renombrado de email a destinatario
        'asunto', // Nuevo campo para asunto del correo
        'body', // Nuevo campo para cuerpo del mensaje
        'expired_date',
        'respondent_name',
        'enabled', // Campo para controlar si el encuestado está habilitado
        'previous_status', // Campo para guardar el estado anterior antes de deshabilitar
        'response_data',
        'scheduled_sending',
        'scheduled_date',
        'send_immediately',
        'scheduled_at', // Nuevo campo para fecha/hora programada
        'created_at', // Timestamp de creación
        'updated_at', // Timestamp de actualización
        'sent_at', // Timestamp de envío
        'estado', // Estado del envío (pending, sent, delivered, failed, bounced)
        'retry_count', // Contador de reintentos
        'last_error', // Último error
        'next_retry_at' // Próximo reintento
    ];

    protected $casts = [
        'destinatario' => 'string', // Cast destinatario como string
        'asunto' => 'string', // Cast asunto como string
        'body' => 'string', // Cast body como string
        'data' => 'array',  // Cast data como array - ahora solo para metadatos optimizados
        'response_data' => 'array', // Para almacenar las respuestas de la encuesta
        'date_insert' => 'datetime', // Cast date_insert como datetime
        'expired_date' => 'datetime', // Cast expired_date como datetime
        'scheduled_at' => 'datetime', // Cast scheduled_at como datetime
        'created_at' => 'datetime', // Cast created_at como datetime
        'updated_at' => 'datetime', // Cast updated_at como datetime
        'sent_at' => 'datetime', // Cast sent_at como datetime
        'next_retry_at' => 'datetime', // Cast next_retry_at como datetime
        'estado' => 'string', // Cast estado como enum env_estado ('pendiente', 'en_proceso', 'enviado', 'fallo', 'cancelado')
        'retry_count' => 'integer', // Cast retry_count como integer
        'last_error' => 'string', // Cast last_error como string
        'state_results' => 'string', // Cast state_results como string (no boolean)
        'enabled' => 'boolean', // Cast enabled como boolean
        'previous_status' => 'string', // Cast previous_status como string
        'scheduled_sending' => 'boolean', // Cast para envío programado
        'scheduled_date' => 'datetime', // Cast para fecha programada
        'send_immediately' => 'boolean' // Cast para envío inmediato
    ];

    public $timestamps = false; // Deshabilita los timestamps si no tienes 'created_at' y 'updated_at' en tu tabla

    // Mutator para date_insert - formato simple para PostgreSQL
    public function setDateInsertAttribute($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            // Formato ISO 8601 estándar sin timezone
            $this->attributes['date_insert'] = $value->format('Y-m-d H:i:s');
        } else {
            $this->attributes['date_insert'] = $value;
        }
    }

    // Mutator para expired_date - formato simple para PostgreSQL
    public function setExpiredDateAttribute($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            // Formato ISO 8601 estándar sin timezone
            $this->attributes['expired_date'] = $value->format('Y-m-d H:i:s');
        } else {
            $this->attributes['expired_date'] = $value;
        }
    }

    // Definir la relación belongsTo con SurveyModel
    public function survey()
    {
        return $this->belongsTo(SurveyModel::class, 'id_survey');
    }

    /**
     * Boot method - Define model events
     */
    protected static function boot()
    {
        parent::boot();

        // Evento que se ejecuta cuando se elimina una notificación
        static::deleted(function ($notification) {
            self::cleanupSurveyAccessTokens($notification);
        });

        // Evento que se ejecuta cuando se elimina una notificación por query builder
        static::deleting(function ($notification) {
            self::cleanupSurveyAccessTokens($notification);
        });
    }

    /**
     * Limpiar tokens de acceso relacionados cuando se elimina una notificación
     */
    protected static function cleanupSurveyAccessTokens($notification)
    {
        try {
            // Obtener información de la notificación antes de eliminarla
            $surveyId = $notification->id_survey;
            $email = $notification->destinatario;

            if ($surveyId && $email) {
                // Eliminar tokens de acceso relacionados con esta combinación survey/email
                $deletedTokens = \App\Models\SurveyAccessToken::where('survey_id', $surveyId)
                    ->where('email', $email)
                    ->delete();

                if ($deletedTokens > 0) {
                    try {
                        Log::info('🧹 CLEANUP: Survey access tokens automatically cleaned', [
                            'survey_id' => $surveyId,
                            'email' => $email,
                            'tokens_deleted' => $deletedTokens,
                            'trigger' => 'notification_deleted'
                        ]);
                    } catch (\Exception $logError) {
                        // Continuar aunque falle el logging
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('❌ Error cleaning up survey access tokens', [
                'error' => $e->getMessage(),
                'notification_id' => $notification->id ?? 'unknown'
            ]);
        }
    }

    /**
     * Método público para limpiar tokens de una encuesta completa
     * Útil para eliminaciones masivas o limpieza manual
     */
    public static function cleanupSurveyTokens($surveyId)
    {
        try {
            $deletedTokens = \App\Models\SurveyAccessToken::where('survey_id', $surveyId)->delete();

            if ($deletedTokens > 0) {
                try {
                    Log::info('🧹 CLEANUP: All survey access tokens cleaned for survey', [
                        'survey_id' => $surveyId,
                        'tokens_deleted' => $deletedTokens,
                        'trigger' => 'manual_cleanup'
                    ]);
                } catch (\Exception $logError) {
                    // Continuar aunque falle el logging
                }
            }

            return $deletedTokens;
        } catch (\Exception $e) {
            Log::error('❌ Error cleaning up survey tokens', [
                'error' => $e->getMessage(),
                'survey_id' => $surveyId
            ]);
            return 0;
        }
    }
}

