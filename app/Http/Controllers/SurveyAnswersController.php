<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SurveyAnswersModel;
use Illuminate\Support\Facades\Validator;

class SurveyAnswersController extends Controller
{
  
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }
        
        // Filtrar respuestas por el usuario autenticado
        $answers = SurveyAnswersModel::where('user_id', $user->id)->get();
        return response()->json($answers); // Cambiado para devolver JSON
       
    }

    public function create()
    {

    }

  
    public function store(Request $request)
{
    // Validar los datos recibidos excepto `answer`
    $validator = Validator::make($request->all(), [
        'survey_question_id' => 'required|integer',
        'answer' => 'required|array',
        'user_id' => 'required|integer',
        'status' => 'required|boolean',
    ]);

    // Si la validación falla, devolver un mensaje de error
    if ($validator->fails()) {
        return response()->json([
            'error' => 'Error de validación',
            'details' => $validator->errors()
        ], 422); // 422 Unprocessable Entity
    }

    // Obtener todos los datos validados
    $data = $request->all();

    // Convertir el campo `answer` a una cadena JSON antes de almacenar
    $data['answer'] = json_encode($data['answer']);

    // // Verificar si ya existe un registro con los mismos datos clave
    // $existinganswers = SurveyAnswersModel::where('survey_question_id', $data['survey_question_id'])
    //                                      ->where('user_id', $data['user_id'])
    //                                      ->where('status', $data['status'])
    //                                      ->first();
    // if ($existinganswers) {
    //     // Si el registro ya existe, devolver un mensaje indicando que ya fue creado
    //     return response()->json([
    //         'message' => 'La encuesta ya fue creada exitosamente',
    //     ], 201);
    // }

    try {
        // Crear un nuevo registro en la base de datos
        $answers = SurveyAnswersModel::create($data);

        // Preparar la respuesta
        $response = [
            'message' => 'Encuesta creada exitosamente',
            'survey' => $answers->toArray(),
        ];

        // Devolver la respuesta como JSON
        return response()->json($response, 200);
    } catch (\Exception $e) {
        // Capturar cualquier excepción y devolver un error 500
        return response()->json(['error' => 'Error al crear la Encuesta', 'details' => $e->getMessage()], 500);
    }
}    

    public function show(string $id)
    {
        
        $answers = SurveyAnswersModel::find($id);
        if ($answers) {
            
            return response()->json($answers); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
           
        }
    }

    
    public function edit(string $id)
    {
        //
        $answers = SurveyAnswersModel::find($id);
        if ($answers) {
            return response()->json($answers); // Cambiado para devolver JSON
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
        $answers = SurveyAnswersModel::find($id);

    if ($answers) {

        // Validar los datos de la solicitud

        $validator = Validator::make($request->all(), [
            'survey_question_id' => 'required|integer',
            'answer' => 'required|json',
            'user_id' => 'required|integer',
            'status' => 'required|boolean',

        ]);


        if ($validator->fails()) {
            $errors = $validator->errors();
            $errorMessages = [];

            foreach ($errors->all() as $error) {
                if (str_contains($error, 'survey_question_id')) {
                    $errorMessages[] = 'El campo "survey_question_id" es requerido.';
                } elseif (str_contains($error, 'answer')) {
                    $errorMessages[] = 'El campo "answer" es requerido.';
                } elseif (str_contains($error, 'creator_id')) {
                    $errorMessages[] = 'El campo "user_id" es requerido.';
                } elseif (str_contains($error, 'status')) {
                    $errorMessages[] = 'El campo "Estado" es requerido.';
                }
            }
            return response()->json(['message' => 'Error de validación', 'errors' => $errorMessages], 422);
        }


        // Actualizar los campos

        $answers->survey_question_id = $request->survey_question_id;
        $answers->answer = $request->answer;
        $answers->user_id = $request->user_id;
        $answers->status = $request->status;

        if ($answers->save()) {
            return response()->json(['message' => 'Actualizado con éxito, id: '.$id], 200);
        } else {
            return response()->json(['message' => 'Error al actualizar'], 500);
        }
    } else {
        return response()->json(['message' => 'No se encontró la encuesta con id: '.$id], 404);
    }

    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $answers = SurveyAnswersModel::find($id);
        if ($answers) {
            if ($answers->delete()) {
                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }
}
