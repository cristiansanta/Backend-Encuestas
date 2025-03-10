<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SectionModel extends Model
{
    use HasFactory;

    protected $table = "sections";
    protected $fillable = [

        'title',
        'descrip_sect',
        'id_survey',
     ];


     public function surveys()
     {
         return $this->belongsTo(SurveyModel::class, 'id_survey');
     }

     public function questions()
     {
         return $this->hasMany(SurveyquestionsModel::class, 'section_id');
     }
   
}
