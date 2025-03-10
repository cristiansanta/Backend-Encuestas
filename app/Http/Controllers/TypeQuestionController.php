<?php

namespace App\Http\Controllers;

use App\Models\TypeQuestionModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class TypeQuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        $typequestion = TypeQuestionModel::all();

        return response()->json($typequestion); // Cambiado para devolver JSON
        //return view('surveys.index', compact('surveys'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'descrip' => 'required|string',
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
        $existingTypequestions = TypeQuestionModel::where('title', $data['title'])
                                         ->where('descrip', $data['descrip'])
                                         ->first();

        if ($existingTypequestions) {
            // Si el registro ya existe, devolver un mensaje indicando que ya fue creado
            $response = [
                'message' => 'El tipo de  pregunta ya fue creada exitosamente',
                //'category' => $existingCategory->toArray(),
            ];
            return response()->json($response, 201);
        }

        try {
            // Crear una nueva categoría en la base de datos
            $category = TypeQuestionModel::create($data);

            // Preparar la respuesta
            $response = [
                'message' => 'Tipo de pregunta creada exitosamente',
                //'category' => $category->toArray(),
            ];

            // Devolver la respuesta como JSON
            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y devolver un error 500
            return response()->json(['error' => 'Error al crear el registro', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        
         //motrar las categorias
         $typequestion = TypeQuestionModel::find($id);
         if ($typequestion) {
             return response()->json($typequestion); // Cambiado para devolver JSON
             //return view('surveys.show', compact('survey'));
         } else {
             return response()->json(['message' => 'No se encontró el tipo de pregunta'], 404);
             //return response()->json(['message' => 'No se encontró la encuesta'], 404);
         }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //editar
        $typequestion = TypeQuestionModel::find($id);
        if ($typequestion) {
            return response()->json($typequestion); // Cambiado para devolver JSON
            //return view('surveys.edit', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        
        $typequestion = TypeQuestionModel::find($id);
        if ($typequestion) {
            // Validar los datos de la solicitud
            $request->validate([
                'title' => 'required|string|max:255',
                'descrip' => 'required|string',
               
            ]);
    
            // Actualizar los campos
            $typequestion->title = $request->title;
            $typequestion->descrip = $request->descrip;            
    
            if ($typequestion->save()) {
                return response()->json(['message' => 'Actualizado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al actualizar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la la categoria'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $typequestion = TypeQuestionModel::find($id);
        if ($typequestion) {
            if ($typequestion->delete()) {
                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }
}
