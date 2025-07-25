<?php

namespace App\Http\Controllers;

use App\Models\TemporarySurveyModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

    /**
     * Publish temporary survey - Transfer temporary data to permanent database records
     */
    public function publish(Request $request, $id)
    {
        try {
            \Log::info("TemporarySurveyController::publish - Starting publish process for temporary survey ID: {$id}");
            
            // Get the temporary survey
            $temporarySurvey = TemporarySurveyModel::where('user_id', Auth::id())
                ->findOrFail($id);

            \Log::info("TemporarySurveyController::publish - Found temporary survey: {$temporarySurvey->title}");

            // Start database transaction
            \DB::beginTransaction();

            // 1. Create the permanent survey
            $surveyData = [
                'title' => $temporarySurvey->title,
                'descrip' => $temporarySurvey->description,
                'id_category' => $this->extractCategoryId($temporarySurvey->categories),
                'status' => true,
                'publication_status' => 'published',
                'user_create' => Auth::user()->name ?? Auth::user()->email ?? 'system',
                'start_date' => $temporarySurvey->start_date ?? now(),
                'end_date' => $temporarySurvey->end_date ?? now()->addDays(7)
            ];

            $survey = \App\Models\SurveyModel::create($surveyData);
            \Log::info("TemporarySurveyController::publish - Created survey ID: {$survey->id}");

            // 2. Transfer sections
            $sectionsData = $temporarySurvey->sections ?? [];
            $sectionIdMap = []; // Map temporary section IDs to permanent ones

            foreach ($sectionsData as $sectionData) {
                $section = \App\Models\SectionModel::create([
                    'title' => $sectionData['title'] ?? 'Sección sin título',
                    'descrip_sect' => $sectionData['description'] ?? '',
                    'id_survey' => $survey->id
                ]);
                
                // Map temporary ID to permanent ID
                if (isset($sectionData['id'])) {
                    $sectionIdMap[$sectionData['id']] = $section->id;
                }
                
                \Log::info("TemporarySurveyController::publish - Created section ID: {$section->id} for temp ID: " . ($sectionData['id'] ?? 'unknown'));
            }

            // 3. Transfer questions with their custom options
            $questionsData = $temporarySurvey->questions ?? [];
            $transferredQuestions = 0;
            $transferredOptions = 0;
            $questionIdMap = []; // Map temporary question IDs to permanent ones

            // First pass: Create parent questions only
            foreach ($questionsData as $questionData) {
                // Skip child questions in first pass
                if (isset($questionData['parentId']) && !empty($questionData['parentId']) && $questionData['parentId'] !== 0) {
                    continue;
                }

                \Log::info("TemporarySurveyController::publish - Processing parent question: " . ($questionData['title'] ?? 'Untitled'));
                \Log::info("TemporarySurveyController::publish - Question data: " . json_encode($questionData));

                // Map section ID
                $sectionId = null;
                if (isset($questionData['section']) && isset($sectionIdMap[$questionData['section']])) {
                    $sectionId = $sectionIdMap[$questionData['section']];
                } elseif (isset($sectionIdMap) && count($sectionIdMap) > 0) {
                    $sectionId = array_values($sectionIdMap)[0]; // Use first section as fallback
                }

                // Create the question
                $question = \App\Models\QuestionModel::create([
                    'title' => $questionData['title'] ?? 'Pregunta sin título',
                    'descrip' => $questionData['description'] ?? '',
                    'validate' => ($questionData['mandatory'] ?? false) ? 'Requerido' : 'Opcional',
                    'cod_padre' => 0, // Parent questions have cod_padre = 0
                    'bank' => true, // Save to question bank for reuse
                    'type_questions_id' => $questionData['questionType'] ?? 1,
                    'creator_id' => Auth::id(),
                    'questions_conditions' => false, // Parent questions don't have conditions
                    'section_id' => $sectionId
                ]);

                // Map temporary ID to permanent ID
                if (isset($questionData['id'])) {
                    $questionIdMap[$questionData['id']] = $question->id;
                }

                \Log::info("TemporarySurveyController::publish - Created parent question ID: {$question->id} for temp ID: " . ($questionData['id'] ?? 'unknown'));

                // Create survey-question relationship for parent question
                \App\Models\SurveyquestionsModel::create([
                    'survey_id' => $survey->id,
                    'question_id' => $question->id,
                    'section_id' => $sectionId,
                    'creator_id' => Auth::id(),
                    'status' => true,
                    'user_id' => Auth::id()
                ]);

                // 4. CRITICAL: Transfer custom options
                if (isset($questionData['options']) && is_array($questionData['options']) && count($questionData['options']) > 0) {
                    \Log::info("TemporarySurveyController::publish - Transferring " . count($questionData['options']) . " custom options for question {$question->id}");
                    
                    foreach ($questionData['options'] as $optionData) {
                        $optionText = '';
                        
                        // Handle different option formats
                        if (is_string($optionData)) {
                            $optionText = $optionData;
                        } elseif (is_array($optionData)) {
                            $optionText = $optionData['text'] ?? $optionData['option'] ?? $optionData['value'] ?? $optionData['label'] ?? '';
                        }

                        if (!empty($optionText) && $optionText !== 'Opción 1' && $optionText !== 'Opción 2' && $optionText !== 'Opción 3') {
                            \App\Models\QuestionsoptionsModel::create([
                                'questions_id' => $question->id,
                                'options' => $optionText,
                                'creator_id' => Auth::id(),
                                'status' => true
                            ]);
                            
                            $transferredOptions++;
                            \Log::info("TemporarySurveyController::publish - Transferred custom option: '{$optionText}' for question {$question->id}");
                        } else {
                            \Log::warning("TemporarySurveyController::publish - Skipped empty or default option: '{$optionText}'");
                        }
                    }
                } else {
                    \Log::warning("TemporarySurveyController::publish - No custom options found for question {$question->id}, type: " . ($questionData['questionType'] ?? 'unknown'));
                }

                $transferredQuestions++;
            }

            // Second pass: Create child questions
            foreach ($questionsData as $questionData) {
                // Only process child questions in second pass
                if (!isset($questionData['parentId']) || empty($questionData['parentId']) || $questionData['parentId'] === 0) {
                    continue;
                }

                \Log::info("TemporarySurveyController::publish - Processing child question: " . ($questionData['title'] ?? 'Untitled'));
                \Log::info("TemporarySurveyController::publish - Parent ID: " . $questionData['parentId']);

                // Map parent ID from temporary to permanent
                $parentId = 0;
                if (isset($questionIdMap[$questionData['parentId']])) {
                    $parentId = $questionIdMap[$questionData['parentId']];
                } else {
                    // If direct mapping fails, try to extract numeric ID from complex ID
                    if (preg_match('/(\d+)/', $questionData['parentId'], $matches)) {
                        $tempNumericId = $matches[1];
                        // Look for this numeric ID in our question map
                        foreach ($questionIdMap as $tempId => $permanentId) {
                            if (strpos($tempId, $tempNumericId) !== false) {
                                $parentId = $permanentId;
                                break;
                            }
                        }
                    }
                }

                if ($parentId === 0) {
                    \Log::warning("TemporarySurveyController::publish - Could not map parent ID for child question: " . ($questionData['title'] ?? 'Untitled'));
                    continue;
                }

                // Map section ID (same as parent or use mapping)
                $sectionId = null;
                if (isset($questionData['section']) && isset($sectionIdMap[$questionData['section']])) {
                    $sectionId = $sectionIdMap[$questionData['section']];
                } elseif (isset($sectionIdMap) && count($sectionIdMap) > 0) {
                    $sectionId = array_values($sectionIdMap)[0];
                }

                // Create the child question
                $childQuestion = \App\Models\QuestionModel::create([
                    'title' => $questionData['title'] ?? 'Pregunta hija sin título',
                    'descrip' => $questionData['description'] ?? '',
                    'validate' => ($questionData['mandatory'] ?? false) ? 'Requerido' : 'Opcional',
                    'cod_padre' => $parentId, // Map to actual parent question ID
                    'bank' => true, // Save to question bank for reuse
                    'type_questions_id' => $questionData['questionType'] ?? 1,
                    'creator_id' => Auth::id(),
                    'questions_conditions' => true, // Child questions have conditions
                    'mother_answer_condition' => $questionData['mother_answer_condition'] ?? $questionData['condition'] ?? null,
                    'section_id' => $sectionId
                ]);

                // Map temporary ID to permanent ID for this child question too
                if (isset($questionData['id'])) {
                    $questionIdMap[$questionData['id']] = $childQuestion->id;
                }

                \Log::info("TemporarySurveyController::publish - Created child question ID: {$childQuestion->id} with parent ID: {$parentId}");

                // Create survey-question relationship for child question
                \App\Models\SurveyquestionsModel::create([
                    'survey_id' => $survey->id,
                    'question_id' => $childQuestion->id,
                    'section_id' => $sectionId,
                    'creator_id' => Auth::id(),
                    'status' => true,
                    'user_id' => Auth::id()
                ]);

                // Transfer custom options for child question
                if (isset($questionData['options']) && is_array($questionData['options']) && count($questionData['options']) > 0) {
                    \Log::info("TemporarySurveyController::publish - Transferring " . count($questionData['options']) . " custom options for child question {$childQuestion->id}");
                    
                    foreach ($questionData['options'] as $optionData) {
                        $optionText = '';
                        
                        // Handle different option formats
                        if (is_string($optionData)) {
                            $optionText = $optionData;
                        } elseif (is_array($optionData)) {
                            $optionText = $optionData['text'] ?? $optionData['option'] ?? $optionData['value'] ?? $optionData['label'] ?? '';
                        }

                        if (!empty($optionText) && $optionText !== 'Opción 1' && $optionText !== 'Opción 2' && $optionText !== 'Opción 3') {
                            \App\Models\QuestionsoptionsModel::create([
                                'questions_id' => $childQuestion->id,
                                'options' => $optionText,
                                'creator_id' => Auth::id(),
                                'status' => true
                            ]);
                            
                            $transferredOptions++;
                            \Log::info("TemporarySurveyController::publish - Transferred custom option: '{$optionText}' for child question {$childQuestion->id}");
                        }
                    }
                }

                $transferredQuestions++;
            }

            // Update temporary survey status
            $temporarySurvey->status = 'published';
            $temporarySurvey->save();

            // Commit transaction
            \DB::commit();

            \Log::info("TemporarySurveyController::publish - Successfully published survey. Questions: {$transferredQuestions}, Options: {$transferredOptions}");

            return response()->json([
                'success' => true,
                'message' => 'Encuesta publicada exitosamente',
                'data' => [
                    'survey_id' => $survey->id,
                    'temporary_survey_id' => $temporarySurvey->id,
                    'transferred_questions' => $transferredQuestions,
                    'transferred_options' => $transferredOptions,
                    'sections_created' => count($sectionIdMap)
                ]
            ], 201);

        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error("TemporarySurveyController::publish - Error publishing survey: " . $e->getMessage());
            \Log::error("TemporarySurveyController::publish - Stack trace: " . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Error al publicar la encuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract category ID from the categories JSON array
     */
    private function extractCategoryId($categories)
    {
        if (!$categories) {
            return null;
        }

        // If it's a string, decode it
        if (is_string($categories)) {
            $categories = json_decode($categories, true);
        }

        // If it's an array and has data
        if (is_array($categories) && count($categories) > 0) {
            // Categories are stored as [["id", "name", "description"]]
            $firstCategory = $categories[0];
            if (is_array($firstCategory) && count($firstCategory) > 0) {
                return (int) $firstCategory[0]; // Return the ID as integer
            }
        }

        return null;
    }
}