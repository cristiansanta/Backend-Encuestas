<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentTypeModel extends Model
{
    use HasFactory;
    protected $table = 'assignment_types';
    protected $fillable = [
        'assignment_id',
        'value',
        'type_name',
        'notification'
    ];

    public function assignment()
    {
        return $this->belongsTo(AssignmentModel::class);
    }
}
