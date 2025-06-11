<?php

namespace App\Http\Controllers;
use App\Models\QuestionsoptionsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
                QuestionsoptionsModel::create([
                    'questions_id' => $questions_id,
                    'options' => $option['option'] ?? $option, // Verificar si tiene un campo 'option' o es un simple string
                    'creator_id' => $request->input('creator_id'),
                    'status' => $request->input('status'),
                ]);
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
        //
        $questionoptions = QuestionsoptionsModel::find($id);
        if ($questionoptions) {
            // Validar los datos de la solicitud
            $request->validate([
              'questions_id' => 'required|integer',
              'options' => 'required|string',
              'creator_id'=> 'required|integer',
              'status'=> 'required|boolean',

              
            ]);
    
            // Actualizar los campos
            $questionoptions->questions_id = $request->questions_id;
            $questionoptions->options = $request->options;  
            $questionoptions->creator_id = $request->creator_id;
            $questionoptions->status = $request->status;      
    
            if ($questionoptions->save()) {
                return response()->json(['message' => 'Actualizado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al actualizar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $questionoptions = QuestionsoptionsModel::find($id);
    
        if ($questionoptions) {
            // Verificar si la categoría está siendo utilizada como llave foránea en otra tabla
            if ($questionoptions->questionoptions()->count() > 0) {
                return response()->json(['message' => 'No se puede eliminar la categoría porque está siendo utilizada en otras encuestas'], 409);
            }
    
            if ($questionoptions->delete()) {
                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la registro'], 404);
        }
    }
}
