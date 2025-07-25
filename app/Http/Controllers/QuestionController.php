<?php

namespace App\Http\Controllers;

use App\Models\QuestionModel;
use App\Models\QuestionsoptionsModel;
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
        
        // Filtrar por el usuario creador
        $query->where('creator_id', $user->id);
        
        // Filtrar por banco si se especifica
        if ($request->has('bank')) {
            $query->where('bank', $request->bank);
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
            $query->with(['type', 'options']);
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
    if (preg_match_all('/<img src="data:image\/[^;]+;base64,([^"]+)"/', $data['descrip'], $matches)) {
        foreach ($matches[1] as $key => $base64Image) {
            $imageData = base64_decode($base64Image);
            $imageName = uniqid() . '.png'; // Generar un nombre único para cada imagen
            $imagePath = 'private/images/' . $imageName; // Ruta en almacenamiento privado

            // Almacenar la imagen en el sistema de archivos privado
            Storage::disk('private')->put('images/' . $imageName, $imageData);

            // Reemplazar la imagen base64 en el campo descrip con la ruta de la imagen guardada
            $storagePath = '/storage/images/' . $imageName; // Ajustar la ruta de acceso
            $data['descrip'] = str_replace($matches[0][$key], '<img src="' . $storagePath . '"', $data['descrip']);
        }
    }

    // MEJORADO: Verificar si ya existe un registro similar para este usuario
    // Detección de duplicados robusta que NO considera contexto de sección
    // Una pregunta es duplicada por contenido, independientemente de su sección
    $existingQuestion = QuestionModel::where('title', $data['title'])
                                      ->where('descrip', $data['descrip'])
                                      ->where('type_questions_id', $data['type_questions_id'])
                                      ->where('creator_id', $user->id)
                                      ->first();

    if ($existingQuestion) {
        // Log para debugging
        \Log::info('Question duplicate detected', [
            'existing_id' => $existingQuestion->id,
            'existing_title' => $existingQuestion->title,
            'existing_section_id' => $existingQuestion->section_id,
            'requested_title' => $data['title'],
            'requested_section_id' => $data['section_id'],
            'user_id' => $user->id
        ]);
        
        return response()->json([
            'id' => $existingQuestion->id,
            'message' => 'La pregunta ya fue creada exitosamente (duplicado detectado por contenido)',
            'existing' => true,
            'duplicate_reason' => 'same_title_description_type',
            'note' => 'Las preguntas duplicadas se detectan por contenido, no por sección'
        ], 200);
    }

    try {
        // Separar las opciones de los datos de la pregunta
        $options = $request->input('options', []);
        unset($data['options']); // Remover opciones de los datos de la pregunta
        
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
        
        // Auditoría después de crear
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
        
        // Cargar la pregunta con sus opciones para la respuesta
        $questionWithOptions = QuestionModel::with(['options', 'type'])->find($question->id);
        
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
        $question = QuestionModel::find($id);
        if ($question) {
            return response()->json($question); // Cambiado para devolver JSON
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
           $question = QuestionModel::find($id);
           if ($question) {
               return response()->json($question); // Cambiado para devolver JSON
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
    
                // Actualizar todos los campos
                $question->title = $request->title;
                $question->descrip = $request->descrip;
                $question->validate = $request->validate;
                $question->cod_padre = $request->cod_padre;
                $question->bank = $request->bank;
                $question->type_questions_id = $request->type_questions_id;
                $question->questions_conditions = $request->questions_conditions;
                $question->mother_answer_condition = $request->mother_answer_condition;
                $question->section_id = $request->section_id;
            }
    
            if ($question->save()) {
                // Auditoría después de actualizar
                QuestionIntegrityService::auditQuestionIntegrityChange('UPDATE', $questionBefore, $question, $request->user()->id ?? null);
                
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
}
