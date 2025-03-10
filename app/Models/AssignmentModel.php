<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignmentModel extends Model
{
    use HasFactory;
    protected $table = 'assignments';
    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'number_of_attempts',
        'is_anonymous', 
        'enable_notification', 
        'days_enabled',
        'days_activation', 
        'days_notification'
    ];

    public function types()
    {
        return $this->hasMany(AssignmentTypeModel::class);
    }
}
