<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class SectionModel extends Model
{
    use HasFactory;

    protected $table = "sections";
    protected $fillable = [
        'title',
        'descrip_sect',
        'id_survey',
        'user_create', // Campo para la lógica de inteligencia de negocio - cada usuario ve solo sus secciones
     ];


     public function surveys()
     {
         return $this->belongsTo(SurveyModel::class, 'id_survey');
     }
     
     public function survey()
     {
         return $this->belongsTo(SurveyModel::class, 'id_survey');
     }

     public function questions()
     {
         return $this->hasMany(SurveyquestionsModel::class, 'section_id');
     }

     // Relación belongsTo con User (creador de la sección) usando el campo user_create
     public function creator()
     {
         return $this->belongsTo(User::class, 'user_create', 'name');
     }

}
