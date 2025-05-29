<?php

namespace App\Http\Controllers;


use Illuminate\Http\Request;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Validator;
use Closure;
use Symfony\Component\HttpFoundation\Response;
use HTMLPurifier;
use HTMLPurifier_Config;
use function PHPSTORM_META\type;
use Illuminate\Support\Facades\Storage;


class SurveyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
                $surveys = SurveyModel::all();
        return response()->json($surveys); // Cambiado para devolver JSON
        //return view('surveys.index', compact('surveys'));
    }


    public function create()
    {
        //
        return view('surveys.create');
    }

    /**
     * Store a newly created resource in storage.
     */

    public function pon(Request $request, Closure $next )
    {
        $apiKey = $request->header('X-API-KEY');
        if ($apiKey !== config('app.api_key')) {
            return response()->json(['message' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }
        return $next($request);       

    }

  

    public function store(Request $request)
    {
        // Validar los datos entrantes
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'descrip' => 'nullable|string',
            'id_category' => 'required|integer',
            'status' => 'required|boolean',
            'user_create' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Error de validación',
                'details' => $validator->errors(),
            ], 422);
        }

        $data = $request->all();

        // Buscar y decodificar imágenes en base64 dentro de la descripción
        if (preg_match_all('/<img src="data:image\/[^;]+;base64,([^"]+)"/', $data['descrip'], $matches)) {
            foreach ($matches[1] as $key => $base64Image) {
                // Decodificar la imagen base64
                $imageData = base64_decode($base64Image);
                $imageName = uniqid() . '.png'; // Puedes cambiar la extensión según el tipo de imagen
                $imagePath = 'private/images/' . $imageName; // Ruta en almacenamiento privado

                // Almacenar la imagen en el sistema de archivos privado
                Storage::disk('private')->put('images/' . $imageName, $imageData);

                // Reemplazar la imagen base64 por la ruta de almacenamiento privado
                $storagePath = '/storage/images/' . $imageName; // Ajustamos la ruta de acceso
                $data['descrip'] = str_replace($matches[0][$key], '<img src="' . $storagePath . '"', $data['descrip']);
            }
        }

        try {
            // Crear la encuesta en la base de datos
            $survey = SurveyModel::create($data);

            return response()->json([
                'message' => 'Encuesta creada exitosamente',
                'survey' => $survey,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al crear la encuesta',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    


    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $survey = SurveyModel::find($id);
        if ($survey) {
            
            return response()->json($survey); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró la encuesta'], 404);
            //return response()->json(['message' => 'No se encontró la encuesta'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
        $survey = SurveyModel::find($id);
        if ($survey) {
            return response()->json($survey); // Cambiado para devolver JSON
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
        $survey = SurveyModel::find($id);
        if (!$survey) {
            return response()->json(['message' => 'No se encontró la encuesta con id: ' . $id], 404);
        }
    
        // Validar los datos de la solicitud
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'descrip' => 'nullable|string',
            'id_category' => 'nullable|integer',
            'status' => 'nullable|boolean',
            'user_create' => 'required|string',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['message' => 'Error de validación', 'errors' => $validator->errors()], 422);
        }
    
        // Actualizar solo los campos proporcionados en la solicitud
        if ($request->has('title')) {
            $survey->title = $request->title;
        }
    
        if ($request->has('descrip')) {
            $survey->descrip = $request->descrip;
        }
    
        if ($request->has('id_category')) {
            $survey->id_category = $request->id_category;
        }
    
        if ($request->has('status')) {
            $survey->status = $request->status;
        }
    
        // Guardar los cambios
        if ($survey->save()) {
            return response()->json(['message' => 'Encuesta actualizada con éxito', 'data' => $survey], 200);
        } else {
            return response()->json(['message' => 'Error al actualizar la encuesta'], 500);
        }
    }
    

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
{
    $survey = SurveyModel::find($id);

    if (!$survey) {
        return response()->json(['message' => 'No se encontró la encuesta'], 404);
    }

    try {
        $survey->delete();
        return response()->json(['message' => 'Encuesta eliminada con éxito'], 200);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al eliminar la encuesta', 'error' => $e->getMessage()], 500);
    }
}


    public function showSections($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            return response()->json($survey->sections);
        } else {
            return response()->json(['message' => 'sections not found'], 404);
        }
    }

    // Función para obtener preguntas de una encuesta específica de options
    public function getSurveyQuestions($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            $surveyQuestions = $survey->surveyQuestions;
            return response()->json($surveyQuestions);
        } else {
            return response()->json(['message' => 'Survey not found'], 404);
        }
    }

    // Función para obtener preguntas de una encuesta específica detallada
    public function getSurveyQuestionsop($id)
    {
        $survey = SurveyModel::find($id);
        if ($survey) {
            $surveyQuestions = $survey->surveyQuestions()->with('question')->get();
            return response()->json($surveyQuestions);
        } else {
            return response()->json(['message' => 'Survey not found'], 404);
        }
    }

    // Función para obtener una encuesta con sus secciones
    public function getSurveySections($id)
    {
        $survey = SurveyModel::with('sections')->find($id);

        if ($survey) {
            return response()->json($survey);
        } else {
            return response()->json(['message' => 'Survey not found'], 404);
        }
    }

     // Función para obtener una encuesta completa con sus relaciones
     public function getSurveyDetails($id)
{
    $survey = SurveyModel::with([
        'category',
        'sections',
        'surveyQuestions.question.type',
        'surveyQuestions.question.options',
        'surveyQuestions.question.conditions' // Renombra el campo
    ])->find($id);

    if ($survey) {
        return response()->json($survey);
    } else {
        return response()->json(['message' => 'Survey not found'], 404);
    }
}
// Función para obtener todas las encuestas completas con sus relaciones
public function getAllSurveyDetails()
{
    $surveys = SurveyModel::with([
        'category',
        'sections',
        'surveyQuestions.question.type',
        'surveyQuestions.question.options',
        'surveyQuestions.question.conditions' // Renombra el campo si es necesario
    ])->orderBy('id', 'desc')->get(); // Ordenar por 'id' de mayor a menor

    if ($surveys->isNotEmpty()) {
        return response()->json($surveys);
    } else {
        return response()->json(['message' => 'No surveys found'], 404);
    }
}


     
     

     public function testStorage()
     {
         try {
             $path = 'images/testfile.txt';
             Storage::disk('private')->put($path, 'Contenido de prueba');
             return 'Archivo almacenado correctamente.';
         } catch (\Exception $e) {
             return 'Error: ' . $e->getMessage();
         }
     }

    /**
     * Obtener lista de encuestas para envío masivo
     */
    public function list()
    {
        try {
            $surveys = SurveyModel::select('id', 'title', 'descrip', 'status', 'created_at')
                ->where('status', 1) // Solo encuestas activas
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($survey) {
                    return [
                        'id' => $survey->id,
                        'title' => $survey->title,
                        'description' => $survey->descrip,
                        'status' => $survey->status,
                        'created_at' => $survey->created_at
                    ];
                });

            return response()->json($surveys, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener las encuestas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
