<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupUserModel extends Model
{
    use HasFactory;

    protected $table = 'group_users';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'group_id',
        'nombre',
        'correo',
        'categoria',
        'created_by',
        'tipo_documento',
        'numero_documento',
        'regional',
        'centro_formacion',
        'programa_formacion',
        'ficha_grupo',
        'tipo_caracterizacion'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'group_id' => 'integer'
    ];

    // Relación con el grupo
    public function group()
    {
        return $this->belongsTo(GroupModel::class, 'group_id');
    }

    // Relación con el usuario que agregó este registro
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Validación de email
    public static function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}