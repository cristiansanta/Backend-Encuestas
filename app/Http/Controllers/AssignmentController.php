<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AssignmentModel;
class AssignmentController extends Controller
{
 
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $assignment = AssignmentModel::all();
        return response()->json($assignment); // Cambiado para devolver JSON
    }


    public function create()
    {
        
    }

    public function store(Request $request)
    {
        // Validar los datos recibidos
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'number_of_attempts' => 'required|integer',
            'is_anonymous' => 'required|boolean',
            'days_enabled' => 'required|integer',
            'days_activation' => 'required|integer',
            'days_notification' => 'required|integer',
            'user_id' => 'required|integer',
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

        // Verificar si ya existe un registro con los mismos datos clave
        $existingassignment = AssignmentModel::where('title', $data['title'])
                                         ->where('start_date', $data['start_date'])
                                         ->where('number_of_attempts', $data['number_of_attempts'])
                                         ->where('is_anonymous', $data['is_anonymous'])
                                         ->first();

        if ($existingassignment) {
            // Si el registro ya existe, devolver un mensaje indicando que ya fue creado
            $response = [
                'message' => 'La encuesta ya fue creada exitosamente',
                //'category' => $existingCategory->toArray(),
            ];
            return response()->json($response, 201);
        }

        try {
            // Crear una nueva categoría en la base de datos
            $assignment = AssignmentModel::create($data);

            // Preparar la respuesta
            $response = [
                'message' => 'Encuesta creada exitosamente',
                'survey' => $assignment->toArray(),
            ];

            // Devolver la respuesta como JSON
            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y devolver un error 500
            return response()->json(['error' => 'Error al crear el registro', 'details' => $e->getMessage()], 500);
        }
    }
  

    public function show(string $id)
    {
        //
        $assignment = AssignmentModel::find($id);
        if ($assignment) {
            
            return response()->json($assignment); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró el registro que desea visualizar'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    public function edit(string $id)
    {
        //
        $assignment = AssignmentModel::find($id);
        if ($assignment) {
            return response()->json($assignment); // Cambiado para devolver JSON
            //return view('surveys.edit', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }

    public function update(Request $request, string $id)
    {
    $assignment = AssignmentModel::find($id);
    if ($assignment) {

        // Validar los datos de la solicitud

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'number_of_attempts' => 'required|integer',
            'is_anonymous' => 'required|boolean',
            'days_enabled' => 'required|integer',
            'days_activation' => 'required|integer',
            'days_notification' => 'required|integer',
            'user_id' => 'required|integer',

        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $errorMessages = [];

            foreach ($errors->all() as $error) {
                if (str_contains($error, 'title')) {
                    $errorMessages[] = 'El campo "Título" es requerido.';
                } elseif (str_contains($error, 'start_date')) {
                    $errorMessages[] = 'El campo "fecha inicio" es requerido.';
                } elseif (str_contains($error, 'end_date')) {
                    $errorMessages[] = 'El campo "Fecha final" es requerido.';
                } elseif (str_contains($error, 'number_of_attempts')) {
                    $errorMessages[] = 'El campo "numero de intentos" es requerido.';
                }
            }
            return response()->json(['message' => 'Error de validación', 'errors' => $errorMessages], 422);
        }

        // Actualizar los campos
        $assignment->title = $request->title;
        $assignment->start_date = $request->start_date;
        $assignment->end_date = $request->end_date;
        $assignment->number_of_attempts = $request->number_of_attempts;
        $assignment->is_anonymous = $request->is_anonymous;
        $assignment->enable_notification = $request->enable_notification;
        $assignment->days_enabled = $request->days_enabled;
        $assignment->days_activation = $request->days_activation;
        $assignment->days_notification = $request->days_notification;


        if ($assignment->save()) {
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
        //
        $assignment = AssignmentModel::find($id);
        if ($assignment) {
            if ($assignment->delete()) {
                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    
}
