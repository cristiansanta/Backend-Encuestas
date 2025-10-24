<?php

namespace App\Http\Controllers;

use App\Models\QuestionModel;
use App\Models\QuestionsoptionsModel;
use App\Models\SurveyquestionsModel;
use App\Models\User;
use App\Services\QuestionIntegrityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

use Exception;
class QuestionController extends Controller
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

        $query = QuestionModel::query();

        // MODIFICADO: Si se solicita el banco (bank=true), mostrar TODAS las preguntas del banco
        // Si NO se solicita el banco, aplicar filtros de visibilidad normales
        if ($request->has('bank') && $request->bank == 'true') {
            // Para el banco de preguntas, mostrar TODAS las preguntas que tengan bank=true
            // Esto permite que todos los usuarios vean y reutilicen preguntas del banco
            $query->where('bank', true);
        } else {
            // Para preguntas NO del banco, aplicar filtros de visibilidad según permisos
            $query->where(function($q) use ($user) {
                // Incluir preguntas del propio usuario
                $q->where('creator_id', $user->id);

                // Incluir preguntas de otros usuarios que tengan el permiso habilitado
                $q->orWhereHas('creator', function($userQuery) {
                    $userQuery->where('allow_view_questions_categories', true);
                });
            });
        }
        
        // Filtrar por tipo de pregunta si se especifica
        if ($request->has('type')) {
            $query->where('type_questions_id', $request->type);
        }
        
        // Filtrar por sección si se especifica
        if ($request->has('section_id')) {
            $query->where('section_id', $request->section_id);
        }
        
        // Incluir relaciones si se solicita
        if ($request->has('with_details')) {
            $query->with(['type', 'options', 'creator:id,name']);
        } else {
            // Siempre incluir información básica del creador para mostrar la propiedad
            $query->with(['creator:id,name']);
        }
        
        $questions = $query->get();
        return response()->json($questions);
    }
public function store(Request $request)
{
    // Obtener el usuario autenticado
    $user = $request->user();
    
    if (!$user) {
        return response()->json(['message' => 'Usuario no autenticado'], 401);
    }
    
    // Validar los datos recibidos
    $validator = Validator::make($request->all(), [
        'title' => 'required|string|max:255',        
        'descrip' => 'nullable|string',
        'validate' => 'required|string|max:255',
        'cod_padre' => 'required|integer',
        'bank' => 'required|boolean',
        'type_questions_id' => 'required|integer',
        'questions_conditions' => 'required|boolean',
        'mother_answer_condition' => 'nullable|string|max:500',
        'section_id' => 'nullable|integer', // Añadido para soportar secciones
        'options' => 'nullable|array', // Añadido para soportar opciones
        'options.*' => 'string|max:255', // Validar cada opción
        'character_limit' => 'nullable|integer|min:1|max:250', // Límite de caracteres para preguntas abiertas
        'survey_id' => 'nullable|integer', // Añadido para asociar preguntas padre con encuestas
        'imported_from_bank' => 'nullable|boolean', // Para omitir validación de duplicados en preguntas importadas
    ]);

    if ($validator->fails()) {
        return response()->json([
            'error' => 'Error de validación',
            'details' => $validator->errors()
        ], 422); 
    }

    $data = $request->all();
    // Asignar automáticamente el creator_id del usuario autenticado
    $data['creator_id'] = $user->id;
    
    // Asignar valor por defecto para related_question si no se proporciona
    if (!isset($data['related_question'])) {
        $data['related_question'] = '';
    }

    // Buscar y decodificar imágenes en base64 dentro de la descripción
    if (preg_match_all('/<img src="data:image\/([^;]+);base64,([^"]+)"/', $data['descrip'], $matches)) {
        foreach ($matches[2] as $key => $base64Image) {
            try {
                // Obtener el tipo MIME de la imagen
                $mimeType = $matches[1][$key];
                
                // Validar tipos de archivo permitidos
                $allowedTypes = ['png', 'jpeg', 'jpg', 'svg+xml'];
                if (!in_array(strtolower($mimeType), $allowedTypes)) {
                    \Log::warning('Tipo de imagen no permitido en pregunta: ' . $mimeType);
                    continue; // Saltar esta imagen
                }
                
                // Decodificar la imagen base64
                $imageData = base64_decode($base64Image);
                
                // Validar que la decodificación fue exitosa
                if ($imageData === false) {
                    \Log::error('Error al decodificar imagen base64 en pregunta');
                    continue;
                }
                
                // Determinar la extensión correcta del archivo
                $extension = $this->getImageExtension($mimeType);
                $imageName = uniqid() . '.' . $extension;
                
                // Almacenar la imagen en el sistema de archivos privado
                $stored = Storage::disk('private')->put('images/' . $imageName, $imageData);
                
                if ($stored) {
                    // Establecer permisos explícitos para que el servidor web pueda leer la imagen
                    $fullPath = storage_path('app/private/images/' . $imageName);
                    chmod($fullPath, 0644);
                    
                    // Reemplazar la imagen base64 por la ruta del FileController
                    $storagePath = '/api/storage/images/' . $imageName;
                    $data['descrip'] = str_replace($matches[0][$key], '<img src="' . $storagePath . '"', $data['descrip']);
                    \Log::info('Imagen de pregunta guardada exitosamente: ' . $imageName);
                } else {
                    \Log::error('Error al guardar imagen de pregunta: ' . $imageName);
                }
            } catch (\Exception $e) {
                \Log::error('Error procesando imagen en pregunta: ' . $e->getMessage());
                continue;
            }
        }
    }

    // CRÍTICO: SIEMPRE verificar duplicados, incluso para preguntas del banco
    // La detección de duplicados debe aplicarse a TODAS las preguntas
    $existingQuestion = null;

    // VALIDACIÓN ESPECIAL PARA PREGUNTAS DEL BANCO
    if (isset($data['bank']) && $data['bank'] === true) {
        // Para preguntas del banco, verificar duplicados de forma más estricta:
        // 1. Verificar si existe una pregunta con el mismo título dentro de la misma sección
        // 2. Verificar si existe una pregunta con el mismo título en CUALQUIER sección del banco

        // Verificación 1: Duplicado en la misma sección
        if (isset($data['section_id']) && $data['section_id']) {
            $duplicateInSection = QuestionModel::where('title', $data['title'])
                                               ->where('bank', true)
                                               ->where('section_id', $data['section_id'])
                                               ->first();

            if ($duplicateInSection) {
                \Log::info('Bank question duplicate detected in same section', [
                    'existing_id' => $duplicateInSection->id,
                    'title' => $data['title'],
                    'section_id' => $data['section_id'],
                    'creator_id' => $duplicateInSection->creator_id
                ]);

                return response()->json([
                    'error' => 'Ya existe una pregunta con este nombre en la misma sección del banco',
                    'duplicate_in_section' => true,
                    'existing_question_id' => $duplicateInSection->id,
                    'section_id' => $data['section_id']
                ], 422);
            }
        }

        // Verificación 2: Duplicado global en todo el banco (cualquier sección)
        $duplicateInBank = QuestionModel::where('title', $data['title'])
                                        ->where('bank', true)
                                        ->first();

        if ($duplicateInBank) {
            \Log::info('Bank question duplicate detected globally', [
                'existing_id' => $duplicateInBank->id,
                'title' => $data['title'],
                'existing_section_id' => $duplicateInBank->section_id,
                'requested_section_id' => $data['section_id'] ?? null,
                'creator_id' => $duplicateInBank->creator_id
            ]);

            return response()->json([
                'error' => 'Ya existe una pregunta con este nombre en el banco de preguntas',
                'duplicate_global' => true,
                'existing_question_id' => $duplicateInBank->id,
                'existing_section_id' => $duplicateInBank->section_id,
                'suggested_action' => 'Por favor, utilice un nombre diferente para esta pregunta'
            ], 422);
        }

        // Si no se encontraron duplicados en el banco, continuar con la creación
        $existingQuestion = null;

    } else {
        // MEJORADO: Verificar duplicados por título, tipo, sección y usuario (para preguntas NO del banco)
        $duplicateQuery = QuestionModel::where('title', $data['title'])
                                      ->where('type_questions_id', $data['type_questions_id'])
                                      ->where('creator_id', $user->id);

        // CRÍTICO: Para preguntas del banco, incluir section_id en la búsqueda
        if (isset($data['section_id']) && $data['section_id']) {
            $duplicateQuery->where('section_id', $data['section_id']);
        }

        // CRÍTICO: Para preguntas hijas (cod_padre > 0), incluir cod_padre en la búsqueda
        if (isset($data['cod_padre']) && $data['cod_padre'] > 0) {
            $duplicateQuery->where('cod_padre', $data['cod_padre']);
            \Log::info('Child question duplicate check', [
                'title' => $data['title'],
                'cod_padre' => $data['cod_padre'],
                'section_id' => $data['section_id'] ?? null,
                'user_id' => $user->id
            ]);
        }

        $existingQuestion = $duplicateQuery->first();
    }

    if ($existingQuestion) {
        \Log::info('Question already exists - preventing duplication', [
            'existing_id' => $existingQuestion->id,
            'title' => $data['title'],
            'section_id' => $data['section_id'] ?? null,
            'bank' => $data['bank'] ?? false,
            'imported_from_bank' => $data['imported_from_bank'] ?? false
        ]);
    }
    
    // NUEVO: SIEMPRE buscar una pregunta existente para actualizar - NUNCA crear nuevas
    $questionToUpdate = null;
    if (!$existingQuestion) {
        // ESTRATEGIA: Buscar preguntas candidatas para actualizar en orden de prioridad
        
        if (isset($data['cod_padre']) && $data['cod_padre'] > 0) {
            // Para preguntas hijas: buscar una pregunta hija específica para actualizar
            // Solo actualizar si hay una coincidencia exacta de título O si es el mismo tipo y se está editando
            $childQuestions = QuestionModel::where('cod_padre', $data['cod_padre'])
                                          ->where('creator_id', $user->id)
                                          ->get();
            
            \Log::info('SELECTIVE_UPDATE: Child questions found for potential update', [
                'cod_padre' => $data['cod_padre'],
                'child_questions_count' => $childQuestions->count(),
                'child_question_ids' => $childQuestions->pluck('id')->toArray(),
                'child_question_titles' => $childQuestions->pluck('title')->toArray(),
                'incoming_title' => $data['title']
            ]);
            
            // PRIORITIZE: If question_id is provided, use it first (for editing existing questions)
            $questionToUpdate = null;
            if (isset($data['question_id']) && $data['question_id'] > 0) {
                $questionToUpdate = $childQuestions->where('id', $data['question_id'])->first();
                \Log::info('SELECTIVE_UPDATE: Found question to update by ID', [
                    'question_id' => $data['question_id'],
                    'found' => $questionToUpdate ? 'yes' : 'no'
                ]);
            }
            
            // FALLBACK: If no question_id provided, try exact title match (for new questions with same title)
            if (!$questionToUpdate) {
                $questionToUpdate = $childQuestions->where('title', $data['title'])->first();
                \Log::info('SELECTIVE_UPDATE: Searching by title match', [
                    'title' => $data['title'],
                    'found' => $questionToUpdate ? 'yes' : 'no'
                ]);
            }
            
            \Log::info('SELECTIVE_UPDATE: Child question selected for update', [
                'selected_question_id' => $questionToUpdate ? $questionToUpdate->id : null,
                'selected_question_title' => $questionToUpdate ? $questionToUpdate->title : null,
                'selection_method' => $questionToUpdate ? 'exact_match_found' : 'no_match_create_new',
                'will_create_new' => !$questionToUpdate
            ]);
            
        } else {
            // Para preguntas padre: NO aplicar lógica de actualización automática
            // Las preguntas padre del banco son únicas y no deben actualizarse automáticamente
            // Solo se debe verificar duplicados (ya hecho arriba)
            $questionToUpdate = null;

            \Log::info('Parent question - no automatic update logic', [
                'user_id' => $user->id,
                'title' => $data['title'],
                'bank' => $data['bank'] ?? false,
                'section_id' => $data['section_id'] ?? null
            ]);
            
            \Log::info('NEVER_CREATE: Parent question selected for update', [
                'selected_question_id' => $questionToUpdate ? $questionToUpdate->id : null,
                'selected_question_title' => $questionToUpdate ? $questionToUpdate->title : null,
                'selection_method' => $questionToUpdate ? 'found_candidate' : 'no_candidate_found'
            ]);
        }
    }
    
    // Log resultado de verificaciones
    if ($existingQuestion || $questionToUpdate) {
        \Log::info('Question check result', [
            'found_duplicate' => $existingQuestion ? true : false,
            'found_question_to_update' => $questionToUpdate ? true : false,
            'question_id' => $existingQuestion ? $existingQuestion->id : ($questionToUpdate ? $questionToUpdate->id : null)
        ]);
    }

    if ($existingQuestion) {
        // EXCEPCIÓN: Si se está importando desde el banco individualmente, permitir reutilización
        $isImportedFromBank = isset($data['imported_from_bank']) && $data['imported_from_bank'] === true;
        $hasSurveyId = isset($data['survey_id']) && $data['survey_id'];

        // Si es una pregunta del banco y está siendo importada a una encuesta
        if ($isImportedFromBank && $hasSurveyId && $existingQuestion->bank === true) {
            \Log::info('Question imported from bank - associating with survey', [
                'question_id' => $existingQuestion->id,
                'survey_id' => $data['survey_id'],
                'title' => $data['title'],
                'action' => 'reusing_bank_question'
            ]);

            // Verificar si ya está asociada con esta encuesta
            $alreadyInSurvey = SurveyquestionsModel::where('survey_id', $data['survey_id'])
                                                    ->where('question_id', $existingQuestion->id)
                                                    ->exists();

            if (!$alreadyInSurvey) {
                // Asociar la pregunta existente del banco con la encuesta
                SurveyquestionsModel::create([
                    'survey_id' => $data['survey_id'],
                    'question_id' => $existingQuestion->id,
                    'section_id' => $data['section_id'],
                    'creator_id' => $user->id,
                    'user_id' => $user->id,
                    'status' => true,
                ]);

                \Log::info('Bank question associated with survey', [
                    'question_id' => $existingQuestion->id,
                    'survey_id' => $data['survey_id']
                ]);
            } else {
                \Log::info('Bank question already associated with survey', [
                    'question_id' => $existingQuestion->id,
                    'survey_id' => $data['survey_id']
                ]);
            }

            // Retornar la pregunta existente
            return response()->json([
                'id' => $existingQuestion->id,
                'message' => 'Pregunta del banco reutilizada exitosamente',
                'reused_from_bank' => true
            ], 200);
        } else {
            // Log para debugging mejorado
            \Log::info('Question duplicate detected', [
                'existing_id' => $existingQuestion->id,
                'existing_title' => $existingQuestion->title,
                'existing_cod_padre' => $existingQuestion->cod_padre,
                'existing_section_id' => $existingQuestion->section_id,
                'requested_title' => $data['title'],
                'requested_cod_padre' => $data['cod_padre'] ?? 0,
                'requested_section_id' => $data['section_id'],
                'user_id' => $user->id,
                'is_child_question' => isset($data['cod_padre']) && $data['cod_padre'] > 0
            ]);

            $messageType = isset($data['cod_padre']) && $data['cod_padre'] > 0
                ? 'pregunta hija'
                : 'pregunta';

            return response()->json([
                'id' => $existingQuestion->id,
                'message' => "La {$messageType} ya fue creada exitosamente (duplicado detectado)",
                'existing' => true,
                'duplicate_reason' => isset($data['cod_padre']) && $data['cod_padre'] > 0
                    ? 'same_title_description_type_parent'
                    : 'same_title_description_type',
                'note' => isset($data['cod_padre']) && $data['cod_padre'] > 0
                    ? 'Las preguntas hijas duplicadas se detectan por contenido Y relación padre-hija'
                    : 'Las preguntas duplicadas se detectan por contenido'
            ], 200);
        }
    }
    
    // NUEVO: Lógica de actualización completa para cualquier campo
    if ($questionToUpdate) {
        try {
            // Capturar valores originales para el log
            $originalValues = [
                'title' => $questionToUpdate->title,
                'descrip' => $questionToUpdate->descrip,
                'validate' => $questionToUpdate->validate,
                'character_limit' => $questionToUpdate->character_limit,
                'type_questions_id' => $questionToUpdate->type_questions_id,
                'section_id' => $questionToUpdate->section_id,
                'questions_conditions' => $questionToUpdate->questions_conditions,
                'mother_answer_condition' => $questionToUpdate->mother_answer_condition,
                'related_question' => $questionToUpdate->related_question
            ];
            
            // Actualizar TODOS los campos que vengan en $data
            if (isset($data['title'])) $questionToUpdate->title = $data['title'];
            if (isset($data['descrip'])) $questionToUpdate->descrip = $data['descrip'];
            if (isset($data['validate'])) $questionToUpdate->validate = $data['validate'];
            if (isset($data['character_limit'])) $questionToUpdate->character_limit = $data['character_limit'];
            if (isset($data['type_questions_id'])) $questionToUpdate->type_questions_id = $data['type_questions_id'];
            if (isset($data['section_id'])) $questionToUpdate->section_id = $data['section_id'];
            if (isset($data['questions_conditions'])) $questionToUpdate->questions_conditions = $data['questions_conditions'];
            if (isset($data['mother_answer_condition'])) $questionToUpdate->mother_answer_condition = $data['mother_answer_condition'];
            if (isset($data['related_question'])) $questionToUpdate->related_question = $data['related_question'];
            
            $questionToUpdate->updated_at = now();
            $questionToUpdate->save();
            
            // Identificar qué campos cambiaron
            $changedFields = [];
            foreach ($originalValues as $field => $originalValue) {
                if (isset($data[$field]) && $data[$field] != $originalValue) {
                    $changedFields[$field] = [
                        'old' => $originalValue,
                        'new' => $data[$field]
                    ];
                }
            }
            
            \Log::info('Question updated successfully', [
                'question_id' => $questionToUpdate->id,
                'question_type' => isset($data['cod_padre']) && $data['cod_padre'] > 0 ? 'child' : 'parent',
                'cod_padre' => $questionToUpdate->cod_padre,
                'changed_fields' => $changedFields,
                'operation' => 'complete_update_instead_of_creation'
            ]);
            
            $questionType = isset($data['cod_padre']) && $data['cod_padre'] > 0 ? 'hija' : 'padre';
            $changedFieldsList = implode(', ', array_keys($changedFields));
            
            return response()->json([
                'id' => $questionToUpdate->id,
                'message' => "Pregunta {$questionType} actualizada exitosamente",
                'updated' => true,
                'operation' => 'complete_field_update',
                'changed_fields' => $changedFields,
                'fields_updated' => $changedFieldsList,
                'note' => 'La pregunta existente fue actualizada completamente en lugar de crear una nueva'
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error updating question', [
                'question_id' => $questionToUpdate->id,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            // Si falla la actualización, NO crear - devolver error
            return response()->json([
                'error' => 'No se pudo actualizar la pregunta existente',
                'message' => 'Error en la actualización - creación de preguntas deshabilitada',
                'question_id' => $questionToUpdate->id
            ], 500);
        }
    }
    
    // Permitir creación de preguntas si no hay nada que actualizar
    if (!$existingQuestion && !$questionToUpdate) {
        $isChildQuestion = isset($data['cod_padre']) && $data['cod_padre'] > 0;
        $isBankQuestion = isset($data['bank']) && $data['bank'] === true;

        \Log::info('QUESTION_CREATION: Allowing creation of new question', [
            'is_child_question' => $isChildQuestion,
            'is_bank_question' => $isBankQuestion,
            'user_id' => $user->id,
            'title' => $data['title'],
            'type_questions_id' => $data['type_questions_id'],
            'policy' => 'ALLOW_CREATION'
        ]);

        // Continuar con la creación normal para todas las preguntas
    }

    try {
        // Separar las opciones y survey_id de los datos de la pregunta
        $options = $request->input('options', []);
        unset($data['options']); // Remover opciones de los datos de la pregunta
        unset($data['survey_id']); // Remover survey_id de los datos de la pregunta (no es campo de tabla questions)
        
        // POLICY: Only allow creation if no existing question was found to update
        $isChildQuestion = isset($data['cod_padre']) && $data['cod_padre'] > 0;
        
        if (!$isChildQuestion) {
            \Log::warning('SELECTIVE_CREATE: Parent question creation attempted', [
                'user_id' => $user->id,
                'title' => $data['title'] ?? 'unknown',
                'message' => 'Permitiendo creación de pregunta padre solo si no existe candidato para actualizar'
            ]);
            
            // Allow parent question creation in certain cases
            // You can uncomment the return below if you want to strictly disable parent creation
            /*
            return response()->json([
                'error' => 'Creación de preguntas padre deshabilitada',
                'message' => 'Solo se permite crear preguntas hijas, no preguntas padre',
                'policy' => 'NEVER_CREATE_PARENTS_ONLY_CHILDREN',
            ], 422);
            */
        }
        
        \Log::info('QUESTION_CREATE: Creating new question', [
            'cod_padre' => $data['cod_padre'] ?? 0,
            'user_id' => $user->id,
            'title' => $data['title'],
            'type' => $isChildQuestion ? 'child' : 'parent',
            'reason' => 'no_existing_question_found_to_update'
        ]);
        
        // HABILITADO: Código de creación para preguntas hijas
        
        // Validar integridad antes de crear
        try {
            QuestionIntegrityService::validateQuestionType($data['type_questions_id']);
            
            // Validar referencial integrity para sección
            if ($data['section_id']) {
                $sectionExists = DB::table('sections')->where('id', $data['section_id'])->exists();
                if (!$sectionExists) {
                    return response()->json(['error' => 'Error de integridad referencial', 'details' => 'La sección especificada no existe'], 422);
                }
            }
            
            // Validar referencial integrity para pregunta padre
            if ($data['cod_padre'] && $data['cod_padre'] > 0) {
                $parentExists = QuestionModel::where('id', $data['cod_padre'])
                    ->where('creator_id', $user->id)
                    ->exists();
                if (!$parentExists) {
                    return response()->json(['error' => 'Error de integridad referencial', 'details' => 'La pregunta padre especificada no existe o no pertenece al usuario'], 422);
                }
            }
            
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => 'Error de validación de tipo', 'details' => $e->getMessage()], 422);
        }
        
        $question = QuestionModel::create($data);
        
        // HABILITADO: Auditoría después de crear
        QuestionIntegrityService::auditQuestionIntegrityChange('CREATE', null, $question, $user->id);
        
        // Crear opciones si fueron proporcionadas
        if (!empty($options) && is_array($options)) {
            foreach ($options as $option) {
                // Manejar tanto strings como objetos con propiedad 'option'
                $optionText = is_array($option) ? ($option['option'] ?? $option['text'] ?? '') : $option;
                
                if (!empty($optionText)) {
                    QuestionsoptionsModel::create([
                        'questions_id' => $question->id,
                        'options' => $optionText,
                        'creator_id' => $user->id,
                        'status' => true,
                    ]);
                }
            }
        }
        
        // Si es una pregunta padre (cod_padre = 0) y se proporciona survey_id, agregarla a la encuesta
        if ($question->cod_padre == 0 && $request->has('survey_id') && $request->survey_id) {
            $surveyId = $request->survey_id;
            
            // Verificar que la encuesta existe
            $surveyExists = DB::table('surveys')->where('id', $surveyId)->exists();
            
            if ($surveyExists) {
                // Verificar si la pregunta ya está asociada con esta encuesta
                $existingInSurvey = SurveyquestionsModel::where('survey_id', $surveyId)
                                                        ->where('question_id', $question->id)
                                                        ->exists();
                
                if (!$existingInSurvey) {
                    // Agregar la pregunta padre a la encuesta
                    SurveyquestionsModel::create([
                        'survey_id' => $surveyId,
                        'question_id' => $question->id,
                        'section_id' => $question->section_id, // Usar la sección de la pregunta
                        'creator_id' => $user->id,
                        'user_id' => $user->id,
                        'status' => true,
                    ]);
                    
                    \Log::info("Parent question {$question->id} added to survey {$surveyId}");
                } else {
                    \Log::info("Parent question {$question->id} already exists in survey {$surveyId}");
                }
            } else {
                \Log::warning("Survey {$surveyId} does not exist, cannot add parent question {$question->id}");
            }
        }
        
        // Si es una pregunta hija (cod_padre > 0), asociarla automáticamente con las encuestas de su padre
        if ($question->cod_padre && $question->cod_padre > 0) {
            // Verificar que la pregunta padre existe
            $parentQuestion = QuestionModel::find($question->cod_padre);
            if (!$parentQuestion) {
                \Log::warning("Child question {$question->id} has invalid parent ID: {$question->cod_padre}");
                return response()->json([
                    'error' => 'La pregunta padre no existe',
                    'message' => 'No se puede crear una pregunta hija con un padre inexistente',
                    'parent_id' => $question->cod_padre
                ], 422);
            }
            
            // Buscar todas las encuestas que contienen la pregunta padre
            $parentSurveys = SurveyquestionsModel::where('question_id', $question->cod_padre)->get();
            
            if ($parentSurveys->isEmpty()) {
                \Log::info("Child question {$question->id} created but parent {$question->cod_padre} is not associated with any survey yet");
            }
            
            foreach ($parentSurveys as $parentSurvey) {
                // Verificar el estado de la encuesta para preguntas hijas
                $survey = \App\Models\SurveyModel::find($parentSurvey->survey_id);
                
                // Verificar si la pregunta hija ya existe en esta encuesta
                $existingChildInSurvey = SurveyquestionsModel::where('survey_id', $parentSurvey->survey_id)
                                                            ->where('question_id', $question->id)
                                                            ->exists();
                
                if (!$existingChildInSurvey) {
                    // Agregar la pregunta hija a la encuesta
                    SurveyquestionsModel::create([
                        'survey_id' => $parentSurvey->survey_id,
                        'question_id' => $question->id,
                        'section_id' => $parentSurvey->section_id, // Usar la misma sección del padre
                        'creator_id' => $user->id,
                        'user_id' => $user->id,
                        'status' => true,
                    ]);
                    
                    $surveyStatus = $survey ? ($survey->publication_status ?? 'unknown') : 'unknown';
                    \Log::info("Child question {$question->id} automatically added to survey {$parentSurvey->survey_id} (status: {$surveyStatus}, parent: {$question->cod_padre})");
                }
            }
        }
        
        // Cargar la pregunta con sus opciones, tipo y creador para la respuesta
        $questionWithOptions = QuestionModel::with(['options', 'type', 'creator:id,name'])->find($question->id);

        return response()->json($questionWithOptions, 200);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Error al crear la pregunta', 'details' => $e->getMessage()], 500);
    }
}


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        
        //motrar las categorias
        $question = QuestionModel::with(['options', 'type'])->find($id);
        if ($question) {
            return response()->json($question); // Cambiado para devolver JSON con opciones
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la pregunta'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        
           //editar
           $question = QuestionModel::with(['options', 'type'])->find($id);
           if ($question) {
               return response()->json($question); // Cambiado para devolver JSON con opciones
               //return view('surveys.edit', compact('survey'));
           } else {
               return response()->json(['message' => 'No se encontró la pregunta'], 404);
           }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        
        //Buscar la pregunta por su ID

        $question = QuestionModel::find($id);
    
        if ($question) {
            // Crear copia para auditoría
            $questionBefore = clone $question;
            
            if ($request->has('cod_padre') && count($request->all()) === 1) {
                $request->validate([
                    'cod_padre' => 'required|integer',
                ]);
                // Actualizar solo el campo 'cod_padre'
                $question->cod_padre = $request->cod_padre;
            } elseif ($request->has('section_id') && count($request->all()) === 1) {
                $request->validate([
                    'section_id' => 'nullable|integer',
                ]);
                // Actualizar solo el campo 'section_id'
                $question->section_id = $request->section_id;
            } else {
                $request->validate([
                    'title' => 'required|string|max:255',
                    'descrip' => 'nullable|string',
                    'validate' => 'required|string|max:255',
                    'cod_padre' => 'required|integer',
                    'bank' => 'required|boolean',
                    'type_questions_id' => 'required|integer',
                    'questions_conditions' => 'required|boolean',
                    'mother_answer_condition' => 'nullable|string|max:500',
                    'section_id' => 'nullable|integer',
                    'character_limit' => 'nullable|integer|min:1|max:250',
                ]);
                
                // Validar integridad del tipo antes de actualizar
                if ($request->has('type_questions_id')) {
                    try {
                        QuestionIntegrityService::validateQuestionType($request->type_questions_id);
                    } catch (\InvalidArgumentException $e) {
                        return response()->json(['error' => 'Error de validación de tipo', 'details' => $e->getMessage()], 422);
                    }
                }
                
                // Validar referencial integrity para sección
                if ($request->has('section_id') && $request->section_id) {
                    $sectionExists = DB::table('sections')->where('id', $request->section_id)->exists();
                    if (!$sectionExists) {
                        return response()->json(['error' => 'Error de integridad referencial', 'details' => 'La sección especificada no existe'], 422);
                    }
                }
                
                // Validar referencial integrity para pregunta padre
                if ($request->has('cod_padre') && $request->cod_padre && $request->cod_padre > 0) {
                    $parentExists = QuestionModel::where('id', $request->cod_padre)
                        ->where('creator_id', $request->user()->id ?? $question->creator_id)
                        ->exists();
                    if (!$parentExists) {
                        return response()->json(['error' => 'Error de integridad referencial', 'details' => 'La pregunta padre especificada no existe o no pertenece al usuario'], 422);
                    }
                }

                // VALIDACIÓN DE DUPLICADOS PARA PREGUNTAS DEL BANCO AL ACTUALIZAR
                if ($request->has('bank') && $request->bank === true) {
                    // Solo validar si el título está cambiando
                    if ($request->has('title') && $request->title !== $question->title) {
                        // Verificación 1: Duplicado en la misma sección
                        if ($request->has('section_id') && $request->section_id) {
                            $duplicateInSection = QuestionModel::where('title', $request->title)
                                                               ->where('bank', true)
                                                               ->where('section_id', $request->section_id)
                                                               ->where('id', '!=', $id) // Excluir la pregunta actual
                                                               ->first();

                            if ($duplicateInSection) {
                                \Log::info('Bank question duplicate detected in same section during update', [
                                    'updating_id' => $id,
                                    'existing_id' => $duplicateInSection->id,
                                    'title' => $request->title,
                                    'section_id' => $request->section_id
                                ]);

                                return response()->json([
                                    'error' => 'Ya existe una pregunta con este nombre en la misma sección del banco',
                                    'duplicate_in_section' => true,
                                    'existing_question_id' => $duplicateInSection->id,
                                    'section_id' => $request->section_id
                                ], 422);
                            }
                        }

                        // Verificación 2: Duplicado global en todo el banco
                        $duplicateInBank = QuestionModel::where('title', $request->title)
                                                        ->where('bank', true)
                                                        ->where('id', '!=', $id) // Excluir la pregunta actual
                                                        ->first();

                        if ($duplicateInBank) {
                            \Log::info('Bank question duplicate detected globally during update', [
                                'updating_id' => $id,
                                'existing_id' => $duplicateInBank->id,
                                'title' => $request->title,
                                'existing_section_id' => $duplicateInBank->section_id,
                                'requested_section_id' => $request->section_id ?? null
                            ]);

                            return response()->json([
                                'error' => 'Ya existe una pregunta con este nombre en el banco de preguntas',
                                'duplicate_global' => true,
                                'existing_question_id' => $duplicateInBank->id,
                                'existing_section_id' => $duplicateInBank->section_id,
                                'suggested_action' => 'Por favor, utilice un nombre diferente para esta pregunta'
                            ], 422);
                        }
                    }
                }

                // Actualizar todos los campos
                $question->title = $request->title;
                
                // Procesar imágenes en base64 durante la actualización
                $description = $request->descrip;
                if (preg_match_all('/<img src="data:image\/([^;]+);base64,([^"]+)"/', $description, $matches)) {
                    foreach ($matches[2] as $key => $base64Image) {
                        try {
                            // Obtener el tipo MIME de la imagen
                            $mimeType = $matches[1][$key];
                            
                            // Validar tipos de archivo permitidos
                            $allowedTypes = ['png', 'jpeg', 'jpg', 'svg+xml'];
                            if (!in_array(strtolower($mimeType), $allowedTypes)) {
                                \Log::warning('Tipo de imagen no permitido en actualización de pregunta: ' . $mimeType);
                                continue; // Saltar esta imagen
                            }
                            
                            // Decodificar la imagen base64
                            $imageData = base64_decode($base64Image);
                            
                            // Validar que la decodificación fue exitosa
                            if ($imageData === false) {
                                \Log::error('Error al decodificar imagen base64 en actualización de pregunta');
                                continue;
                            }
                            
                            // Determinar la extensión correcta del archivo
                            $extension = $this->getImageExtension($mimeType);
                            $imageName = uniqid() . '.' . $extension;
                            
                            // Almacenar la imagen en el sistema de archivos privado
                            $stored = Storage::disk('private')->put('images/' . $imageName, $imageData);
                            
                            if ($stored) {
                                // Establecer permisos explícitos para que el servidor web pueda leer la imagen
                                $fullPath = storage_path('app/private/images/' . $imageName);
                                chmod($fullPath, 0644);
                                
                                // Reemplazar la imagen base64 por la ruta del FileController
                                $storagePath = '/api/storage/images/' . $imageName;
                                $description = str_replace($matches[0][$key], '<img src="' . $storagePath . '"', $description);
                                \Log::info('Imagen de pregunta actualizada exitosamente: ' . $imageName);
                            } else {
                                \Log::error('Error al guardar imagen actualizada de pregunta: ' . $imageName);
                            }
                        } catch (\Exception $e) {
                            \Log::error('Error procesando imagen en actualización de pregunta: ' . $e->getMessage());
                            continue;
                        }
                    }
                }
                
                $question->descrip = $description;
                $question->validate = $request->validate;
                $question->cod_padre = $request->cod_padre;
                $question->bank = $request->bank;
                $question->type_questions_id = $request->type_questions_id;
                $question->questions_conditions = $request->questions_conditions;
                $question->mother_answer_condition = $request->mother_answer_condition;
                $question->section_id = $request->section_id;
                $question->character_limit = $request->character_limit;
            }
    
            if ($question->save()) {
                // Auditoría después de actualizar
                QuestionIntegrityService::auditQuestionIntegrityChange('UPDATE', $questionBefore, $question, $request->user()->id ?? null);

                // NUEVO: Actualizar opciones si se proporcionan (para preguntas de opción única/múltiple)
                if ($request->has('options') && is_array($request->options)) {
                    // Eliminar opciones existentes
                    QuestionsoptionsModel::where('questions_id', $question->id)->delete();

                    // Crear las nuevas opciones
                    $userId = $request->user()->id ?? $question->creator_id;
                    foreach ($request->options as $option) {
                        $optionText = is_array($option) ? ($option['option'] ?? $option['text'] ?? '') : $option;

                        if (!empty($optionText)) {
                            QuestionsoptionsModel::create([
                                'questions_id' => $question->id,
                                'options' => $optionText,
                                'creator_id' => $userId,
                                'status' => true,
                            ]);
                        }
                    }
                }

                return response()->json(['message' => 'Actualizado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al actualizar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la pregunta'], 404);
        }
    }
    
    

    /**
     * Remove the specified resource from storage.
     */
    // public function destroy(string $id)
    // {
    //     $question = QuestionModel::find($id);
    
    //     if ($question) {
    
    //         if ($question->delete()) {
    //             return response()->json(['message' => 'Eliminado con éxito'], 200);
    //         } else {
    //             return response()->json(['message' => 'Error al eliminar'], 500);
    //         }
    //     } else {
    //         return response()->json(['message' => 'No se encontró la pregunta'], 404);
    //     }
    // }

    public function destroy(string $id)
    {
        // Buscar la pregunta por ID
        $question = QuestionModel::find($id);

        if ($question) {
            // Iniciar una transacción para garantizar que todos los pasos se completen correctamente
            try {
                DB::beginTransaction();

                // Obtener el usuario autenticado
                $user = auth()->user();

                // CRÍTICO: Si la pregunta pertenece al banco (bank=true) Y NO es del usuario actual,
                // NO eliminarla completamente - solo desasociarla de las encuestas
                // PERO: Si el usuario es el creador, SÍ permitir eliminarla completamente
                if ($question->bank === true && $question->creator_id !== $user->id) {
                    \Log::info('BANK QUESTION DELETE: Preserving bank question (not owner), only removing survey associations', [
                        'question_id' => $question->id,
                        'title' => $question->title,
                        'type' => $question->type_questions_id,
                        'section_id' => $question->section_id,
                        'creator_id' => $question->creator_id,
                        'user_id' => $user->id
                    ]);

                    // Solo eliminar las asociaciones con encuestas, pero mantener la pregunta en el banco
                    $question->surveyQuestions()->delete();

                    // NO eliminar condiciones ni opciones, ya que la pregunta sigue existiendo en el banco
                    // NO eliminar la pregunta en sí

                    DB::commit();

                    return response()->json([
                        'message' => 'Pregunta desasociada de la encuesta, pero preservada en el banco',
                        'preserved_in_bank' => true,
                        'question_id' => $question->id
                    ], 200);
                }

                // Si la pregunta NO es del banco, eliminarla completamente como antes
                \Log::info('NON-BANK QUESTION DELETE: Completely removing question', [
                    'question_id' => $question->id,
                    'title' => $question->title,
                    'bank' => false
                ]);

                // Eliminar las condiciones relacionadas
                $question->conditions()->delete();

                // Eliminar las opciones relacionadas
                $question->options()->delete();

                // Eliminar los registros relacionados en la tabla `survey_questions` si es necesario
                $question->surveyQuestions()->delete();

                // Finalmente, eliminar la pregunta
                $question->delete();

                // Confirmar la transacción
                DB::commit();

                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } catch (Exception $e) {
                // En caso de error, revertir la transacción
                DB::rollBack();
                return response()->json(['message' => 'Error al eliminar: ' . $e->getMessage()], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la pregunta'], 404);
        }
    }


    // Función para obtener una pregunta con sus opciones y tipo de pregunta
    public function getQuestionDetails($id)
    {
        $question = QuestionModel::with(['options', 'type'])->find($id);

        if ($question) {
            return response()->json($question);
        } else {
            return response()->json(['message' => 'Question not found'], 404);
        }
    }
    
    /**
     * Obtener la extensión correcta basada en el tipo MIME
     */
    private function getImageExtension($mimeType)
    {
        $extensions = [
            'png' => 'png',
            'jpeg' => 'jpg',
            'jpg' => 'jpg',
            'svg+xml' => 'svg'
        ];
        
        return $extensions[strtolower($mimeType)] ?? 'png';
    }
}
