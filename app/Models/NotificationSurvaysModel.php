<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'response_data',
        'scheduled_sending',
        'scheduled_date',
        'send_immediately',
        'scheduled_at' // Nuevo campo para fecha/hora programada
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
        'state_results' => 'string', // Cast state_results como string (no boolean)
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
}

