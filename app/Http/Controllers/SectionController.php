<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SectionModel;
use Illuminate\Support\Facades\Validator;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            // Ordenar las secciones por 'id' en orden descendente y luego por 'title' en orden ascendente
            $sections = SectionModel::orderBy('id', 'desc')->get();
    
            // Verificar si se encontraron secciones
            if ($sections->isEmpty()) {
                return response()->json(['message' => 'No se encontraron secciones'], 404);
            }
    
            // Devolver las secciones en formato JSON
            return response()->json($sections, 200);
        } catch (\Illuminate\Database\QueryException $e) {
            // Manejo de errores específicos de la base de datos
            return response()->json([
                'message' => 'Error al ejecutar la consulta a la base de datos.',
                'error' => $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            // Manejo de errores generales
            return response()->json([
                'message' => 'Error al obtener las secciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
        return view('section.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'descrip_sect' => 'nullable|string',
            'id_survey' => 'required|integer',
    
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
    $existingsections = SectionModel::where('title', $data['title'])
                                    ->where('descrip_sect', $data['descrip_sect'])
                                    ->where('id_survey', $data['id_survey'])
                                    ->first();

    if ($existingsections) {
        // Si el registro ya existe, devolver un mensaje indicando que ya fue creado
        $response = [
            'message' => 'La seccion ya fue creada exitosamente',
            //'question' => $existingQuestion->toArray(),
        ];
        return response()->json($response, 201);
    }

    try {
        // Crear una nueva sections en la base de datos
        $section = SectionModel::create($data);

        // Preparar la respuesta
        $response = [
            'message' => 'Seccion fue creada exitosamente',
            'section_id' => $section->id, 
            //'question' => $question->toArray(),
        ];

        // Devolver la respuesta como JSON
        return response()->json($response, 200);
    } catch (\Exception $e) {
        // Capturar cualquier excepción y devolver un error 500
        return response()->json(['error' => 'Error al crear la pregunta', 'details' => $e->getMessage()], 500);
    }    
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $section = SectionModel::find($id);
        if ($section) {
            return response()->json($section); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la seccion'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $section = SectionModel::find($id);
        if ($section) {
            return response()->json($section); // Cambiado para devolver JSON
            //return view('surveys.edit', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la seccion'], 404);
        }
        
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $section = SectionModel::find($id);
        if ($section) {
            // Validar que los nombres o títulos en el JSON están siendo enviados correctamente
            $requiredFields = ['title', 'descrip_sect', 'id_survey'];
            foreach ($requiredFields as $field) {
                if (!$request->has($field)) {
                    return response()->json(['message' => 'Campo requerido faltante: ' . $field], 400);
                }
            }
    
            // Validar los datos de la solicitud
            $request->validate([
                'title' => 'required|string|max:255',
                'descrip_sect' => 'nullable|string',
                'id_survey' => 'required|integer',
            ]);
    
            // Verificar diferencias en las llaves foráneas
            $novedades = [];
            if ($section->id_survey != $request->id_survey) {
                $novedades[] = 'Diferencia en id_survey: de ' . $section->id_survey . ' a ' . $request->id_survey;
            }
    
            // Actualizar los campos
            $section->title = $request->title;
            $section->descrip_sect = $request->descrip_sect;
            $section->id_survey = $request->id_survey;
    
            if ($section->save()) {
                $message = 'Actualizado con éxito, id: ' . $id;
                if (!empty($novedades)) {
                    $message .= '. Novedades: ' . implode(', ', $novedades);
                }
                return response()->json(['message' => $message], 200);
            } else {
                return response()->json(['message' => 'Error al actualizar, id: ' . $id], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la sección, id:' . $id], 404);
        }
    }
    

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
        $section = SectionModel::find($id);
        if ($section) {
            if ($section->delete()) {
                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    public function getSectionsBySurvey($id_survey)
{
    try {
        // Obtener todas las secciones relacionadas con el id_survey
        $sections = SectionModel::where('id_survey', $id_survey)->get();

        // Verificar si se encontraron secciones
        if ($sections->isEmpty()) {
            return response()->json(['message' => 'No se encontraron secciones para esta encuesta'], 404);
        }

        // Devolver las secciones filtradas en formato JSON
        return response()->json($sections, 200);
    } catch (\Illuminate\Database\QueryException $e) {
        // Manejar errores de consulta
        return response()->json([
            'message' => 'Error al ejecutar la consulta a la base de datos.',
            'error' => $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        // Manejar errores generales
        return response()->json([
            'message' => 'Error al obtener las secciones',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
