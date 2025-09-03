<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\SurveyModel;
use App\Models\User;
class CategoryModel extends Model
{
    use HasFactory;
    protected $table = "categories";
    protected $fillable = [
        'title',
        'descrip_cat',
        'user_create',
     ];
     
    // Definir la relación hasMany 1 a muchas 
    public function surveys()
    {
        return $this->hasMany(SurveyModel::class, 'id_category');
    }

    // Relación belongsTo con User (creador de la categoría) usando el campo user_create
    public function creator()
    {
        return $this->belongsTo(User::class, 'user_create', 'name');
    }
}
