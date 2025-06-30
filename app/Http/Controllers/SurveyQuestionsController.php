<?php

namespace App\Http\Controllers;

use App\Models\SurveyquestionsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SurveyQuestionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Verificar si el parámetro 'section_id' está presente en la solicitud
        $sectionId = $request->query('section_id');
    
        // Si 'section_id' está presente, filtrar por este campo
        if ($sectionId) {
            $surveyQuestions = SurveyquestionsModel::where('section_id', $sectionId)->get();
        } else {
            // Si no se proporciona 'section_id', devolver todos los registros
            $surveyQuestions = SurveyquestionsModel::all();
        }
    
        // Retornar los resultados como JSON
        return response()->json($surveyQuestions);
    }
    

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //crear survey questions
        return view('survey_questions.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Validar los datos recibidos - MEJORADO para aceptar tanto boolean como integer para status
        $validator = Validator::make($request->all(), [
            'survey_id' => 'required|integer',
            'question_id' => 'required|integer',
            'section_id' => 'required|integer',
            'creator_id' => 'required|integer',
            'status' => 'required|boolean', // Acepta 0,1,true,false
            'user_id' => 'required|integer',
        ]);

        // Log datos recibidos para debugging
        \Log::info('SurveyQuestionsController - Datos recibidos:', $request->all());

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
        $existingsq = SurveyquestionsModel::where('survey_id', $data['survey_id'])
                                         ->where('question_id', $data['question_id'])
                                         ->where('section_id', $data['section_id'])
                                         ->first();
        if ($existingsq) {
            // Si el registro ya existe, devolver un mensaje indicando que ya fue creado
            $response = [
                'message' => 'el registro ya fue creada exitosamente (duplicado detectado)',
                'id' => $existingsq->id,
                'survey_id' => $existingsq->survey_id,
                'question_id' => $existingsq->question_id,
                'section_id' => $existingsq->section_id,
                'already_exists' => true
            ];
            return response()->json($response, 200);
        }

        try {
            // Crear una nueva surveyquestions en la base de datos
            $surveyQuestion = SurveyquestionsModel::create($data);
            
            // Preparar la respuesta INCLUYENDO EL ID
            $response = [
                'message' => 'Creado exitosamente',
                'id' => $surveyQuestion->id,
                'survey_id' => $surveyQuestion->survey_id,
                'question_id' => $surveyQuestion->question_id,
                'section_id' => $surveyQuestion->section_id
            ];

            // Devolver la respuesta como JSON
            return response()->json($response, 200);
        } catch (\Exception $e) {
            // Capturar cualquier excepción y devolver un error 500
            return response()->json(['error' => 'Error al crear EL REGISTRO', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //motrar las surveyquestions
        $surveyquestions = SurveyquestionsModel::find($id);
        if ($surveyquestions) {
            return response()->json($surveyquestions); // Cambiado para devolver JSON
            //return view('surveys.show', compact('survey'));
        } else {
            return response()->json(['message' => 'No se encontró el registro'], 404);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
         //editar
         $surveyquestions = SurveyquestionsModel::find($id);
         if ($surveyquestions) {
             return response()->json($surveyquestions); // Cambiado para devolver JSON
             
         } else {
             return response()->json(['message' => 'No se encontró el registro'], 404);
         }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $surveyquestions = SurveyquestionsModel::find($id);
        if ($surveyquestions) {
            // Validar los datos de la solicitud
            $request->validate([
            'survey_id' => 'required|integer',
            'question_id' => 'required|integer',
            'section_id' => 'required|integer',
            'creator_id' => 'required|integer',
            'status' => 'required|boolean',
            'user_id' => 'required|integer',
                
            ]);
    
            // Actualizar los campos
            $surveyquestions->survey_id = $request->survey_id;
            $surveyquestions->question_id = $request->question_id;
            $surveyquestions->section_id = $request->section_id; 
            $surveyquestions->creator_id = $request->creator_id;
            $surveyquestions->status = $request->status;
            $surveyquestions->user_id = $request->user_id;   

    
            if ($surveyquestions->save()) {
                return response()->json(['message' => 'Actualizado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al actualizar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la la categoria'], 404);
        }
    }

    /**
     * Elimina los registros.
     */
    public function destroy(string $id)
    {    
        $surveyquestions = SurveyquestionsModel::find($id);
            if ($surveyquestions) {    
            if ($surveyquestions->delete()) {    
                return response()->json(['message' => 'Eliminado con éxito'], 200);
            } else {
                return response()->json(['message' => 'Error al eliminar'], 500);
            }
        } else {
            return response()->json(['message' => 'No se encontró la pregunta de la encuesta'], 404);
        }
    }
}
