<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyAnswersModel;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
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
                'state' => '1',
                'state_results' => 'true', // Cambiar de boolean a string
                'date_insert' => Carbon::now(),
                'id_survey' => $data['survey_id'],
                'destinatario' => $data['respondent_email'], // Usar nuevo campo destinatario
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
                ->where('state_results', 'true') // Cambiar a string
                ->whereNotNull('respondent_name')
                ->get();

            $formattedResponses = $responses->map(function($response) {
                $data = is_string($response->data) ? json_decode($response->data, true) : $response->data;
                
                return [
                    'id' => $response->id,
                    'survey_id' => $response->id_survey,
                    'respondent_name' => $response->respondent_name,
                    'respondent_email' => $response->destinatario, // Usar nuevo campo destinatario
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
            $responses = NotificationSurvaysModel::where('state_results', 'true') // Cambiar a string
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
                    'respondent_email' => $response->destinatario, // Usar nuevo campo destinatario
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
     * Valida el acceso y obtiene datos de la encuesta para respuesta por email
     */
    public function validateEmailSurveyAccess(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'token' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $surveyId = $request->input('survey_id');
            $token = $request->input('token');

            // Verificar que la encuesta existe
            $survey = SurveyModel::with(['sections', 'surveyQuestions'])->find($surveyId);
            if (!$survey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Encuesta no encontrada'
                ], 404);
            }

            // Si hay token, validarlo
            $tokenData = null;
            if ($token) {
                try {
                    $tokenData = Crypt::decrypt($token);
                    
                    // Verificar que el token corresponde a esta encuesta
                    if ($tokenData['survey_id'] != $surveyId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Token no válido para esta encuesta'
                        ], 401);
                    }

                    // Verificar fecha de expiración del token
                    if (Carbon::createFromTimestamp($tokenData['expires_at'])->isPast()) {
                        return response()->json([
                            'success' => false,
                            'message' => 'El enlace de la encuesta ha expirado',
                            'expired' => true
                        ], 401);
                    }

                    // Verificar si ya se respondió la encuesta
                    $notification = NotificationSurvaysModel::where('id_survey', $surveyId)
                        ->where('destinatario', $tokenData['email']) // Usar nuevo campo destinatario
                        ->where('state_results', 'true') // Cambiar a string
                        ->whereNotNull('response_data')
                        ->first();

                    if ($notification) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Ya has respondido esta encuesta',
                            'already_responded' => true
                        ], 409);
                    }

                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Token inválido o corrupto'
                    ], 401);
                }
            }

            // Verificar estado de la encuesta
            if (!$survey->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está activa'
                ], 400);
            }

            // Verificar fechas de la encuesta
            $now = Carbon::now();
            if ($survey->start_date && $now->isBefore($survey->start_date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta aún no ha comenzado'
                ], 401);
            }

            if ($survey->end_date && $now->isAfter($survey->end_date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta ha finalizado',
                    'expired' => true
                ], 401);
            }

            // Preparar respuesta con datos de la encuesta
            $responseData = [
                'survey_id' => $surveyId,
                'survey_title' => $survey->title,
                'survey_description' => $survey->descrip,
                'survey_status' => $survey->status,
                'start_date' => $survey->start_date,
                'end_date' => $survey->end_date,
                'sections_count' => $survey->sections()->count(),
                'questions_count' => $survey->surveyQuestions()->count()
            ];

            // Si hay token, agregar información del destinatario
            if ($tokenData) {
                $responseData['respondent_email'] = $tokenData['email'];
                $responseData['respondent_name'] = $tokenData['respondent_name'];
                $responseData['token_expires_at'] = Carbon::createFromTimestamp($tokenData['expires_at'])->toISOString();
            }

            return response()->json([
                'success' => true,
                'message' => 'Acceso validado correctamente',
                'data' => $responseData
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al validar acceso a encuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Método mejorado para guardar respuestas con validación de token
     */
    public function storeWithTokenValidation(Request $request)
    {
        try {
            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'respondent_name' => 'required|string|max:255',
                'respondent_email' => 'required|email|max:255',
                'responses' => 'required',
                'token' => 'nullable|string',
                'status' => 'string|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();

            // Si hay token, validar el acceso primero
            if (!empty($data['token'])) {
                $accessValidation = $this->validateEmailSurveyAccess($request);
                if ($accessValidation->getStatusCode() !== 200) {
                    return $accessValidation;
                }
            }

            // PERMITIR REENVÍOS: Eliminar verificación de duplicados para permitir múltiples respuestas

            // PERMITIR REENVÍOS: Crear nuevo registro siempre
            $notification = NotificationSurvaysModel::create([
                'data' => json_encode([
                    'survey_id' => $data['survey_id'],
                    'respondent_name' => $data['respondent_name'],
                    'type' => !empty($data['token']) ? 'email_survey_response' : 'manual_response',
                    'submitted_at' => Carbon::now()
                ]),
                'state' => 'completed',
                'state_results' => 'true',
                'date_insert' => Carbon::now(),
                'id_survey' => $data['survey_id'],
                'destinatario' => $data['respondent_email'],
                'expired_date' => Carbon::now()->addDays(30),
                'respondent_name' => $data['respondent_name'],
                'response_data' => $data['responses']
            ]);

            return response()->json([
                'success' => true,
                'message' => '¡Respuesta guardada exitosamente! Gracias por participar en la encuesta.',
                'data' => [
                    'notification_id' => $notification->id,
                    'survey_id' => $data['survey_id'],
                    'respondent_name' => $data['respondent_name'],
                    'respondent_email' => $data['respondent_email'],
                    'submitted_at' => Carbon::now()->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la respuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}