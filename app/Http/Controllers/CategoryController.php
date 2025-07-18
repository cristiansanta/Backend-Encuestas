<?php

namespace App\Http\Controllers;

use App\Models\CategoryModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CategoryController extends Controller
{
    
    public function index(Request $request)
    {
        // Obtener el usuario autenticado
        $user = $request->user();
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }
        
        // Listado de categorías del usuario ordenadas de mayor a menor por el campo 'id'
        $category = CategoryModel::where('user_create', $user->name)
                                 ->orderBy('id', 'desc')
                                 ->get();
        return response()->json($category);
    }

    
    public function create()
    {
        //crear category
        return view('categorys.create');
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
            'descrip_cat' => 'nullable|string',
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
        $data['user_create'] = $user->name; // Agregar el usuario creador

        // Verificar si ya existe una categoría con el mismo título para este usuario
        $existingCategory = CategoryModel::where('title', $data['title'])
                                         ->where('user_create', $user->name)
                                         ->first();

        if ($existingCategory) {
            // Si el registro ya existe, devolver un mensaje indicando que ya fue creado
            $response = [
                'message' => 'Ya existe una categoría con este nombre',
                'error' => 'duplicate_category',
                'category_id' => $existingCategory->id
            ];
            return response()->json($response, 409); // 409 Conflict for duplicate
        }

        try {
            // Crear una nueva categoría en la base de datos
            $category = CategoryModel::create($data);

            // Preparar la respuesta
            $response = [
                'message' => 'Categoría creada exitosamente',
                'category_id' => $category->id,
                'category' => $category->toArray(),
            ];

            // Devolver la respuesta como JSON
            return response()->json($response, 201);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y devolver un error 500
            return response()->json(['error' => 'Error al crear la categoría', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //motrar las categorias
        $category = CategoryModel::find($id);
        if ($category) {
            return response()->json($category); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la categoria'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //editar
        $category = CategoryModel::find($id);
        if ($category) {
            return response()->json($category); // Cambiado para devolver JSON
            //return view('surveys.edit', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la categoria'], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
        $category = CategoryModel::find($id);
        if ($category) {
            // Validar los datos de la solicitud
            $request->validate([
                'title' => 'required|string|max:255',
                'descrip_cat' => 'nullable|string',
                
            ]);
    
            // Actualizar los campos
            $category->title = $request->title;
            $category->descrip_cat = $request->descrip_cat;            
    
            if ($category->save()) {
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
  
    
        // Método para eliminar una categoría
        public function destroy($id)
        {
            // Buscar la categoría por ID
            $category = CategoryModel::find($id);
            
            // Verificar si la categoría existe
            if ($category) {
                // Verificar si hay encuestas asociadas a esta categoría
                $surveysCount = $category->surveys()->count();
                
                if ($surveysCount > 0) {
                    return response()->json([
                        'message' => 'No se puede eliminar la categoría porque tiene ' . $surveysCount . ' encuesta(s) asociada(s).',
                        'error' => 'foreign_key_constraint',
                        'surveys_count' => $surveysCount
                    ], 409); // 409 Conflict
                }
                
                // Si no hay encuestas asociadas, eliminar la categoría
                $category->delete();
                return response()->json(['message' => 'Registro eliminado con éxito.'], 200);
            } else {
                // Si no se encuentra, devolver un error 404
                return response()->json(['message' => 'Categoría no encontrada.'], 404);
            }
        }
    
    

    public function showSurveys($id)
    {
       
        
        $category = CategoryModel::find($id);

        if ($category) {
            return response()->json($category->surveys);
        } else {
            return response()->json(['message' => 'Category not found'], 404);
        }
    }
}
