<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurveyAnswersModel extends Model
{
    use HasFactory;
    protected $table = "survey_answers";
    protected $fillable = [

        'survey_question_id',
        'answer',
        'user_id',
        'status',
    
     ];
     protected $casts = [
        'answer' => 'array', // Cast `related_question` as array
    ];

     
}
