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
        'section_id'
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
}

