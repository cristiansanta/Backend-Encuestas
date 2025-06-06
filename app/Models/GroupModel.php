<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupModel extends Model
{
    use HasFactory;

    protected $table = 'groups';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'user_count'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'user_count' => 'integer'
    ];

    // Relación con usuarios del grupo
    public function users()
    {
        return $this->hasMany(GroupUserModel::class, 'group_id');
    }

    // Relación con el usuario que creó el grupo
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Método para actualizar el contador de usuarios
    public function updateUserCount()
    {
        $this->user_count = $this->users()->count();
        $this->save();
    }
}