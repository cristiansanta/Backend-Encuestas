<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionsoptionsModel extends Model
{
    use HasFactory;
    protected $table = "question_options";
    protected $fillable = [

        'questions_id',
        'options',
        'creator_id',
        'status',
    
     ];

     // Relación belongsTo con QuestionModel
    public function question()
    {
        return $this->belongsTo(QuestionModel::class, 'questions_id');
    }
}
