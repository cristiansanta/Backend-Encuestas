<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyAnswersModel;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ManualSurveyResponseController extends Controller
{
    /**
     * Guarda una respuesta manual de encuesta
     */
    public function store(Request $request)
    {
        try {
            // ENHANCED DEBUG: Log incoming data for debugging
            \Log::info('📥 ManualSurveyResponseController - Datos recibidos:', [
                'all_data' => $request->all(),
                'survey_id' => $request->input('survey_id'),
                'survey_id_type' => gettype($request->input('survey_id')),
                'respondent_name' => $request->input('respondent_name'),
                'respondent_email' => $request->input('respondent_email'),
                'responses' => $request->input('responses'),
                'responses_type' => gettype($request->input('responses')),
                'responses_is_array' => is_array($request->input('responses')),
                'status' => $request->input('status')
            ]);

            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'respondent_name' => 'required|string|max:255',
                'respondent_email' => 'required|email|max:255',
                'responses' => 'required',
                'status' => 'string|max:50'
            ]);

            if ($validator->fails()) {
                \Log::error('❌ ManualSurveyResponseController - Validación fallida:', [
                    'errors' => $validator->errors(),
                    'input_data' => $request->all()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();

            // Crear registro en notificationsurvays para el seguimiento
            $notification = NotificationSurvaysModel::create([
                'data' => json_encode([
                    'survey_id' => $data['survey_id'],
                    'respondent_name' => $data['respondent_name'],
                    'type' => 'manual_response'
                ]),
                'state' => true,
                'state_results' => true,
                'date_insert' => Carbon::now(),
                'id_survey' => $data['survey_id'],
                'email' => [$data['respondent_email']], // Array con un email
                'expired_date' => Carbon::now()->addDays(30),
                'respondent_name' => $data['respondent_name'],
                'response_data' => $data['responses'] // Can be either array or object, stored as JSON
            ]);

            // Las respuestas se almacenan como JSON en response_data, no como registros individuales
            // ya que el frontend envía un objeto con las respuestas estructuradas

            \Log::info('✅ ManualSurveyResponseController - Respuesta guardada exitosamente:', [
                'notification_id' => $notification->id,
                'survey_id' => $data['survey_id'],
                'respondent_name' => $data['respondent_name']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Respuesta manual guardada exitosamente',
                'data' => [
                    'notification_id' => $notification->id,
                    'survey_id' => $data['survey_id'],
                    'respondent_name' => $data['respondent_name'],
                    'respondent_email' => $data['respondent_email']
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todas las respuestas manuales de una encuesta específica
     */
    public function getResponsesBySurvey($surveyId)
    {
        try {
            $responses = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->where('state_results', true)
                ->whereNotNull('respondent_name')
                ->get();

            $formattedResponses = $responses->map(function($response) {
                $data = is_string($response->data) ? json_decode($response->data, true) : $response->data;
                
                return [
                    'id' => $response->id,
                    'survey_id' => $response->id_survey,
                    'respondent_name' => $response->respondent_name,
                    'respondent_email' => is_array($response->email) ? $response->email[0] : $response->email,
                    'completed_at' => $response->date_insert,
                    'status' => 'Contestada',
                    'responses' => $response->response_data
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedResponses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener respuestas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene todas las respuestas manuales
     */
    public function getAllResponses()
    {
        try {
            $responses = NotificationSurvaysModel::where('state_results', true)
                ->whereNotNull('respondent_name')
                ->with(['survey']) // Asume que hay una relación con el modelo Survey
                ->get();

            $formattedResponses = $responses->map(function($response) {
                $data = is_string($response->data) ? json_decode($response->data, true) : $response->data;
                
                return [
                    'id' => $response->id,
                    'survey_id' => $response->id_survey,
                    'survey_title' => $response->survey->title ?? 'Sin título',
                    'respondent_name' => $response->respondent_name,
                    'respondent_email' => is_array($response->email) ? $response->email[0] : $response->email,
                    'completed_at' => $response->date_insert,
                    'status' => 'Contestada',
                    'responses' => $response->response_data
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $formattedResponses
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener respuestas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}