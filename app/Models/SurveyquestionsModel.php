<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyquestionsModel extends Model
{
    use HasFactory;
    protected $table = "survey_questions";
    protected $fillable = [

        'survey_id',
        'question_id',
        'section_id',
        'creator_id',
        'status',
        'user_id',
     

    ];

    public function survey()
    {
        return $this->belongsTo(SurveyModel::class, 'survey_id');
    }

    public function section()
    {
        return $this->belongsTo(SectionModel::class, 'section_id');
    }

    // Definir la relación belongsTo con QuestionModel
    public function question()
    {
        return $this->belongsTo(QuestionModel::class, 'question_id');
    }
    
}
