<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\AssignmentTypeModel;

class AssignmentTypeController extends Controller
{
    public function index()
    {
        $assignment = AssignmentTypeModel::all();
        return response()->json($assignment); // Cambiado para devolver JSON
    }


    public function create()
    {
        
    }

    public function store(Request $request)
    {
        // Validar los datos recibidos
        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|integer',
            'value' => 'required|json',
            'type_name' => 'required|string',
            'notification' => 'required|json',
            
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
        $existingassignment = AssignmentTypeModel::where('assignment_id', $data['assignment_id'])
                                         ->where('value', $data['start_date'])
                                         ->where('type_name', $data['type_name'])
                                         ->where('notification', $data['notification'])
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
            $assignment = AssignmentTypeModel::create($data);

            // Preparar la respuesta
            $response = [
                'message' => 'creado exitosamente',
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
        $assignment = AssignmentTypeModel::find($id);
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
        $assignment = AssignmentTypeModel::find($id);
        if ($assignment) {
            return response()->json($assignment); // Cambiado para devolver JSON
            //return view('surveys.edit', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }

    public function update(Request $request, string $id)
    {
    $assignment = AssignmentTypeModel::find($id);
    if ($assignment) {

        // Validar los datos de la solicitud

        $validator = Validator::make($request->all(), [
            'assignment_id' => 'required|integer',
            'value' => 'required|json',
            'type_name' => 'required|string',
            'notification' => 'required|json',

        ]);

        if ($validator->fails()) {
            $errors = $validator->errors();
            $errorMessages = [];

            foreach ($errors->all() as $error) {
                if (str_contains($error, 'assignment_id')) {
                    $errorMessages[] = 'El campo "assignment_id" es requerido.';
                } elseif (str_contains($error, 'value')) {
                    $errorMessages[] = 'El campo "value" es requerido.';
                } elseif (str_contains($error, 'type_name')) {
                    $errorMessages[] = 'El campo "type_name" es requerido.';
                } elseif (str_contains($error, 'notification')) {
                    $errorMessages[] = 'El campo "notification" es requerido.';
                }
            }
            return response()->json(['message' => 'Error de validación', 'errors' => $errorMessages], 422);
        }

        // Actualizar los campos
        $assignment->assignment_id = $request->assignment_id;
        $assignment->value = $request->value;
        $assignment->type_name = $request->type_name;
        $assignment->notification = $request->notification;
       


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
        $assignment = AssignmentTypeModel::find($id);
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
