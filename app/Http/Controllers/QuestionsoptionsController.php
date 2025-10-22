<?php

namespace App\Http\Controllers;
use App\Models\QuestionsoptionsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class QuestionsoptionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
         //
         $questionoptions   = QuestionsoptionsModel::all();
         return response()->json($questionoptions); // Cambiado para devolver JSON
         //return view('categorys.index', compact('categorys'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'questions_id' => 'required|integer',
            'options' => 'required', // Aceptamos tanto un array como un string
            'creator_id' => 'required|integer',
            'status' => 'required|boolean',
        ]);
    
        // Si la validación falla, devolver un mensaje de error
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $validator->errors()
            ], 422); // 422 Unprocessable Entity
        }
    
        $questions_id = $request->input('questions_id');
        $options = $request->input('options'); // Recibimos un array de opciones o un string
    
        // Si "options" es un array (para opción múltiple o verdadero/falso con las dos opciones)
        if (is_array($options)) {
            foreach ($options as $option) {
                // Manejar diferentes formatos de opciones
                $optionText = '';
                if (is_string($option)) {
                    // Opción simple como string
                    $optionText = $option;
                } elseif (is_array($option)) {
                    // Opción como objeto/array con propiedades
                    $optionText = $option['option'] ?? $option['text'] ?? $option['value'] ?? $option['label'] ?? '';
                } else {
                    // Convertir a string como último recurso
                    $optionText = (string) $option;
                }
                
                if (!empty($optionText)) {
                    QuestionsoptionsModel::create([
                        'questions_id' => $questions_id,
                        'options' => $optionText,
                        'creator_id' => $request->input('creator_id'),
                        'status' => $request->input('status'),
                    ]);
                }
            }
        } else {
            // Para respuesta abierta o único string
            QuestionsoptionsModel::create([
                'questions_id' => $questions_id,
                'options' => $options, // Para respuesta abierta u otra opción única
                'creator_id' => $request->input('creator_id'),
                'status' => $request->input('status'),
            ]);
        }
    
        return response()->json(['message' => 'Opciones creadas exitosamente'], 200);
    }
    
    



    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //motrar las categorias
        $questionoptions = QuestionsoptionsModel::find($id);
        if ($questionoptions) {
            return response()->json($questionoptions); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
         //editar
         $questionoptions = QuestionsoptionsModel::find($id);
         if ($questionoptions) {
             return response()->json($questionoptions); // Cambiado para devolver JSON
             //return view('surveys.edit', compact('survey'));
         } else {
             return response()->json(['message' => 'No se encontró la respuesta'], 404);
         }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $questionoptions = QuestionsoptionsModel::find($id);
        if ($questionoptions) {
            // Validar solo el campo options (los demás son opcionales)
            $request->validate([
              'options' => 'required|string|max:255',
            ]);

            // Actualizar solo el campo options (texto de la opción)
            $questionoptions->options = $request->options;

            // Actualizar otros campos solo si se proporcionan
            if ($request->has('questions_id')) {
                $questionoptions->questions_id = $request->questions_id;
            }
            if ($request->has('creator_id')) {
                $questionoptions->creator_id = $request->creator_id;
            }
            if ($request->has('status')) {
                $questionoptions->status = $request->status;
            }

            if ($questionoptions->save()) {
                return response()->json([
                    'message' => 'Opción actualizada con éxito',
                    'option' => $questionoptions
                ], 200);
            } else {
                return response()->json(['message' => 'Error al actualizar la opción'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la opción'], 404);
        }
    }

    /**
     * Update a single option text.
     */
    public function updateOptionText(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $validator->errors()
            ], 422);
        }

        $option = QuestionsoptionsModel::find($id);
        if (!$option) {
            return response()->json(['message' => 'Opción no encontrada'], 404);
        }

        $option->options = $request->input('text');
        
        if ($option->save()) {
            return response()->json([
                'message' => 'Opción actualizada exitosamente',
                'option' => $option
            ], 200);
        } else {
            return response()->json(['message' => 'Error al actualizar la opción'], 500);
        }
    }

    /**
     * Update all options for a specific question.
     */
    public function updateByQuestion(Request $request, string $question_id)
    {
        $validator = Validator::make($request->all(), [
            'options' => 'required|array',
            'creator_id' => 'required|integer',
            'status' => 'required|boolean',
        ]);
    
        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $validator->errors()
            ], 422);
        }

        try {
            // Use database transaction to prevent concurrent duplicates
            DB::transaction(function () use ($question_id, $request) {
                // Log before deletion for debugging
                $existingOptions = QuestionsoptionsModel::where('questions_id', $question_id)->get();
                \Log::info("Deleting existing options for question {$question_id}:", $existingOptions->pluck('options')->toArray());
                
                // Eliminar todas las opciones existentes para esta pregunta
                QuestionsoptionsModel::where('questions_id', $question_id)->delete();

                // Crear las nuevas opciones
                $options = $request->input('options');
                \Log::info("Creating new options for question {$question_id}:", $options);
                
                foreach ($options as $option) {
                    // Manejar diferentes formatos de opciones
                    $optionText = '';
                    if (is_string($option)) {
                        $optionText = $option;
                    } elseif (is_array($option)) {
                        $optionText = $option['option'] ?? $option['text'] ?? $option['value'] ?? $option['label'] ?? '';
                    } else {
                        $optionText = (string) $option;
                    }
                    
                    if (!empty($optionText)) {
                        $newOption = QuestionsoptionsModel::create([
                            'questions_id' => $question_id,
                            'options' => $optionText,
                            'creator_id' => $request->input('creator_id'),
                            'status' => $request->input('status'),
                        ]);
                        \Log::info("Created option: {$optionText} with ID: {$newOption->id} for question: {$question_id}");
                    }
                }
            });

            return response()->json(['message' => 'Opciones actualizadas exitosamente'], 200);
        } catch (\Exception $e) {
            \Log::error("Error updating options for question {$question_id}: " . $e->getMessage());
            return response()->json(['message' => 'Error al actualizar opciones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get options for a specific question.
     */
    public function getOptionsByQuestion(string $question_id)
    {
        try {
            $options = QuestionsoptionsModel::where('questions_id', $question_id)
                ->where('status', true)
                ->orderBy('id')
                ->get();

            \Log::info("Retrieved options for question {$question_id}:", $options->pluck('options')->toArray());

            return response()->json([
                'options' => $options,
                'count' => $options->count()
            ], 200);
        } catch (\Exception $e) {
            \Log::error("Error getting options for question {$question_id}: " . $e->getMessage());
            return response()->json(['message' => 'Error al obtener opciones: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $questionoptions = QuestionsoptionsModel::find($id);

        if ($questionoptions) {
            // Eliminar la opción directamente sin verificaciones adicionales
            // Las opciones pueden eliminarse libremente siempre que la pregunta lo permita
            if ($questionoptions->delete()) {
                return response()->json(['message' => 'Opción eliminada con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar la opción'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la opción'], 404);
        }
    }
}
