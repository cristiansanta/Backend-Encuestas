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
        'email',
        'expired_date',
        'respondent_name',
        'response_data'
    ];

    protected $casts = [
        'email' => 'array', // Cast email como array para manejar múltiples correos
        'data' => 'array',  // Cast data como array si contiene información JSON
        'response_data' => 'array', // Para almacenar las respuestas de la encuesta
        'date_insert' => 'datetime', // Cast date_insert como datetime
        'expired_date' => 'datetime' // Cast expired_date como datetime
    ];

    public $timestamps = false; // Deshabilita los timestamps si no tienes 'created_at' y 'updated_at' en tu tabla

    // Mutator para date_insert - maneja correctamente Carbon con timezone
    public function setDateInsertAttribute($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            // Convertir Carbon a formato con timezone para PostgreSQL
            $this->attributes['date_insert'] = $value->format('Y-m-d H:i:s T');
        } else {
            $this->attributes['date_insert'] = $value;
        }
    }

    // Mutator para expired_date - maneja correctamente Carbon con timezone
    public function setExpiredDateAttribute($value)
    {
        if ($value instanceof \Carbon\Carbon) {
            // Convertir Carbon a formato con timezone para PostgreSQL
            $this->attributes['expired_date'] = $value->format('Y-m-d H:i:s T');
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

