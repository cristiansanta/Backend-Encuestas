<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TypeQuestionModel extends Model
{
    use HasFactory;

    protected $table = "type_questions";
    protected $fillable = [

        'title',
        'descrip',

    ];

    // Relación hasMany con QuestionModel
    public function questions()
    {
        return $this->hasMany(QuestionModel::class, 'type_questions_id');
    }
}
