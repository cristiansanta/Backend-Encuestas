<?php

namespace App\Models;
use Spatie\Permission\Models\Permission as SpatiePermission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends SpatiePermission
{
    use HasFactory;
    protected $fillable = ['name', 'guard_name'];
}
