<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyRespondentModel extends Model
{
    use HasFactory;
    
    protected $table = 'survey_respondents';

    protected $fillable = [
        'survey_id',
        'respondent_name',
        'respondent_email',
        'status', // 'Enviada', 'Contestada'
        'sent_at',
        'responded_at',
        'notification_id',
        'group_id',
        'group_name',
        'response_data',
        'email_token'
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'responded_at' => 'datetime',
        'response_data' => 'array',
    ];

    public $timestamps = true;

    // Relación con la encuesta
    public function survey()
    {
        return $this->belongsTo(SurveyModel::class, 'survey_id');
    }

    // Relación con la notificación
    public function notification()
    {
        return $this->belongsTo(NotificationSurvaysModel::class, 'notification_id');
    }

    // Relación con el grupo
    public function group()
    {
        return $this->belongsTo(GroupModel::class, 'group_id');
    }

    // Scopes para filtrar por estado
    public function scopeEnviada($query)
    {
        return $query->where('status', 'Enviada');
    }

    public function scopeContestada($query)
    {
        return $query->where('status', 'Contestada');
    }

    // Métodos helper
    public function markAsResponded($responseData = null)
    {
        $this->update([
            'status' => 'Contestada',
            'responded_at' => now(),
            'response_data' => $responseData
        ]);
    }

    public function isResponded()
    {
        return $this->status === 'Contestada';
    }

    public function isSent()
    {
        return $this->status === 'Enviada';
    }
}