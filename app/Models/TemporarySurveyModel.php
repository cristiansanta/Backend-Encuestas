<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemporarySurveyModel extends Model
{
    use HasFactory;

    protected $table = 'temporary_surveys';

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_date',
        'end_date',
        'survey_data',
        'sections',
        'questions',
        'categories',
        'child_question_conditions',
        'status',
        'last_saved_at'
    ];

    protected $casts = [
        'survey_data' => 'array',
        'sections' => 'array',
        'questions' => 'array',
        'categories' => 'array',
        'child_question_conditions' => 'array',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'last_saved_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function updateFromLocalStorage($data)
    {
        $this->survey_data = $data;
        
        // Extract specific fields from the data
        if (isset($data['survey_info'])) {
            $this->title = $data['survey_info']['title'] ?? null;
            $this->description = $data['survey_info']['description'] ?? null;
            $this->start_date = $data['survey_info']['startDate'] ?? null;
            $this->end_date = $data['survey_info']['endDate'] ?? null;
            $this->categories = $data['survey_info']['selectedCategory'] ?? null;
        }
        
        if (isset($data['sections'])) {
            $this->sections = $data['sections'];
        }
        
        if (isset($data['questions'])) {
            $this->questions = $data['questions'];
        }
        
        // Guardar condiciones de preguntas hijas si existen
        if (isset($data['child_question_conditions'])) {
            $this->child_question_conditions = $data['child_question_conditions'];
        }
        
        $this->last_saved_at = now();
        $this->save();
    }
}