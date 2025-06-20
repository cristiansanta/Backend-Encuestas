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
            // Try to find existing record
            $temporarySurvey = TemporarySurveyModel::where('user_id', Auth::id())
                ->find($validated['id']);
            
            if ($temporarySurvey) {
                // Update existing
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
                // ID provided but record not found, create new one
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
        } else {
            // MEJORADO: Lógica más robusta para evitar múltiples borradores
            
            // Estrategia 1: Buscar el borrador más reciente del usuario (última sesión de edición)
            $mostRecentDraft = TemporarySurveyModel::where('user_id', Auth::id())
                ->where('status', 'draft')
                ->orderBy('last_saved_at', 'desc')
                ->first();
            
            // Estrategia 2: Si hay datos de sección o pregunta específicos, usar esos para identificar
            $sectionsData = $surveyData['sections'] ?? [];
            $questionsData = $surveyData['questions'] ?? [];
            
            $existingByContent = null;
            
            // Si tenemos un borrador reciente y el contenido parece similar, actualizarlo
            if ($mostRecentDraft) {
                $timeDifference = now()->diffInMinutes($mostRecentDraft->last_saved_at);
                
                // Si el último borrador fue modificado en las últimas 2 horas, considerar que es la misma sesión
                if ($timeDifference <= 120) {
                    \Log::info('Auto-save: Found recent draft within 2 hours, updating it', [
                        'draft_id' => $mostRecentDraft->id,
                        'minutes_ago' => $timeDifference,
                        'old_title' => $mostRecentDraft->title,
                        'new_title' => $title
                    ]);
                    
                    $existingByContent = $mostRecentDraft;
                }
            }
            
            // Estrategia 3: Si no hay borrador reciente, buscar por contenido similar
            if (!$existingByContent && $title) {
                $existingByContent = TemporarySurveyModel::where('user_id', Auth::id())
                    ->where('status', 'draft')
                    ->where(function($query) use ($title) {
                        $query->where('title', $title)
                              ->orWhere('title', 'LIKE', '%' . substr($title, 0, 20) . '%');
                    })
                    ->orderBy('last_saved_at', 'desc')
                    ->first();
            }
            
            if ($existingByContent) {
                // Actualizar borrador existente
                $existingByContent->update([
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
                
                \Log::info('Auto-save: Updated existing draft', [
                    'draft_id' => $existingByContent->id,
                    'title' => $title
                ]);
                
                $temporarySurvey = $existingByContent;
                
                // LIMPIEZA: Eliminar otros borradores antiguos del mismo usuario para evitar acumulación
                $this->cleanupOldDrafts(Auth::id(), $existingByContent->id);
                
            } else {
                // Crear nuevo borrador solo si no encontramos ninguno apropiado
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
                
                \Log::info('Auto-save: Created new draft', [
                    'draft_id' => $temporarySurvey->id,
                    'title' => $title
                ]);
                
                // LIMPIEZA: Mantener solo los últimos 3 borradores del usuario
                $this->cleanupOldDrafts(Auth::id(), $temporarySurvey->id, 3);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Auto-guardado exitoso',
            'data' => $temporarySurvey
        ]);
    }

    /**
     * Limpiar borradores antiguos para evitar acumulación
     */
    private function cleanupOldDrafts($userId, $keepId, $maxDrafts = 5)
    {
        try {
            // Contar borradores actuales del usuario
            $totalDrafts = TemporarySurveyModel::where('user_id', $userId)
                ->where('status', 'draft')
                ->count();

            if ($totalDrafts <= $maxDrafts) {
                return; // No necesita limpieza
            }

            // Obtener borradores antiguos (excluyendo el que queremos mantener)
            $oldDrafts = TemporarySurveyModel::where('user_id', $userId)
                ->where('status', 'draft')
                ->where('id', '!=', $keepId)
                ->orderBy('last_saved_at', 'asc')
                ->take($totalDrafts - $maxDrafts)
                ->get();

            $deletedCount = 0;
            foreach ($oldDrafts as $draft) {
                // Solo eliminar borradores que sean más antiguos que 24 horas
                $hoursOld = now()->diffInHours($draft->last_saved_at);
                if ($hoursOld >= 24) {
                    $draft->delete();
                    $deletedCount++;
                }
            }

            if ($deletedCount > 0) {
                \Log::info("Cleanup: Deleted {$deletedCount} old drafts for user {$userId}");
            }

        } catch (\Exception $e) {
            \Log::error('Error during draft cleanup: ' . $e->getMessage());
        }
    }
}