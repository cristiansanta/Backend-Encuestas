<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SurveyEmailController extends Controller
{
    /**
     * Genera un enlace de encuesta con JWT para acceso por correo
     */
    public function generateSurveyLink(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer|exists:surveys,id',
                'email' => 'required|email',
                'respondent_name' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();
            
            // Verificar que la encuesta existe y está activa
            $survey = SurveyModel::findOrFail($data['survey_id']);
            
            if (!$survey->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está activa'
                ], 400);
            }

            // Crear un token único para esta combinación de encuesta y email
            $tokenData = [
                'survey_id' => $data['survey_id'],
                'email' => $data['email'],
                'respondent_name' => $data['respondent_name'] ?? null,
                'issued_at' => Carbon::now()->timestamp,
                'expires_at' => $survey->end_date ? $survey->end_date->timestamp : Carbon::now()->addDays(30)->timestamp,
                'unique_id' => Str::uuid()
            ];

            // Cifrar el token con Laravel Crypt
            $encryptedToken = Crypt::encrypt($tokenData);

            // Crear o actualizar el registro de notificación
            $notification = NotificationSurvaysModel::updateOrCreate(
                [
                    'id_survey' => $data['survey_id'],
                    'email' => json_encode([$data['email']])
                ],
                [
                    'data' => json_encode([
                        'survey_id' => $data['survey_id'],
                        'respondent_name' => $data['respondent_name'],
                        'type' => 'email_survey_access',
                        'token_issued_at' => Carbon::now(),
                        'unique_token' => $tokenData['unique_id']
                    ]),
                    'state' => 'sent',
                    'state_results' => false,
                    'date_insert' => Carbon::now(),
                    'expired_date' => $survey->end_date ?? Carbon::now()->addDays(30),
                    'respondent_name' => $data['respondent_name']
                ]
            );

            // Generar la URL de la encuesta
            $surveyUrl = config('app.frontend_url', 'http://localhost:3000') . 
                        '/survey-view-manual/' . $data['survey_id'] . 
                        '?token=' . urlencode($encryptedToken);

            return response()->json([
                'success' => true,
                'message' => 'Enlace de encuesta generado exitosamente',
                'data' => [
                    'survey_url' => $surveyUrl,
                    'encrypted_token' => $encryptedToken,
                    'notification_id' => $notification->id,
                    'expires_at' => $survey->end_date ?? Carbon::now()->addDays(30)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar enlace de encuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Valida el acceso a la encuesta mediante token JWT
     */
    public function validateAccess(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'token' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token o ID de encuesta requeridos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $surveyId = $request->input('survey_id');
            $token = $request->input('token');

            // Desencriptar el token
            try {
                $tokenData = Crypt::decrypt($token);
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token inválido o corrupto'
                ], 401);
            }

            // Verificar que el token corresponde a esta encuesta
            if ($tokenData['survey_id'] != $surveyId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token no válido para esta encuesta'
                ], 401);
            }

            // Verificar fecha de expiración
            if (Carbon::createFromTimestamp($tokenData['expires_at'])->isPast()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta ha expirado',
                    'expired' => true
                ], 401);
            }

            // Verificar que la encuesta existe y está activa
            $survey = SurveyModel::find($surveyId);
            if (!$survey || !$survey->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Encuesta no encontrada o inactiva'
                ], 404);
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

            // Verificar si ya se respondió la encuesta
            $notification = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->whereJsonContains('email', $tokenData['email'])
                ->where('state_results', true)
                ->whereNotNull('response_data')
                ->first();

            if ($notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya has respondido esta encuesta',
                    'already_responded' => true
                ], 409);
            }

            return response()->json([
                'success' => true,
                'message' => 'Acceso validado correctamente',
                'data' => [
                    'survey_id' => $surveyId,
                    'respondent_email' => $tokenData['email'],
                    'respondent_name' => $tokenData['respondent_name'],
                    'survey_title' => $survey->title,
                    'survey_description' => $survey->descrip,
                    'expires_at' => Carbon::createFromTimestamp($tokenData['expires_at'])->toISOString()
                ]
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
     * Guarda la respuesta de encuesta enviada por correo
     */
    public function submitSurveyResponse(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'token' => 'required|string',
                'responses' => 'required',
                'respondent_name' => 'nullable|string|max:255'
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
            $responses = $request->input('responses');
            $respondentName = $request->input('respondent_name');

            // Validar el acceso primero
            $accessValidation = $this->validateAccess($request);
            if ($accessValidation->getStatusCode() !== 200) {
                return $accessValidation;
            }

            // Desencriptar el token para obtener el email
            $tokenData = Crypt::decrypt($token);

            // Crear o actualizar el registro de notificación con la respuesta
            $notification = NotificationSurvaysModel::updateOrCreate(
                [
                    'id_survey' => $surveyId,
                    'email' => json_encode([$tokenData['email']])
                ],
                [
                    'data' => json_encode([
                        'survey_id' => $surveyId,
                        'respondent_name' => $respondentName ?? $tokenData['respondent_name'],
                        'type' => 'email_survey_response',
                        'submitted_at' => Carbon::now(),
                        'unique_token' => $tokenData['unique_id']
                    ]),
                    'state' => 'completed',
                    'state_results' => true,
                    'date_insert' => Carbon::now(),
                    'expired_date' => Carbon::createFromTimestamp($tokenData['expires_at']),
                    'respondent_name' => $respondentName ?? $tokenData['respondent_name'],
                    'response_data' => $responses
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Respuesta guardada exitosamente. ¡Gracias por participar!',
                'data' => [
                    'notification_id' => $notification->id,
                    'survey_id' => $surveyId,
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

    /**
     * Verifica el estado de una respuesta de encuesta
     */
    public function checkResponseStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'email' => 'required|email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $surveyId = $request->input('survey_id');
            $email = $request->input('email');

            $notification = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->whereJsonContains('email', $email)
                ->first();

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró registro para esta encuesta y email'
                ], 404);
            }

            $survey = SurveyModel::find($surveyId);
            $isExpired = $survey && $survey->end_date && Carbon::now()->isAfter($survey->end_date);

            return response()->json([
                'success' => true,
                'data' => [
                    'has_responded' => (bool) $notification->state_results && !empty($notification->response_data),
                    'is_expired' => $isExpired,
                    'survey_status' => $survey ? $survey->status : false,
                    'response_date' => $notification->state_results ? $notification->date_insert : null,
                    'respondent_name' => $notification->respondent_name
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar estado de respuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}