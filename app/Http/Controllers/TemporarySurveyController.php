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
            'child_question_conditions' => 'nullable|array',
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
            'child_question_conditions' => $validated['child_question_conditions'] ?? null,
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
            'localStorage_data' => 'required|array',
            'force_new_survey' => 'nullable|boolean'
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
            // NUEVO: Verificar si el frontend solicita forzar nueva encuesta
            $forceNewSurvey = $validated['force_new_survey'] ?? false;
            
            if ($forceNewSurvey) {
                // Frontend detectó nueva encuesta, crear registro sin buscar duplicados
                \Log::info('Auto-save: Frontend requested new survey, creating new record', [
                    'title' => $title,
                    'force_new_survey' => true
                ]);
                
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
                
                \Log::info('Auto-save: Created new forced survey', [
                    'draft_id' => $temporarySurvey->id,
                    'title' => $title
                ]);
                
            } else {
                // LÓGICA CONSOLIDADA: Buscar y limpiar duplicados para mantener solo un borrador por usuario
                
                // PASO 1: Limpiar duplicados automáticamente antes de continuar
                $this->consolidateDuplicateTemporarySurveys(Auth::id());
                
                // PASO 2: Buscar el único borrador restante del usuario
                $existingDraft = TemporarySurveyModel::where('user_id', Auth::id())
                    ->where('status', 'draft')
                    ->orderBy('last_saved_at', 'desc')
                    ->first();
                
                $existingByContent = null;
                
                // PASO 3: Solo usar el borrador si fue modificado recientemente (misma sesión)
                if ($existingDraft) {
                    $timeDifference = now()->diffInMinutes($existingDraft->last_saved_at);
                    
                    // Si el borrador fue modificado en las últimas 4 horas, considerarlo de la misma sesión
                    if ($timeDifference <= 240) {
                        \Log::info('Auto-save: Using single existing draft', [
                            'draft_id' => $existingDraft->id,
                            'minutes_ago' => $timeDifference,
                            'old_title' => $existingDraft->title,
                            'new_title' => $title
                        ]);
                        
                        $existingByContent = $existingDraft;
                    } else {
                        // Si es muy antiguo, eliminarlo y crear uno nuevo
                        \Log::info('Auto-save: Deleting old draft and creating new one', [
                            'old_draft_id' => $existingDraft->id,
                            'hours_ago' => round($timeDifference / 60, 2)
                        ]);
                        
                        $existingDraft->delete();
                        $existingByContent = null;
                    }
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

            // 2. Transfer sections - CRÍTICO: NO duplicar secciones del banco
            $sectionsData = $temporarySurvey->sections ?? [];
            $sectionIdMap = []; // Map temporary section IDs to permanent ones

            foreach ($sectionsData as $sectionData) {
                $sectionTitle = $sectionData['title'] ?? 'Sección sin título';

                // MEJORA CRÍTICA: Verificar si la sección ya existe en el banco
                // Las secciones del banco NO tienen id_survey (es NULL)
                $existingBankSection = \App\Models\SectionModel::whereNull('id_survey')
                    ->where('title', $sectionTitle)
                    ->where('user_create', Auth::user()->name ?? Auth::user()->email ?? 'system')
                    ->first();

                if ($existingBankSection) {
                    // REUTILIZAR sección del banco en lugar de crear duplicado
                    $section = $existingBankSection;
                    \Log::info("TemporarySurveyController::publish - REUSING bank section ID: {$section->id} for temp ID: " . ($sectionData['id'] ?? 'unknown'));
                } else {
                    // Solo crear nueva sección si NO existe en el banco
                    // NOTA: Las secciones se mantienen en el banco (id_survey = NULL) para poder reutilizarlas
                    $section = \App\Models\SectionModel::create([
                        'title' => $sectionTitle,
                        'descrip_sect' => $sectionData['description'] ?? '',
                        'id_survey' => null, // Mantener en banco para reutilización
                        'user_create' => Auth::user()->name ?? Auth::user()->email ?? 'system'
                    ]);
                    \Log::info("TemporarySurveyController::publish - Created NEW bank section ID: {$section->id} for temp ID: " . ($sectionData['id'] ?? 'unknown'));
                }

                // Map temporary ID to permanent ID
                if (isset($sectionData['id'])) {
                    $sectionIdMap[$sectionData['id']] = $section->id;
                }
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

                // MEJORA CRÍTICA: Verificar si la pregunta ya existe antes de crearla
                $existingQuestion = \App\Models\QuestionModel::where('title', $questionData['title'] ?? 'Pregunta sin título')
                    ->where('type_questions_id', $questionData['questionType'] ?? 1)
                    ->where('creator_id', Auth::id())
                    ->where('cod_padre', 0)
                    ->where('bank', true)
                    ->first();

                if ($existingQuestion) {
                    // Reutilizar pregunta existente y actualizar section_id si es necesario
                    if ($existingQuestion->section_id !== $sectionId) {
                        $existingQuestion->section_id = $sectionId;
                        $existingQuestion->save();
                        \Log::info("TemporarySurveyController::publish - Updated section_id for existing question ID: {$existingQuestion->id}");
                    }
                    $question = $existingQuestion;
                    \Log::info("TemporarySurveyController::publish - Reusing existing parent question ID: {$question->id}");
                } else {
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
                    \Log::info("TemporarySurveyController::publish - Created new parent question ID: {$question->id}");
                }

                // Map temporary ID to permanent ID
                if (isset($questionData['id'])) {
                    $questionIdMap[$questionData['id']] = $question->id;
                }

                // Verificar si ya existe la relación survey-question antes de crearla
                $existingRelation = \App\Models\SurveyquestionsModel::where('survey_id', $survey->id)
                    ->where('question_id', $question->id)
                    ->first();

                if (!$existingRelation) {
                    // Create survey-question relationship for parent question
                    \App\Models\SurveyquestionsModel::create([
                        'survey_id' => $survey->id,
                        'question_id' => $question->id,
                        'section_id' => $sectionId,
                        'creator_id' => Auth::id(),
                        'status' => true,
                        'user_id' => Auth::id()
                    ]);
                    \Log::info("TemporarySurveyController::publish - Created survey-question relationship for question ID: {$question->id}");
                } else {
                    \Log::info("TemporarySurveyController::publish - Survey-question relationship already exists for question ID: {$question->id}");
                }

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

                // MEJORA CRÍTICA: Verificar si la pregunta hija ya existe antes de crearla
                $existingChildQuestion = \App\Models\QuestionModel::where('title', $questionData['title'] ?? 'Pregunta hija sin título')
                    ->where('type_questions_id', $questionData['questionType'] ?? 1)
                    ->where('creator_id', Auth::id())
                    ->where('cod_padre', $parentId)
                    ->where('bank', true)
                    ->first();

                if ($existingChildQuestion) {
                    // Reutilizar pregunta existente y actualizar section_id si es necesario
                    if ($existingChildQuestion->section_id !== $sectionId) {
                        $existingChildQuestion->section_id = $sectionId;
                        $existingChildQuestion->save();
                        \Log::info("TemporarySurveyController::publish - Updated section_id for existing child question ID: {$existingChildQuestion->id}");
                    }
                    $childQuestion = $existingChildQuestion;
                    \Log::info("TemporarySurveyController::publish - Reusing existing child question ID: {$childQuestion->id}");
                } else {
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
                    \Log::info("TemporarySurveyController::publish - Created new child question ID: {$childQuestion->id}");
                }

                // Map temporary ID to permanent ID for this child question too
                if (isset($questionData['id'])) {
                    $questionIdMap[$questionData['id']] = $childQuestion->id;
                }

                // Verificar si ya existe la relación survey-question antes de crearla
                $existingChildRelation = \App\Models\SurveyquestionsModel::where('survey_id', $survey->id)
                    ->where('question_id', $childQuestion->id)
                    ->first();

                if (!$existingChildRelation) {
                    // Create survey-question relationship for child question
                    \App\Models\SurveyquestionsModel::create([
                        'survey_id' => $survey->id,
                        'question_id' => $childQuestion->id,
                        'section_id' => $sectionId,
                        'creator_id' => Auth::id(),
                        'status' => true,
                        'user_id' => Auth::id()
                    ]);
                    \Log::info("TemporarySurveyController::publish - Created survey-question relationship for child question ID: {$childQuestion->id}");
                } else {
                    \Log::info("TemporarySurveyController::publish - Survey-question relationship already exists for child question ID: {$childQuestion->id}");
                }

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

    /**
     * Consolidar y eliminar encuestas temporales duplicadas
     * Mantiene solo una encuesta temporal por usuario
     */
    private function consolidateDuplicateTemporarySurveys($userId)
    {
        try {
            // Obtener todos los borradores del usuario
            $drafts = TemporarySurveyModel::where('user_id', $userId)
                ->where('status', 'draft')
                ->orderBy('last_saved_at', 'desc')
                ->get();

            // Si hay más de un borrador, mantener solo el más reciente
            if ($drafts->count() > 1) {
                $keepDraft = $drafts->first(); // El más reciente
                $deleteDrafts = $drafts->slice(1); // Todos los demás
                
                \Log::info('Consolidating duplicate temporary surveys', [
                    'user_id' => $userId,
                    'total_drafts' => $drafts->count(),
                    'keeping_draft_id' => $keepDraft->id,
                    'deleting_count' => $deleteDrafts->count()
                ]);

                // Eliminar los duplicados
                foreach ($deleteDrafts as $draft) {
                    \Log::info('Deleting duplicate draft', [
                        'draft_id' => $draft->id,
                        'title' => $draft->title,
                        'last_saved_at' => $draft->last_saved_at
                    ]);
                    $draft->delete();
                }

                \Log::info('Consolidation completed', [
                    'remaining_draft_id' => $keepDraft->id,
                    'remaining_title' => $keepDraft->title
                ]);
            }

        } catch (\Exception $e) {
            \Log::error('Error consolidating duplicate temporary surveys: ' . $e->getMessage());
        }
    }

    /**
     * Endpoint para limpiar duplicados existentes de forma manual
     */
    public function cleanupDuplicates(Request $request)
    {
        try {
            $userId = Auth::id();

            // Obtener estado actual
            $beforeCount = TemporarySurveyModel::where('user_id', $userId)
                ->where('status', 'draft')
                ->count();

            // Ejecutar consolidación
            $this->consolidateDuplicateTemporarySurveys($userId);

            // Verificar resultado
            $afterCount = TemporarySurveyModel::where('user_id', $userId)
                ->where('status', 'draft')
                ->count();

            $cleaned = $beforeCount - $afterCount;

            return response()->json([
                'success' => true,
                'message' => 'Duplicados limpiados exitosamente',
                'data' => [
                    'before_count' => $beforeCount,
                    'after_count' => $afterCount,
                    'cleaned_count' => $cleaned
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error limpiando duplicados: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar preguntas huérfanas (sin relación en survey_questions)
     * Útil para eliminar preguntas duplicadas que quedaron sin usar
     */
    public function cleanupOrphanQuestions(Request $request)
    {
        try {
            $userId = Auth::id();

            // Buscar preguntas del usuario que no tienen relación en survey_questions
            $orphanQuestions = \App\Models\QuestionModel::where('creator_id', $userId)
                ->whereDoesntHave('surveyQuestions')
                ->get();

            $beforeCount = $orphanQuestions->count();

            if ($beforeCount === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron preguntas huérfanas',
                    'data' => [
                        'orphan_questions_count' => 0,
                        'deleted_count' => 0
                    ]
                ]);
            }

            // Registrar preguntas a eliminar para auditoría
            $deletedQuestions = [];
            foreach ($orphanQuestions as $question) {
                $deletedQuestions[] = [
                    'id' => $question->id,
                    'title' => $question->title,
                    'section_id' => $question->section_id,
                    'created_at' => $question->created_at
                ];
            }

            // Eliminar opciones de las preguntas huérfanas primero (integridad referencial)
            foreach ($orphanQuestions as $question) {
                \App\Models\QuestionsoptionsModel::where('questions_id', $question->id)->delete();
            }

            // Eliminar las preguntas huérfanas
            $deletedCount = \App\Models\QuestionModel::where('creator_id', $userId)
                ->whereDoesntHave('surveyQuestions')
                ->delete();

            \Log::info('Orphan questions cleanup completed', [
                'user_id' => $userId,
                'deleted_count' => $deletedCount,
                'deleted_questions' => $deletedQuestions
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Preguntas huérfanas limpiadas exitosamente',
                'data' => [
                    'orphan_questions_count' => $beforeCount,
                    'deleted_count' => $deletedCount,
                    'deleted_questions' => $deletedQuestions
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Error cleaning orphan questions: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error limpiando preguntas huérfanas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar secciones duplicadas
     * Consolida secciones con el mismo título donde una está en el banco (id_survey NULL)
     * y otra está vinculada a una encuesta (id_survey NOT NULL)
     */
    public function cleanupDuplicateSections(Request $request)
    {
        try {
            $userName = Auth::user()->name ?? Auth::user()->email ?? 'system';

            \DB::beginTransaction();

            // Encontrar secciones duplicadas: mismo título, una en banco y otra en survey
            $duplicateSections = \DB::select("
                SELECT
                    s1.id as bank_section_id,
                    s1.title as section_title,
                    s2.id as survey_section_id,
                    s2.id_survey as survey_id
                FROM \"Produc\".sections s1
                INNER JOIN \"Produc\".sections s2 ON s1.title = s2.title
                WHERE s1.id_survey IS NULL
                AND s2.id_survey IS NOT NULL
                AND s1.user_create = ?
                AND s2.user_create = ?
                ORDER BY s1.title, s2.id_survey
            ", [$userName, $userName]);

            if (empty($duplicateSections)) {
                \DB::commit();
                return response()->json([
                    'success' => true,
                    'message' => 'No se encontraron secciones duplicadas',
                    'data' => [
                        'duplicate_sections_count' => 0,
                        'consolidated_count' => 0
                    ]
                ]);
            }

            $consolidatedSections = [];
            $migratedQuestions = 0;

            foreach ($duplicateSections as $duplicate) {
                $bankSectionId = $duplicate->bank_section_id;
                $surveySectionId = $duplicate->survey_section_id;
                $surveyId = $duplicate->survey_id;
                $sectionTitle = $duplicate->section_title;

                \Log::info("Consolidating duplicate section: '{$sectionTitle}'", [
                    'bank_section_id' => $bankSectionId,
                    'survey_section_id' => $surveySectionId,
                    'survey_id' => $surveyId
                ]);

                // 1. Migrar preguntas de la sección survey a la sección banco
                $questionsToMigrate = \App\Models\QuestionModel::where('section_id', $surveySectionId)->get();

                foreach ($questionsToMigrate as $question) {
                    // Verificar si ya existe una pregunta idéntica en la sección del banco
                    $existingBankQuestion = \App\Models\QuestionModel::where('section_id', $bankSectionId)
                        ->where('title', $question->title)
                        ->where('type_questions_id', $question->type_questions_id)
                        ->where('creator_id', $question->creator_id)
                        ->first();

                    if ($existingBankQuestion) {
                        // Actualizar survey_questions para usar la pregunta del banco
                        \DB::table('survey_questions')
                            ->where('question_id', $question->id)
                            ->where('survey_id', $surveyId)
                            ->update([
                                'question_id' => $existingBankQuestion->id,
                                'section_id' => $bankSectionId
                            ]);

                        // Eliminar la pregunta duplicada
                        \App\Models\QuestionsoptionsModel::where('questions_id', $question->id)->delete();
                        $question->delete();

                        \Log::info("Merged duplicate question to bank", [
                            'deleted_question_id' => $question->id,
                            'kept_bank_question_id' => $existingBankQuestion->id,
                            'title' => $question->title
                        ]);
                    } else {
                        // Mover pregunta a la sección del banco
                        $question->section_id = $bankSectionId;
                        $question->save();

                        // Actualizar survey_questions
                        \DB::table('survey_questions')
                            ->where('question_id', $question->id)
                            ->where('survey_id', $surveyId)
                            ->update(['section_id' => $bankSectionId]);

                        \Log::info("Migrated question to bank section", [
                            'question_id' => $question->id,
                            'from_section' => $surveySectionId,
                            'to_section' => $bankSectionId
                        ]);
                    }

                    $migratedQuestions++;
                }

                // 2. Eliminar la sección duplicada del survey
                $deletedSection = \App\Models\SectionModel::find($surveySectionId);
                if ($deletedSection) {
                    $deletedSection->delete();
                    \Log::info("Deleted duplicate survey section", [
                        'section_id' => $surveySectionId,
                        'title' => $sectionTitle,
                        'survey_id' => $surveyId
                    ]);
                }

                $consolidatedSections[] = [
                    'section_title' => $sectionTitle,
                    'bank_section_id' => $bankSectionId,
                    'deleted_section_id' => $surveySectionId,
                    'survey_id' => $surveyId
                ];
            }

            \DB::commit();

            \Log::info('Duplicate sections cleanup completed', [
                'user' => $userName,
                'consolidated_count' => count($consolidatedSections),
                'migrated_questions' => $migratedQuestions
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Secciones duplicadas consolidadas exitosamente',
                'data' => [
                    'duplicate_sections_count' => count($duplicateSections),
                    'consolidated_count' => count($consolidatedSections),
                    'migrated_questions' => $migratedQuestions,
                    'consolidated_sections' => $consolidatedSections
                ]
            ]);

        } catch (\Exception $e) {
            \DB::rollback();
            \Log::error('Error cleaning duplicate sections: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Error limpiando secciones duplicadas: ' . $e->getMessage()
            ], 500);
        }
    }
}