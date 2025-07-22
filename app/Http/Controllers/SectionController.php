<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SectionModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            // Obtener el usuario autenticado
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Usuario no autenticado'], 401);
            }
            
            // Filtrar secciones por encuestas del usuario autenticado
            $sections = SectionModel::where(function($query) use ($user) {
                                        $query->whereHas('survey', function($subQuery) use ($user) {
                                            $subQuery->where('user_create', $user->name);
                                        })
                                        ->orWhereNull('id_survey'); // También incluir secciones del banco (sin encuesta específica)
                                    })
                                    ->orderBy('id', 'desc')
                                    ->get();
    
            // Devolver las secciones en formato JSON (arreglo vacío si no hay secciones)
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
            'id_survey' => 'nullable|integer',
    
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
    
    try {
        // Usar transacción para evitar problemas de concurrencia
        return DB::transaction(function () use ($data) {
            // MEJORADO: Verificar si ya existe un registro con los mismos datos clave usando lógica más robusta
            $query = SectionModel::where(function($q) use ($data) {
                                    // Manejar id_survey que puede ser null para secciones banco
                                    if (isset($data['id_survey']) && $data['id_survey'] !== null) {
                                        $q->where('id_survey', $data['id_survey']);
                                    } else {
                                        $q->whereNull('id_survey');
                                    }
                                 })
                                 ->where(function($q) use ($data) {
                                    // Verificar por título con normalización mejorada (case-insensitive y whitespace)
                                    $normalizedTitle = strtolower(preg_replace('/\s+/', ' ', trim($data['title'])));
                                    $q->whereRaw('LOWER(REGEXP_REPLACE(TRIM(title), \'[[:space:]]+\', \' \', \'g\')) = ?', [$normalizedTitle]);
                                 })
                                 ->lockForUpdate(); // Bloquear para evitar inserciones simultáneas
            
            $existingsections = $query->first();

            if ($existingsections) {
                // Si el registro ya existe, devolver el ID existente para evitar duplicados
                $response = [
                    'message' => 'La seccion ya fue creada exitosamente (duplicado detectado)',
                    'section_id' => $existingsections->id,
                    'already_exists' => true,
                    'existing_title' => $existingsections->title,
                    'requested_title' => $data['title']
                ];
                
                // Log para debugging
                \Log::info('Section duplicate detected', [
                    'existing_id' => $existingsections->id,
                    'existing_title' => $existingsections->title,
                    'requested_title' => $data['title'],
                    'survey_id' => $data['id_survey'] ?? null
                ]);
                
                return response()->json($response, 200);
            }

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
        });
    } catch (\Exception $e) {
        // Capturar cualquier excepción y devolver un error 500
        return response()->json(['error' => 'Error al crear la sección', 'details' => $e->getMessage()], 500);
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
            $requiredFields = ['title', 'descrip_sect'];
            foreach ($requiredFields as $field) {
                if (!$request->has($field)) {
                    return response()->json(['message' => 'Campo requerido faltante: ' . $field], 400);
                }
            }
    
            // Validar los datos de la solicitud
            $request->validate([
                'title' => 'required|string|max:255',
                'descrip_sect' => 'nullable|string',
                'id_survey' => 'nullable|integer',
            ]);
    
            // Verificar diferencias en las llaves foráneas
            $novedades = [];
            if ($request->has('id_survey') && $section->id_survey != $request->id_survey) {
                $novedades[] = 'Diferencia en id_survey: de ' . $section->id_survey . ' a ' . $request->id_survey;
            }
    
            // Actualizar los campos
            $section->title = $request->title;
            $section->descrip_sect = $request->descrip_sect;
            if ($request->has('id_survey')) {
                $section->id_survey = $request->id_survey;
            }
    
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
        // Obtener secciones específicas del survey
        $surveySections = SectionModel::where('id_survey', $id_survey)
                                     ->orderBy('id')
                                     ->get();

        // Obtener secciones del banco que están siendo utilizadas por preguntas de este survey
        $bankSectionsUsed = SectionModel::join('questions as q', 'sections.id', '=', 'q.section_id')
            ->join('survey_questions as sq', 'q.id', '=', 'sq.question_id')
            ->where('sq.survey_id', $id_survey)
            ->whereNull('sections.id_survey') // Solo secciones del banco
            ->select('sections.*')
            ->distinct()
            ->orderBy('sections.id')
            ->get();

        // Combinar ambos tipos de secciones
        $allSections = $surveySections->merge($bankSectionsUsed);

        // MEJORADO: Log de debug detallado
        \Log::info("getSectionsBySurvey - Survey ID: {$id_survey}");
        \Log::info("  - Survey-specific sections: {$surveySections->count()}");
        \Log::info("  - Bank sections used: {$bankSectionsUsed->count()}");
        \Log::info("  - Total sections returned: {$allSections->count()}");
        
        if ($allSections->count() > 0) {
            $sectionDetails = $allSections->map(function($section) {
                $surveyIdDisplay = $section->id_survey ?? 'NULL (banco)';
                return "ID: {$section->id}, Title: '{$section->title}', id_survey: {$surveyIdDisplay}";
            })->toArray();
            \Log::info("Section details: " . implode('; ', $sectionDetails));
        }

        // Devolver todas las secciones (específicas del survey + banco utilizadas)
        return response()->json($allSections, 200);
    } catch (\Illuminate\Database\QueryException $e) {
        // Manejar errores de consulta
        \Log::error("getSectionsBySurvey - Database error for survey {$id_survey}: " . $e->getMessage());
        return response()->json([
            'message' => 'Error al ejecutar la consulta a la base de datos.',
            'error' => $e->getMessage()
        ], 500);
    } catch (\Exception $e) {
        // Manejar errores generales
        \Log::error("getSectionsBySurvey - General error for survey {$id_survey}: " . $e->getMessage());
        return response()->json([
            'message' => 'Error al obtener las secciones',
            'error' => $e->getMessage()
        ], 500);
    }
}
}
