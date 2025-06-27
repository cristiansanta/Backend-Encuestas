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
        'expired_date' => 'datetime' // Cast expired_date como datetime
    ];

    public $timestamps = false; // Deshabilita los timestamps si no tienes 'created_at' y 'updated_at' en tu tabla

    // Definir la relación belongsTo con SurveyModel
    public function survey()
    {
        return $this->belongsTo(SurveyModel::class, 'id_survey');
    }
}

