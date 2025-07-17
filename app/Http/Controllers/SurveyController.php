<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\SurveyModel;
use App\Models\CategoryModel;
use Illuminate\Support\Facades\Validator;
use Closure;
use Symfony\Component\HttpFoundation\Response;
use HTMLPurifier;
use HTMLPurifier_Config;
use function PHPSTORM_META\type;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\SectionController;


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
        
        // Actualizar estados de encuestas automáticamente
        $this->updateSurveyStatesAutomatic();
        
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
        // Establecer fechas por defecto si no se proporcionan
        $data = $request->all();
        
        if (empty($data['start_date'])) {
            $data['start_date'] = now()->format('Y-m-d H:i:s');
        }
        
        if (empty($data['end_date'])) {
            $tomorrow = now()->addDay();
            $data['end_date'] = $tomorrow->format('Y-m-d H:i:s');
        }
        
        // FIXED: Additional validation for invalid titles (console warnings)
        $invalidTitlePatterns = [
            '/\[Deprecation\]/',
            '/DOMNodeInserted/',
            '/mutation event/',
            '/Listener added for a/',
            '/event type has been removed/',
            '/findDOMNode is deprecated/'
        ];
        
        $hasInvalidTitle = false;
        if (isset($data['title'])) {
            foreach ($invalidTitlePatterns as $pattern) {
                if (preg_match($pattern, $data['title'])) {
                    $hasInvalidTitle = true;
                    break;
                }
            }
        }
        
        if ($hasInvalidTitle) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => ['title' => ['El título contiene caracteres o mensajes no válidos.']],
                'message' => 'El título de la encuesta contiene caracteres o mensajes no válidos.'
            ], 422);
        }

        // Validar los datos entrantes (incluyendo fechas por defecto)
        $validator = Validator::make($data, [
            'title' => 'required|string|max:255',
            'descrip' => 'nullable|string',
            'id_category' => 'nullable|integer|exists:categories,id',
            'status' => 'required|boolean',
            'publication_status' => 'nullable|in:draft,unpublished,published,finished,scheduled',
            'user_create' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $validator->errors(),
            ], 422);
        }
        
        // Validar que no exista otra encuesta con el mismo título para el mismo usuario
        $existingSurvey = SurveyModel::where('title', $data['title'])
                                    ->where('user_create', $data['user_create'])
                                    ->first();
                                    
        if ($existingSurvey) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => ['title' => ['Ya existe una encuesta con este nombre']],
                'message' => 'Ya tienes una encuesta con el mismo nombre. Por favor, elige un nombre diferente.'
            ], 422);
        }

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
            'publication_status' => 'nullable|in:draft,unpublished,published,finished,scheduled',
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
            // Validar que la categoría existe
            if ($request->id_category !== null) {
                $category = CategoryModel::find($request->id_category);
                if (!$category) {
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => ['id_category' => ['La categoría seleccionada no existe']],
                        'error' => 'La categoría seleccionada no existe. Por favor, selecciona una categoría válida.'
                    ], 422);
                }
            }
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
        try {
            if ($survey->save()) {
                \Log::info("SurveyController::update - Encuesta actualizada exitosamente. Nuevo estado: status={$survey->status}, publication_status={$survey->publication_status}");
                return response()->json(['message' => 'Encuesta actualizada con éxito', 'data' => $survey], 200);
            } else {
                \Log::error("SurveyController::update - Error al guardar la encuesta ID: {$id}");
                return response()->json(['message' => 'Error al actualizar la encuesta'], 500);
            }
        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error("SurveyController::update - Error de base de datos: " . $e->getMessage());
            
            // Manejar errores específicos de foreign key
            if (strpos($e->getMessage(), '23503') !== false) {
                if (strpos($e->getMessage(), 'id_category') !== false) {
                    return response()->json([
                        'message' => 'Error de validación',
                        'errors' => ['id_category' => ['La categoría seleccionada no existe']],
                        'error' => 'La categoría seleccionada no existe. Por favor, selecciona una categoría válida.'
                    ], 422);
                } else {
                    return response()->json([
                        'message' => 'Error de validación',
                        'error' => 'Los datos proporcionados no son válidos. Verifica que todas las referencias existan.'
                    ], 422);
                }
            }
            
            return response()->json([
                'message' => 'Error al actualizar la encuesta',
                'error' => 'Ocurrió un error al procesar tu solicitud. Por favor, intenta nuevamente.'
            ], 500);
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
        try {
            $survey = SurveyModel::find($id);
            if (!$survey) {
                return response()->json(['message' => 'Survey not found'], 404);
            }

            // MEJORADO: Cargar todas las relaciones necesarias incluyendo preguntas hijas
            $surveyQuestions = $survey->surveyQuestions()->with([
                'question.type',
                'question.options',
                'question.parentQuestion', // Para preguntas hijas
                'question.childQuestions.type', // Para preguntas padre con sus hijas
                'question.childQuestions.options',
                'question.childQuestions.conditions', // Condiciones de preguntas hijas
                'question.conditions', // Condiciones de preguntas padre
                'section'
            ])->get();

            // Log para debugging
            \Log::info("getSurveyQuestions - Survey ID: {$id}, Questions found: {$surveyQuestions->count()}");
            
            return response()->json($surveyQuestions, 200);
        } catch (\Exception $e) {
            \Log::error("Error in getSurveyQuestions for survey {$id}: " . $e->getMessage());
            return response()->json(['message' => 'Error retrieving survey questions', 'error' => $e->getMessage()], 500);
        }
    }

    // Función para obtener preguntas de una encuesta específica detallada
    public function getSurveyQuestionsop($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            // Obtener TODAS las preguntas de la encuesta (padre e hijas) desde la tabla pivot
            $allQuestions = $survey->surveyQuestions()->with([
                'question.type',
                'question.options'
            ])->get();
            
            \Log::info("getSurveyQuestionsop for survey {$id}: Total questions from pivot table: {$allQuestions->count()}");
            
            // Debug: mostrar si hay preguntas hijas
            $childQuestionsCount = $allQuestions->filter(function($sq) {
                return $sq->question && $sq->question->cod_padre > 0;
            })->count();
            
            $parentQuestionsCount = $allQuestions->filter(function($sq) {
                return $sq->question && $sq->question->cod_padre == 0;
            })->count();
            
            \Log::info("getSurveyQuestionsop breakdown: Parent questions: {$parentQuestionsCount}, Child questions: {$childQuestionsCount}");
            
            return response()->json([
                'survey_questions' => $allQuestions,
                'survey_info' => [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'description' => $survey->descrip
                ],
                'debug_info' => [
                    'parent_questions_count' => $parentQuestionsCount,
                    'child_questions_count' => $childQuestionsCount,
                    'total_questions_count' => $allQuestions->count()
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
            'surveyQuestions.question.type',
            'surveyQuestions.question.options',
            'surveyQuestions.question.conditions',
            // AGREGADO: Cargar relaciones padre-hija de preguntas
            'surveyQuestions.question.parentQuestion', // Para preguntas hijas
            'surveyQuestions.question.childQuestions.type', // Para preguntas padre con sus hijas
            'surveyQuestions.question.childQuestions.options',
            'surveyQuestions.question.childQuestions.conditions',
            'surveyQuestions.section'
        ])->find($id);

        if (!$survey) {
            return response()->json(['message' => 'Survey not found'], 404);
        }

        // FIXED: Obtener secciones usando el método correcto que incluye secciones del banco
        $sectionsController = new SectionController();
        $sectionsResponse = $sectionsController->getSectionsBySurvey($id);
        $sectionsData = $sectionsResponse->getData();
        
        // Convertir a Collection para mantener compatibilidad
        $allSections = collect($sectionsData);
        
        // Asignar las secciones al survey
        $survey->setRelation('sections', $allSections);

        // Log información de debug con detalles de secciones y preguntas
        $sectionDetails = $survey->sections->map(function($section) {
            return "ID: {$section->id}, Title: '{$section->title}', id_survey: {$section->id_survey}";
        })->toArray();
        
        // AGREGADO: Log detallado de preguntas y sus relaciones
        $questionDetails = $survey->surveyQuestions->map(function($sq) {
            $question = $sq->question;
            $optionsCount = $question->options ? $question->options->count() : 0;
            $childrenCount = $question->childQuestions ? $question->childQuestions->count() : 0;
            $hasParent = $question->cod_padre ? 'SI' : 'NO';
            $hasConditions = $question->questions_conditions ? 'SI' : 'NO';
            
            return "Q{$question->id}: '{$question->title}' | Type: {$question->type_questions_id} | Options: {$optionsCount} | Children: {$childrenCount} | HasParent: {$hasParent} | HasConditions: {$hasConditions} | MotherAnswer: " . ($question->mother_answer_condition ?: 'NONE');
        })->toArray();
        
        \Log::info("getSurveyDetails for survey {$id} - Sections: {$survey->sections->count()}, Questions: {$survey->surveyQuestions->count()}");
        \Log::info("Section details: " . implode('; ', $sectionDetails));
        \Log::info("Question details: " . implode(' | ', $questionDetails));

        // Deduplicar secciones si hay duplicados por título
        $uniqueSections = $survey->sections->unique(function ($section) {
            return strtolower(trim($section->title));
        })->values();
        
        if ($uniqueSections->count() !== $survey->sections->count()) {
            \Log::warning("Detected {$survey->sections->count()} sections but only {$uniqueSections->count()} unique by title for survey {$id}");
            $survey->setRelation('sections', $uniqueSections);
        }

        // Agregar contadores para debug
        $survey->sections_count = $survey->sections->count();
        $survey->questions_count = $survey->surveyQuestions->count();
        $survey->has_content = $survey->sections->count() > 0 || $survey->surveyQuestions->count() > 0;

        // Si no tiene contenido, verificar si hay secciones o preguntas huérfanas y reparar automáticamente
        if (!$survey->has_content || $survey->sections->count() === 0 || $survey->surveyQuestions->count() === 0) {
            \Log::info("getSurveyDetails - Detecting potential data integrity issues for survey {$id}");
            
            // Buscar secciones que podrían estar relacionadas solo para esta encuesta específica
            $potentialSections = \DB::table('sections')
                ->where('id_survey', $id)
                ->get();

            // Buscar preguntas que podrían estar relacionadas vía secciones
            $orphanQuestions = collect();
            if ($potentialSections->count() > 0) {
                $sectionIds = $potentialSections->pluck('id')->toArray();
                $orphanQuestions = \DB::table('questions')
                    ->whereIn('section_id', $sectionIds)
                    ->whereNotIn('id', function($query) use ($id) {
                        $query->select('question_id')
                              ->from('survey_questions')
                              ->where('survey_id', $id);
                    })
                    ->get();
            }

            $survey->debug_info = [
                'potential_sections' => $potentialSections->count(),
                'orphan_questions' => $orphanQuestions->count(),
                'auto_repair_available' => $orphanQuestions->count() > 0,
                'suggestion' => $orphanQuestions->count() > 0 
                    ? 'Auto-repairing survey relations...' 
                    : 'No orphaned relations detected'
            ];
            
            // Auto-reparación si hay preguntas huérfanas
            if ($orphanQuestions->count() > 0) {
                \Log::info("getSurveyDetails - Auto-repairing {$orphanQuestions->count()} orphan questions for survey {$id}");
                
                $repairedCount = 0;
                foreach ($orphanQuestions as $question) {
                    try {
                        \DB::table('survey_questions')->insert([
                            'survey_id' => $id,
                            'question_id' => $question->id,
                            'section_id' => $question->section_id,
                            'creator_id' => 1,
                            'status' => true,
                            'user_id' => 1,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $repairedCount++;
                    } catch (\Exception $e) {
                        \Log::warning("Failed to repair question {$question->id}: " . $e->getMessage());
                    }
                }
                
                if ($repairedCount > 0) {
                    \Log::info("getSurveyDetails - Successfully auto-repaired {$repairedCount} questions for survey {$id}");
                    $survey->debug_info['auto_repaired_questions'] = $repairedCount;
                    
                    // Recargar las relaciones después de la reparación
                    $survey->load(['surveyQuestions.question.type', 'surveyQuestions.question.options', 'surveyQuestions.section']);
                    $survey->questions_count = $survey->surveyQuestions->count();
                    $survey->has_content = $survey->sections->count() > 0 || $survey->surveyQuestions->count() > 0;
                }
            }
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
    
    $newStatus = null;
    $newPublicationStatus = $survey->publication_status; // Mantener el actual por defecto
    
    // Si la fecha de fin ya pasó, marcar como finalizada
    if ($endDate < $now) {
        $newStatus = 'Finalizada';
        $newPublicationStatus = 'finished'; // Nuevo estado para encuestas finalizadas
    }
    // Si faltan 3 días o menos para finalizar
    elseif ($daysUntilEnd <= 3 && $daysUntilEnd >= 0) {
        $newStatus = 'Próxima a Finalizar';
        // Mantener published si ya estaba publicada
        if ($survey->publication_status === 'published') {
            $newPublicationStatus = 'published';
        }
    }
    // Si está dentro del rango de fechas y publicada
    elseif ($startDate <= $now && $now <= $endDate) {
        $newStatus = 'Activa';
        // Mantener published si ya estaba publicada
        if ($survey->publication_status === 'published') {
            $newPublicationStatus = 'published';
        }
    }
    // Si aún no ha comenzado
    else {
        $newStatus = 'Programada';
        $newPublicationStatus = 'scheduled'; // Nuevo estado para encuestas programadas
    }
    
    // Actualizar el objeto en memoria para respuesta inmediata
    $survey->survey_status = $newStatus;
    
    // Solo actualizar en base de datos si hay cambios y la encuesta está finalizada
    // Esto evita múltiples actualizaciones innecesarias pero garantiza que las finalizadas se persistan
    if ($newStatus === 'Finalizada' && $survey->publication_status !== 'finished') {
        try {
            \DB::table('surveys')
                ->where('id', $survey->id)
                ->update([
                    'publication_status' => 'finished',
                    'updated_at' => now()
                ]);
            
            // Actualizar el objeto en memoria también
            $survey->publication_status = 'finished';
            
            \Log::info("Survey ID {$survey->id} automatically marked as finished due to end date");
        } catch (\Exception $e) {
            \Log::error("Failed to update survey status for ID {$survey->id}: " . $e->getMessage());
        }
    }
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
            'publication_status' => 'required|in:draft,unpublished,published,finished,scheduled',
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
     * Contar respuestas de una encuesta específica
     */
    public function getResponsesCount($id)
    {
        try {
            $survey = SurveyModel::find($id);
            if (!$survey) {
                return response()->json(['message' => 'Survey not found'], 404);
            }

            // Contar respuestas desde la tabla notificationsurvays donde se almacenan las respuestas reales
            $count = \DB::table('notificationsurvays')
                ->where('id_survey', $id)
                ->where('state_results', true) // Solo contar respuestas completadas
                ->whereNotNull('respondent_name') // Asegurar que hay un encuestado válido
                ->count();

            return response()->json([
                'survey_id' => $id,
                'responses_count' => $count
            ], 200);

        } catch (\Exception $e) {
            \Log::error("Error counting responses for survey {$id}: " . $e->getMessage());
            
            return response()->json([
                'error' => 'Error counting responses',
                'message' => $e->getMessage(),
                'survey_id' => $id
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

/**
 * Migrar estados de encuestas existentes para sincronizar con fechas
 * Útil para corregir encuestas que tienen publication_status incorrecto
 */
public function migrateSurveyStates()
{
    try {
        $surveys = SurveyModel::whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->where('status', true) // Solo encuestas publicadas
                            ->get();
        
        $migratedCount = 0;
        $report = [];
        
        foreach ($surveys as $survey) {
            $now = now();
            $endDate = $survey->end_date;
            $startDate = $survey->start_date;
            
            $shouldBeFinished = $endDate < $now;
            $shouldBeScheduled = $startDate > $now;
            
            $needsUpdate = false;
            $newStatus = $survey->publication_status;
            
            // Si debería estar finalizada pero no lo está
            if ($shouldBeFinished && $survey->publication_status !== 'finished') {
                $newStatus = 'finished';
                $needsUpdate = true;
            }
            // Si debería estar programada pero no lo está
            elseif ($shouldBeScheduled && $survey->publication_status !== 'scheduled') {
                $newStatus = 'scheduled';
                $needsUpdate = true;
            }
            // Si está en rango y debería estar publicada
            elseif (!$shouldBeFinished && !$shouldBeScheduled && $survey->publication_status !== 'published') {
                $newStatus = 'published';
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $oldStatus = $survey->publication_status;
                $survey->publication_status = $newStatus;
                $survey->save();
                
                $report[] = [
                    'id' => $survey->id,
                    'title' => $survey->title,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'start_date' => $survey->start_date,
                    'end_date' => $survey->end_date
                ];
                
                $migratedCount++;
                \Log::info("Migrated survey {$survey->id} from '{$oldStatus}' to '{$newStatus}'");
            }
        }
        
        return response()->json([
            'message' => 'Migration completed successfully',
            'surveys_checked' => $surveys->count(),
            'surveys_migrated' => $migratedCount,
            'migrations' => $report
        ], 200);
        
    } catch (\Exception $e) {
        \Log::error("Error in migrateSurveyStates: " . $e->getMessage());
        
        return response()->json([
            'error' => 'Migration failed',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Actualizar automáticamente los estados de encuestas basándose en fechas
 * Se ejecuta silenciosamente en cada consulta de encuestas
 */
private function updateSurveyStatesAutomatic()
{
    try {
        $surveys = SurveyModel::whereNotNull('start_date')
                            ->whereNotNull('end_date')
                            ->where('status', true)
                            ->get();
        
        foreach ($surveys as $survey) {
            $now = now();
            $endDate = $survey->end_date;
            $startDate = $survey->start_date;
            
            $shouldBeFinished = $endDate < $now;
            $shouldBeScheduled = $startDate > $now;
            
            $needsUpdate = false;
            $newStatus = $survey->publication_status;
            
            // Determinar el estado correcto
            if ($shouldBeFinished && $survey->publication_status !== 'finished') {
                $newStatus = 'finished';
                $needsUpdate = true;
            }
            elseif ($shouldBeScheduled && $survey->publication_status !== 'scheduled') {
                $newStatus = 'scheduled';
                $needsUpdate = true;
            }
            elseif (!$shouldBeFinished && !$shouldBeScheduled && 
                    !in_array($survey->publication_status, ['published', 'finished'])) {
                $newStatus = 'published';
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                $survey->publication_status = $newStatus;
                $survey->save();
                \Log::info("Auto-updated Survey {$survey->id} from '{$survey->getOriginal('publication_status')}' to '{$newStatus}'");
            }
        }
    } catch (\Exception $e) {
        // Fallar silenciosamente para no interrumpir la consulta principal
        \Log::error("Error in updateSurveyStatesAutomatic: " . $e->getMessage());
    }
}

/**
 * Depurar relaciones de encuesta - útil para diagnosticar problemas
 */
public function debugRelations($id)
{
    try {
        $survey = SurveyModel::findOrFail($id);
        
        // Obtener secciones directamente
        $sectionsRaw = DB::table('sections')
            ->where('id_survey', $id)
            ->get();
            
        // Obtener preguntas directamente (las preguntas no tienen survey_id directo, se relacionan vía pivot)
        $questionsRaw = DB::table('survey_questions')
            ->join('questions', 'survey_questions.question_id', '=', 'questions.id')
            ->where('survey_questions.survey_id', $id)
            ->select('questions.*', 'survey_questions.section_id as pivot_section_id')
            ->get();
            
        // Obtener relaciones de la tabla pivot
        $pivotRelations = DB::table('survey_questions')
            ->where('survey_id', $id)
            ->get();
            
        // Detectar preguntas huérfanas (preguntas en secciones de esta encuesta pero sin relación en pivot)
        $sectionIds = $sectionsRaw->pluck('id')->toArray();
        $orphanQuestions = collect();
        
        if (!empty($sectionIds)) {
            $orphanQuestions = DB::table('questions')
                ->whereIn('section_id', $sectionIds)
                ->whereNotIn('id', function($query) use ($id) {
                    $query->select('question_id')
                          ->from('survey_questions')
                          ->where('survey_id', $id);
                })
                ->get();
        }
        
        $problemDetected = $questionsRaw->count() > 0 && $pivotRelations->count() === 0;
        
        return response()->json([
            'survey_id' => $id,
            'sections_count' => $sectionsRaw->count(),
            'questions_count' => $questionsRaw->count(),
            'pivot_relations_count' => $pivotRelations->count(),
            'orphan_questions_count' => $orphanQuestions->count(),
            'problem_detected' => $problemDetected,
            'sections_raw_query' => $sectionsRaw,
            'questions_raw_query' => $questionsRaw,
            'pivot_relations' => $pivotRelations,
            'orphan_questions' => $orphanQuestions,
            'analysis' => [
                'has_sections' => $sectionsRaw->count() > 0,
                'has_questions' => $questionsRaw->count() > 0,
                'has_relations' => $pivotRelations->count() > 0,
                'consistency_issue' => $problemDetected
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Error debugging relations: ' . $e->getMessage()
        ], 500);
    }
}

/**
 * Reparar relaciones faltantes de preguntas en la tabla pivot
 */
public function repairQuestions($id)
{
    try {
        $survey = SurveyModel::findOrFail($id);
        
        // Obtener todas las secciones de esta encuesta
        $sections = DB::table('sections')
            ->where('id_survey', $id)
            ->get();
        
        if ($sections->isEmpty()) {
            return response()->json([
                'success' => false,
                'error' => 'La encuesta no tiene secciones. No se pueden reparar preguntas sin secciones.',
                'questions_repaired' => 0
            ], 400);
        }
        
        $sectionIds = $sections->pluck('id')->toArray();
        
        // Encontrar preguntas que pertenecen a las secciones de esta encuesta 
        // pero que no están en la tabla pivot survey_questions
        $orphanQuestions = DB::table('questions')
            ->whereIn('section_id', $sectionIds)
            ->whereNotIn('id', function($query) use ($id) {
                $query->select('question_id')
                      ->from('survey_questions')
                      ->where('survey_id', $id);
            })
            ->get();
        
        $repaired = 0;
        $errors = [];
        
        // Obtener información del usuario autenticado
        $user = auth()->user();
        $userId = $user ? $user->id : 1; // Fallback a usuario ID 1 si no hay autenticación
        $userString = $user ? $user->username ?? $user->email ?? "system" : "system";
        
        foreach ($orphanQuestions as $question) {
            try {
                // Verificar que la pregunta tenga section_id válido
                if (!$question->section_id || !in_array($question->section_id, $sectionIds)) {
                    $errors[] = "Question {$question->id} has invalid section_id {$question->section_id}";
                    continue;
                }
                
                // Crear la relación faltante en la tabla pivot con todos los campos requeridos
                DB::table('survey_questions')->insert([
                    'survey_id' => $id,
                    'question_id' => $question->id,
                    'section_id' => $question->section_id,
                    'creator_id' => $userId,
                    'status' => true,
                    'user_id' => $userString,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $repaired++;
                \Log::info("Repaired relation for question {$question->id} in survey {$id}, section {$question->section_id}");
                
            } catch (\Exception $e) {
                $errors[] = "Error repairing question {$question->id}: " . $e->getMessage();
                \Log::error("Error repairing question {$question->id}: " . $e->getMessage());
            }
        }
        
        return response()->json([
            'success' => $repaired > 0,
            'questions_repaired' => $repaired,
            'total_orphan_questions' => $orphanQuestions->count(),
            'sections_found' => $sections->count(),
            'errors' => $errors,
            'message' => $repaired > 0 
                ? "Se repararon {$repaired} relaciones de preguntas en {$sections->count()} secciones"
                : "No se encontraron relaciones que reparar"
        ]);
        
    } catch (\Exception $e) {
        \Log::error("Error in repairQuestions for survey {$id}: " . $e->getMessage());
        return response()->json([
            'success' => false,
            'error' => 'Error reparando relaciones: ' . $e->getMessage(),
            'questions_repaired' => 0
        ], 500);
    }
}
}
