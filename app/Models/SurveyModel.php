<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyModel extends Model
{
    use HasFactory;

    protected $table = "surveys";
    protected $fillable = [
        'title',
        'descrip',
        'id_category',
        'status',
        'publication_status',
        'user_create',
        'start_date',
        'end_date'
    ];
    
    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'status' => 'boolean'
    ];
    
    protected $appends = ['survey_questions_count'];
    
    // Accessor para contar las preguntas
    public function getSurveyQuestionsCountAttribute()
    {
        return $this->surveyQuestions()->count();
    }

    // Definir la relación belongsTo
    public function category()
    {
        return $this->belongsTo(CategoryModel::class, 'id_category');
    }

     // Definir la relación hasMany 1 a muchas 
     public function sections()
     {
         return $this->hasMany(SectionModel::class, 'id_survey');
     }

     public function surveyQuestions()
     {
         return $this->hasMany(SurveyquestionsModel::class, 'survey_id');
     }
     
     public function assignment()
    {
        return $this->belongsTo(AssignmentModel::class);
    }

    // Relación con notificaciones de encuestas
    public function notifications()
    {
        return $this->hasMany(NotificationSurvaysModel::class, 'id_survey');
    }

     // Evento deleting para eliminar en cascada
     protected static function booted()
     {
         static::deleting(function ($survey) {
             // Eliminar secciones relacionadas
             $survey->sections()->each(function ($section) {
                 $section->delete();
             });
 
             // Eliminar preguntas relacionadas
             $survey->surveyQuestions()->each(function ($question) {
                 $question->delete();
             });
         });
     }
 
}
