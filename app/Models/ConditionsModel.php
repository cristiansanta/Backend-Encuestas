<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConditionsModel extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'conditions';

    // Specify the fields that are mass assignable
    protected $fillable = [
        'id_question_child',
        'operation',
        'compare',
        'cod_father',
        'id_survey'
    ];

    // Optionally, define the relationships or additional model logic
}
