<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionModel extends Model
{
    use HasFactory;

    protected $table = "questions";
    protected $fillable = [
        'title',
        'descrip',
        'validate',
        'cod_padre',
        'bank',
        'type_questions_id',
        'creator_id',
        'questions_conditions',
        'mother_answer_condition',
        'section_id',
        'related_question'
     ];
 

      // Opcional: definir la relación hasMany con SurveyQuestionModel si se requiere la relación inversa
    public function surveyQuestions()
    {
        return $this->hasMany(SurveyquestionsModel::class, 'question_id');
    }

    // Relación hasMany con QuestionOptionModel
    public function options()
    {
        return $this->hasMany(QuestionsoptionsModel::class, 'questions_id');
    }

    // Relación belongsTo con TypeQuestionModel
    public function type()
    {
        return $this->belongsTo(TypeQuestionModel::class, 'type_questions_id');
    }

    public function conditions()
    {
        return $this->hasMany(ConditionsModel::class, 'id_question_child');
    }

    // AGREGADO: Relaciones padre-hija para preguntas condicionales
    // Relación belongsTo para obtener la pregunta padre (si esta pregunta es hija)
    public function parentQuestion()
    {
        return $this->belongsTo(QuestionModel::class, 'cod_padre', 'id');
    }

    // Relación hasMany para obtener las preguntas hijas (si esta pregunta es padre)
    public function childQuestions()
    {
        return $this->hasMany(QuestionModel::class, 'cod_padre', 'id');
    }

    // Relación belongsTo con SectionModel
    public function section()
    {
        return $this->belongsTo(SectionModel::class, 'section_id');
    }
}

