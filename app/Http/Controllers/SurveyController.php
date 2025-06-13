<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Validator;
use Closure;
use Symfony\Component\HttpFoundation\Response;
use HTMLPurifier;
use HTMLPurifier_Config;
use function PHPSTORM_META\type;
use Illuminate\Support\Facades\Storage;


class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }
        
        // Filtrar encuestas por el usuario autenticado
        $surveys = SurveyModel::where('user_create', $user->name)->get();
        
        return response()->json($surveys); // Cambiado para devolver JSON
        //return view('surveys.index', compact('surveys'));
    }


    public function create()
    {
        //
        return view('surveys.create');
    }

    /**
     * Store a newly created resource in storage.
     */

    public function pon(Request $request, Closure $next )
    {
        $apiKey = $request->header('X-API-KEY');
        if ($apiKey !== config('app.api_key')) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        return $next($request);       

    }

  

    public function store(Request $request)
    {
        // Validar los datos entrantes
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'descrip' => 'nullable|string',
            'id_category' => 'nullable|integer',
            'status' => 'required|boolean',
            'publication_status' => 'nullable|in:draft,unpublished,published',
            'user_create' => 'required|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $validator->errors(),
            ], 422);
        }
        
        // Validar que no exista otra encuesta con el mismo título para el mismo usuario
        $existingSurvey = SurveyModel::where('title', $request->title)
                                    ->where('user_create', $request->user_create)
                                    ->first();
                                    
        if ($existingSurvey) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => ['title' => ['Ya existe una encuesta con este nombre']],
                'message' => 'Ya tienes una encuesta con el mismo nombre. Por favor, elige un nombre diferente.'
            ], 422);
        }

        $data = $request->all();

        // Buscar y decodificar imágenes en base64 dentro de la descripción
        if (preg_match_all('/<img src="data:image\/[^;]+;base64,([^"]+)"/', $data['descrip'], $matches)) {
            foreach ($matches[1] as $key => $base64Image) {
                // Decodificar la imagen base64
                $imageData = base64_decode($base64Image);
                $imageName = uniqid() . '.png'; // Puedes cambiar la extensión según el tipo de imagen
                $imagePath = 'private/images/' . $imageName; // Ruta en almacenamiento privado

                // Almacenar la imagen en el sistema de archivos privado
                Storage::disk('private')->put('images/' . $imageName, $imageData);

                // Reemplazar la imagen base64 por la ruta de almacenamiento privado
                $storagePath = '/storage/images/' . $imageName; // Ajustamos la ruta de acceso
                $data['descrip'] = str_replace($matches[0][$key], '<img src="' . $storagePath . '"', $data['descrip']);
            }
        }

        try {
            // Crear la encuesta en la base de datos
            $survey = SurveyModel::create($data);

            return response()->json([
                'message' => 'Encuesta creada exitosamente',
                'survey' => $survey,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la encuesta',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $survey = SurveyModel::find($id);
        if ($survey) {
            
            return response()->json($survey); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $survey = SurveyModel::find($id);
        if ($survey) {
            return response()->json($survey); // Cambiado para devolver JSON
            //return view('surveys.edit', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */

    
    public function update(Request $request, string $id)
    {
        \Log::info("SurveyController::update - Iniciando actualización de encuesta ID: {$id}");
        \Log::info("SurveyController::update - Datos recibidos: " . json_encode($request->all()));
        
        $survey = SurveyModel::find($id);
        if (!$survey) {
            \Log::error("SurveyController::update - No se encontró la encuesta con ID: {$id}");
            return response()->json(['message' => 'No se encontró la encuesta con id: ' . $id], 404);
        }
        
        \Log::info("SurveyController::update - Estado actual de la encuesta: status={$survey->status}, publication_status={$survey->publication_status}");
    
        // Validar los datos de la solicitud
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'descrip' => 'nullable|string',
            'id_category' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'publication_status' => 'nullable|in:draft,unpublished,published',
            'user_create' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date'
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación', 'errors' => $validator->errors()], 422);
        }
        
        // Si se está actualizando el título, validar que no exista otra encuesta con el mismo título para el mismo usuario
        if ($request->has('title') && $request->title !== $survey->title) {
            $existingSurvey = SurveyModel::where('title', $request->title)
                                        ->where('user_create', $survey->user_create)
                                        ->where('id', '!=', $id)
                                        ->first();
                                        
            if ($existingSurvey) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => ['title' => ['Ya existe una encuesta con este nombre']],
                    'error' => 'Ya tienes una encuesta con el mismo nombre. Por favor, elige un nombre diferente.'
                ], 422);
            }
        }
    
        // Actualizar solo los campos proporcionados en la solicitud
        if ($request->has('title')) {
            $survey->title = $request->title;
        }
    
        if ($request->has('descrip')) {
            $survey->descrip = $request->descrip;
        }
    
        if ($request->has('id_category')) {
            $survey->id_category = $request->id_category;
        }
    
        if ($request->has('status')) {
            $survey->status = $request->status;
        }
        
        if ($request->has('publication_status')) {
            $survey->publication_status = $request->publication_status;
        }
        
        if ($request->has('user_create')) {
            $survey->user_create = $request->user_create;
        }
        
        if ($request->has('start_date')) {
            $survey->start_date = $request->start_date;
        }
        
        if ($request->has('end_date')) {
            $survey->end_date = $request->end_date;
        }
    
        // Guardar los cambios
        if ($survey->save()) {
            \Log::info("SurveyController::update - Encuesta actualizada exitosamente. Nuevo estado: status={$survey->status}, publication_status={$survey->publication_status}");
            return response()->json(['message' => 'Encuesta actualizada con éxito', 'data' => $survey], 200);
        } else {
            \Log::error("SurveyController::update - Error al guardar la encuesta ID: {$id}");
            return response()->json(['message' => 'Error al actualizar la encuesta'], 500);
        }
    }
    

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
{
    \Log::warning("SurveyController::destroy - ⚠️ ADVERTENCIA: Intento de eliminar encuesta ID: {$id}");
    \Log::warning("SurveyController::destroy - Stack trace: " . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
    
    $survey = SurveyModel::find($id);

    if (!$survey) {
        \Log::error("SurveyController::destroy - No se encontró la encuesta ID: {$id}");
        return response()->json(['message' => 'No se encontró la encuesta'], 404);
    }
    
    \Log::warning("SurveyController::destroy - Encuesta a eliminar: title={$survey->title}, status={$survey->status}, publication_status={$survey->publication_status}");

    try {
        $survey->delete();
        \Log::error("SurveyController::destroy - ❌ ENCUESTA ELIMINADA: ID={$id}, title={$survey->title}");
        return response()->json(['message' => 'Encuesta eliminada con éxito'], 200);
    } catch (\Exception $e) {
        \Log::error("SurveyController::destroy - Error al eliminar encuesta ID {$id}: " . $e->getMessage());
        return response()->json(['message' => 'Error al eliminar la encuesta', 'error' => $e->getMessage()], 500);
    }
}


    public function showSections($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            return response()->json($survey->sections);
        } else {
            return response()->json(['message' => 'sections not found'], 404);
        }
    }

    // Función para obtener preguntas de una encuesta específica de options
    public function getSurveyQuestions($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            $surveyQuestions = $survey->surveyQuestions;
            return response()->json($surveyQuestions);
        } else {
            return response()->json(['message' => 'Survey not found'], 404);
        }
    }

    // Función para obtener preguntas de una encuesta específica detallada
    public function getSurveyQuestionsop($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            $surveyQuestions = $survey->surveyQuestions()->with([
                'question.type',
                'question.options'
            ])->get();
            return response()->json([
                'survey_questions' => $surveyQuestions,
                'survey_info' => [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'description' => $survey->descrip
                ]
            ]);
        } else {
            return response()->json(['message' => 'Survey not found'], 404);
        }
    }

    // Función para obtener una encuesta con sus secciones
    public function getSurveySections($id)
    {
        $survey = SurveyModel::with('sections')->find($id);

        if ($survey) {
            return response()->json($survey);
        } else {
            return response()->json(['message' => 'Survey not found'], 404);
        }
    }

     // Función para obtener una encuesta completa con sus relaciones
     public function getSurveyDetails($id)
{
    try {
        $survey = SurveyModel::with([
            'category',
            'sections',
            'surveyQuestions.question.type',
            'surveyQuestions.question.options',
            'surveyQuestions.question.conditions'
        ])->find($id);

        if (!$survey) {
            return response()->json(['message' => 'Survey not found'], 404);
        }

        // Log información de debug
        \Log::info("getSurveyDetails for survey {$id} - Sections: {$survey->sections->count()}, Questions: {$survey->surveyQuestions->count()}");

        // Agregar contadores para debug
        $survey->sections_count = $survey->sections->count();
        $survey->questions_count = $survey->surveyQuestions->count();
        $survey->has_content = $survey->sections->count() > 0 || $survey->surveyQuestions->count() > 0;

        // Si no tiene contenido, verificar si hay secciones o preguntas huérfanas
        if (!$survey->has_content) {
            // Buscar secciones que podrían estar relacionadas pero con id_survey NULL
            $potentialSections = \DB::table('sections')
                ->whereNull('id_survey')
                ->orWhere('id_survey', $id)
                ->get();

            // Buscar preguntas que podrían estar relacionadas
            $potentialQuestions = \DB::table('survey_questions')
                ->where('survey_id', $id)
                ->get();

            $survey->debug_info = [
                'potential_sections' => $potentialSections->count(),
                'potential_questions' => $potentialQuestions->count(),
                'suggestion' => 'Consider running /surveys/repair-relations to fix orphaned relations'
            ];
        }

        return response()->json($survey);

    } catch (\Exception $e) {
        \Log::error("Error in getSurveyDetails for survey {$id}: " . $e->getMessage());
        
        return response()->json([
            'error' => 'Error loading survey details',
            'message' => $e->getMessage(),
            'survey_id' => $id
        ], 500);
    }
}
// Función para obtener todas las encuestas completas con sus relaciones
public function getAllSurveyDetails()
{
    try {
        $surveys = SurveyModel::with([
            'category',
            'sections',
            'surveyQuestions.question.type',
            'surveyQuestions.question.options',
            'surveyQuestions.question.conditions'
        ])->orderBy('id', 'desc')->get();
        
        // Log para debug
        \Log::info('getAllSurveyDetails - Total surveys found: ' . $surveys->count());
        
        // Actualizar el estado de las encuestas dinámicamente y agregar debug info
        $surveys->each(function($survey) {
            $this->updateSurveyStatusBasedOnDates($survey);
            
            // Log de debug para cada encuesta
            \Log::info("Survey {$survey->id} - Sections: {$survey->sections->count()}, Questions: {$survey->surveyQuestions->count()}");
            
            // Agregar contadores para el frontend
            $survey->sections_count = $survey->sections->count();
            $survey->questions_count = $survey->surveyQuestions->count();
        });

        if ($surveys->isNotEmpty()) {
            return response()->json($surveys);
        } else {
            return response()->json(['message' => 'No surveys found'], 404);
        }
    } catch (\Exception $e) {
        \Log::error('Error in getAllSurveyDetails: ' . $e->getMessage());
        \Log::error('Error trace: ' . $e->getTraceAsString());
        
        return response()->json([
            'error' => 'Error loading surveys',
            'message' => $e->getMessage(),
            'debug' => config('app.debug') ? $e->getTraceAsString() : null
        ], 500);
    }
}

/**
 * Actualiza el estado de una encuesta basándose en las fechas
 */
private function updateSurveyStatusBasedOnDates($survey)
{
    // Solo actualizar si la encuesta está publicada (status = true)
    if (!$survey->status || !$survey->start_date || !$survey->end_date) {
        return;
    }
    
    $now = now();
    $startDate = $survey->start_date;
    $endDate = $survey->end_date;
    
    // Calcular días hasta el final
    $daysUntilEnd = $now->diffInDays($endDate, false);
    
    // Si la fecha de fin ya pasó, marcar como finalizada
    if ($endDate < $now) {
        $survey->survey_status = 'Finalizada';
    }
    // Si faltan 3 días o menos para finalizar
    elseif ($daysUntilEnd <= 3 && $daysUntilEnd >= 0) {
        $survey->survey_status = 'Próxima a Finalizar';
    }
    // Si está dentro del rango de fechas y publicada
    elseif ($startDate <= $now && $now <= $endDate) {
        $survey->survey_status = 'Activa';
    }
    // Si aún no ha comenzado
    else {
        $survey->survey_status = 'Programada';
    }
    
    // No guardar en la base de datos, solo actualizar el objeto en memoria
    // para no afectar el campo status booleano
}


     
     

     public function testStorage()
     {
         try {
             $path = 'images/testfile.txt';
             Storage::disk('private')->put($path, 'Contenido de prueba');
             return 'Archivo almacenado correctamente.';
         } catch (\Exception $e) {
             return 'Error: ' . $e->getMessage();
         }
     }

    /**
     * Obtener lista de encuestas para envío masivo
     */
    public function list()
    {
        try {
            $surveys = SurveyModel::select('id', 'title', 'descrip', 'status', 'publication_status', 'created_at')
                ->whereIn('publication_status', ['unpublished', 'published']) // Incluir sin publicar y publicadas
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($survey) {
                    return [
                        'id' => $survey->id,
                        'title' => $survey->title,
                        'description' => $survey->descrip,
                        'status' => $survey->status,
                        'publication_status' => $survey->publication_status,
                        'created_at' => $survey->created_at
                    ];
                });

            return response()->json($surveys, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las encuestas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar solo el estado de publicación de una encuesta
     */
    public function updatePublicationStatus(Request $request, string $id)
    {
        $survey = SurveyModel::find($id);
        if (!$survey) {
            return response()->json(['message' => 'No se encontró la encuesta con id: ' . $id], 404);
        }

        $validator = Validator::make($request->all(), [
            'publication_status' => 'required|in:draft,unpublished,published',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación', 'errors' => $validator->errors()], 422);
        }

        $survey->publication_status = $request->publication_status;

        if ($survey->save()) {
            return response()->json([
                'message' => 'Estado de publicación actualizado con éxito', 
                'data' => $survey
            ], 200);
        } else {
            return response()->json(['message' => 'Error al actualizar el estado de publicación'], 500);
        }
    }

    /**
     * Método de debug para diagnosticar problemas con relaciones
     */
    public function debugSurveyRelations($id)
    {
        try {
            // Obtener encuesta básica
            $survey = SurveyModel::find($id);
            if (!$survey) {
                return response()->json(['error' => 'Survey not found'], 404);
            }

            // Debug información básica
            $debug = [
                'survey_id' => $survey->id,
                'survey_title' => $survey->title,
                'survey_status' => $survey->status,
            ];

            // Verificar secciones directamente
            $sectionsQuery = "SELECT * FROM sections WHERE id_survey = ?";
            $sections = \DB::select($sectionsQuery, [$id]);
            $debug['sections_raw_query'] = $sections;
            $debug['sections_count'] = count($sections);

            // Verificar preguntas directamente  
            $questionsQuery = "SELECT * FROM survey_questions WHERE survey_id = ?";
            $questions = \DB::select($questionsQuery, [$id]);
            $debug['questions_raw_query'] = $questions;
            $debug['questions_count'] = count($questions);

            // Buscar preguntas huérfanas que podrían estar relacionadas con las secciones
            if (count($sections) > 0) {
                $sectionIds = array_column($sections, 'id');
                $sectionIdsString = implode(',', $sectionIds);
                $orphanQuestionsQuery = "SELECT q.*, s.id_survey FROM questions q 
                                       LEFT JOIN sections s ON q.section_id = s.id 
                                       WHERE s.id_survey = ?";
                $orphanQuestions = \DB::select($orphanQuestionsQuery, [$id]);
                $debug['orphan_questions'] = $orphanQuestions;
                $debug['orphan_questions_count'] = count($orphanQuestions);
                
                // Análisis del problema
                $debug['analysis'] = [
                    'has_sections' => count($sections) > 0,
                    'has_survey_questions' => count($questions) > 0,
                    'has_orphan_questions' => count($orphanQuestions) > 0,
                    'problem_detected' => count($orphanQuestions) > 0 && count($questions) === 0,
                    'issue_description' => count($orphanQuestions) > 0 && count($questions) === 0 
                        ? 'Questions exist linked to sections but not in survey_questions pivot table' 
                        : 'No issues detected'
                ];
            }

            // Probar relaciones Eloquent
            $surveyWithRelations = SurveyModel::with(['sections', 'surveyQuestions'])->find($id);
            $debug['eloquent_sections'] = $surveyWithRelations->sections->toArray();
            $debug['eloquent_questions'] = $surveyWithRelations->surveyQuestions->toArray();
            $debug['eloquent_sections_count'] = $surveyWithRelations->sections->count();
            $debug['eloquent_questions_count'] = $surveyWithRelations->surveyQuestions->count();

            return response()->json($debug);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Debug failed',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    /**
     * Reparar relaciones faltantes entre encuestas y preguntas
     */
    public function repairSurveyQuestions($id)
    {
        try {
            $survey = SurveyModel::find($id);
            if (!$survey) {
                return response()->json(['error' => 'Survey not found'], 404);
            }

            // Obtener secciones de la encuesta
            $sections = \DB::select("SELECT * FROM sections WHERE id_survey = ?", [$id]);
            
            if (empty($sections)) {
                return response()->json([
                    'message' => 'No sections found for this survey',
                    'survey_id' => $id
                ]);
            }

            $sectionIds = array_column($sections, 'id');
            
            // Buscar preguntas asociadas a las secciones pero no a la encuesta
            $orphanQuestions = \DB::select("
                SELECT q.* FROM questions q 
                WHERE q.section_id IN (" . implode(',', $sectionIds) . ")
                AND q.id NOT IN (
                    SELECT question_id FROM survey_questions WHERE survey_id = ?
                )
            ", [$id]);

            $repairedCount = 0;
            $errors = [];

            foreach ($orphanQuestions as $question) {
                try {
                    // Insertar en la tabla pivot survey_questions
                    \DB::insert("
                        INSERT INTO survey_questions (survey_id, question_id, section_id, creator_id, status, user_id) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ", [
                        $id,
                        $question->id,
                        $question->section_id,
                        $question->creator_id ?? 1,
                        1, // status activo
                        $question->creator_id ?? 1
                    ]);
                    $repairedCount++;
                } catch (\Exception $e) {
                    $errors[] = "Question ID {$question->id}: " . $e->getMessage();
                }
            }

            return response()->json([
                'survey_id' => $id,
                'survey_title' => $survey->title,
                'sections_count' => count($sections),
                'orphan_questions_found' => count($orphanQuestions),
                'questions_repaired' => $repairedCount,
                'errors' => $errors,
                'success' => $repairedCount > 0
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Repair failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar y reparar relaciones de encuestas
     */
    public function repairSurveyRelations()
    {
        try {
            $report = [
                'total_surveys' => 0,
                'surveys_without_sections' => 0,
                'surveys_without_questions' => 0,
                'orphaned_sections' => 0,
                'orphaned_questions' => 0,
                'repaired_sections' => 0,
                'repaired_questions' => 0,
                'errors' => []
            ];

            // Obtener todas las encuestas
            $surveys = SurveyModel::all();
            $report['total_surveys'] = $surveys->count();

            // Verificar secciones huérfanas (sin id_survey)
            $orphanedSections = \DB::table('sections')->whereNull('id_survey')->get();
            $report['orphaned_sections'] = $orphanedSections->count();

            // Verificar preguntas huérfanas (sin survey_id válido)
            $orphanedQuestions = \DB::table('survey_questions')
                ->leftJoin('surveys', 'survey_questions.survey_id', '=', 'surveys.id')
                ->whereNull('surveys.id')
                ->select('survey_questions.*')
                ->get();
            $report['orphaned_questions'] = $orphanedQuestions->count();

            foreach ($surveys as $survey) {
                $sectionsCount = $survey->sections()->count();
                $questionsCount = $survey->surveyQuestions()->count();

                if ($sectionsCount === 0) {
                    $report['surveys_without_sections']++;
                }

                if ($questionsCount === 0) {
                    $report['surveys_without_questions']++;
                }
            }

            // Mostrar información de reparación disponible
            $report['repair_suggestions'] = [
                'orphaned_sections_can_be_assigned' => $orphanedSections->count() > 0,
                'orphaned_questions_can_be_cleaned' => $orphanedQuestions->count() > 0,
                'note' => 'Use /surveys/repair-relations/execute para ejecutar reparaciones automáticas'
            ];

            return response()->json($report);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Repair check failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
