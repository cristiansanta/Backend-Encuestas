<?php

namespace App\Http\Controllers;

use App\Models\TemporarySurveyModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemporarySurveyController extends Controller
{
    public function index()
    {
        $temporarySurveys = TemporarySurveyModel::where('user_id', Auth::id())
            ->orderBy('last_saved_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $temporarySurveys
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'survey_data' => 'required|array',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'sections' => 'nullable|array',
            'questions' => 'nullable|array',
            'categories' => 'nullable|array',
            'status' => 'nullable|string|in:draft,in_progress'
        ]);

        $temporarySurvey = TemporarySurveyModel::create([
            'user_id' => Auth::id(),
            'survey_data' => $validated['survey_data'],
            'title' => $validated['title'] ?? null,
            'description' => $validated['description'] ?? null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
            'sections' => $validated['sections'] ?? null,
            'questions' => $validated['questions'] ?? null,
            'categories' => $validated['categories'] ?? null,
            'status' => $validated['status'] ?? 'draft',
            'last_saved_at' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Encuesta temporal guardada exitosamente',
            'data' => $temporarySurvey
        ], 201);
    }

    public function show($id)
    {
        $temporarySurvey = TemporarySurveyModel::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $temporarySurvey
        ]);
    }

    public function update(Request $request, $id)
    {
        $temporarySurvey = TemporarySurveyModel::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'survey_data' => 'nullable|array',
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'sections' => 'nullable|array',
            'questions' => 'nullable|array',
            'categories' => 'nullable|array',
            'status' => 'nullable|string|in:draft,in_progress'
        ]);

        // Update only provided fields
        foreach ($validated as $key => $value) {
            if ($value !== null) {
                $temporarySurvey->$key = $value;
            }
        }
        
        $temporarySurvey->last_saved_at = now();
        $temporarySurvey->save();

        return response()->json([
            'success' => true,
            'message' => 'Encuesta temporal actualizada exitosamente',
            'data' => $temporarySurvey
        ]);
    }

    public function destroy($id)
    {
        $temporarySurvey = TemporarySurveyModel::where('user_id', Auth::id())
            ->findOrFail($id);

        $temporarySurvey->delete();

        return response()->json([
            'success' => true,
            'message' => 'Encuesta temporal eliminada exitosamente'
        ]);
    }

    public function autoSave(Request $request)
    {
        $validated = $request->validate([
            'id' => 'nullable|integer',
            'localStorage_data' => 'required|array'
        ]);

        $data = $validated['localStorage_data'];
        
        // Parse localStorage data
        $surveyData = [
            'survey_info' => $data['survey_info'] ?? null,
            'sections' => $data['survey_sections'] ?? null,
            'questions' => $data['survey_questions'] ?? null,
            'selected_section' => $data['selected_section_id'] ?? null
        ];

        // Extract fields
        $title = $surveyData['survey_info']['title'] ?? null;
        $description = $surveyData['survey_info']['description'] ?? null;
        $startDate = $surveyData['survey_info']['startDate'] ?? null;
        $endDate = $surveyData['survey_info']['endDate'] ?? null;
        $categories = $surveyData['survey_info']['selectedCategory'] ?? null;

        if (isset($validated['id'])) {
            // Update existing
            $temporarySurvey = TemporarySurveyModel::where('user_id', Auth::id())
                ->findOrFail($validated['id']);
            
            $temporarySurvey->update([
                'survey_data' => $surveyData,
                'title' => $title,
                'description' => $description,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sections' => $surveyData['sections'],
                'questions' => $surveyData['questions'],
                'categories' => $categories,
                'last_saved_at' => now()
            ]);
        } else {
            // Create new
            $temporarySurvey = TemporarySurveyModel::create([
                'user_id' => Auth::id(),
                'survey_data' => $surveyData,
                'title' => $title,
                'description' => $description,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'sections' => $surveyData['sections'],
                'questions' => $surveyData['questions'],
                'categories' => $categories,
                'status' => 'draft',
                'last_saved_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Auto-guardado exitoso',
            'data' => $temporarySurvey
        ]);
    }
}