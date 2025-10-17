<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyAnswersModel;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use App\Services\URLIntegrityService;

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

            // CRÍTICO: Validar que la encuesta existe y no esté vencida
            $survey = SurveyModel::find($data['survey_id']);
            if (!$survey) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no existe'
                ], 404);
            }

            // Verificar si la encuesta está activa
            if (!$survey->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está activa'
                ], 403);
            }

            // Verificar fechas de la encuesta
            $now = Carbon::now();
            if ($survey->start_date && $now->isBefore($survey->start_date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta aún no ha comenzado'
                ], 403);
            }

            if ($survey->end_date && $now->isAfter($survey->end_date)) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta ha finalizado',
                    'expired' => true
                ], 403);
            }

            // MEJORADO: Buscar primero si existe una notificación enviada para este email y encuesta
            // CRÍTICO: Aceptar múltiples estados para soportar reenvíos
            $existingNotification = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['respondent_email'])
                ->whereIn('state', ['1', 'pending_response', 'sent', 'enviado', 'enviada']) // ✅ SOLUCIÓN: Aceptar múltiples estados
                ->orderBy('date_insert', 'desc') // Obtener la más reciente
                ->first();

            if ($existingNotification) {
                // Si existe una notificación enviada, actualizarla en lugar de crear una nueva
                $existingNotification->update([
                    'data' => json_encode([
                        'survey_id' => $data['survey_id'],
                        'respondent_name' => $data['respondent_name'],
                        'type' => 'email_survey_response',
                        'submitted_at' => Carbon::now()
                    ]),
                    'state' => 'completed',
                    'state_results' => 'true',
                    'respondent_name' => $data['respondent_name'],
                    'response_data' => $data['responses']
                ]);

                $notification = $existingNotification;
                \Log::info('✅ Updated existing notification for email response (via store method)', [
                    'notification_id' => $notification->id,
                    'survey_id' => $data['survey_id'],
                    'email' => $data['respondent_email']
                ]);
            } else {
                // Si no existe notificación previa, crear nueva (respuesta manual del administrador)
                $notification = NotificationSurvaysModel::create([
                    'data' => json_encode([
                        'survey_id' => $data['survey_id'],
                        'respondent_name' => $data['respondent_name'],
                        'type' => 'manual_response'
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

                \Log::info('✅ Created new notification for manual response', [
                    'notification_id' => $notification->id,
                    'survey_id' => $data['survey_id'],
                    'email' => $data['respondent_email']
                ]);
            }

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
                    'responses' => $response->response_data,
                    'enabled' => $response->enabled // Incluir el campo enabled
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
                'token' => 'nullable|string',
                'email' => 'nullable|email',
                'hash' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                \Log::warning('❌ URL validation failed - Invalid parameters', [
                    'errors' => $validator->errors(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Acceso no autorizado. Esta URL no es válida o ha sido modificada.',
                    'errors' => $validator->errors()
                ], 400);
            }

            $surveyId = $request->input('survey_id');
            $token = $request->input('token');
            $email = $request->input('email');
            $hash = $request->input('hash');

            // SEGURIDAD CRÍTICA: Validar integridad de URL para acceso sin token
            if (!$token && !$hash) {
                \Log::warning('❌ Security violation - URL missing both token and hash', [
                    'survey_id' => $surveyId,
                    'email' => $email,
                    'ip' => $request->ip(),
                    'url' => $request->fullUrl()
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Acceso no autorizado. Esta URL no es válida o ha sido modificada.'
                ], 401);
            }

            // SEGURIDAD: Validar hash de integridad para URLs sin token
            if (!$token && $hash) {
                if (!$email) {
                    \Log::warning('❌ Hash validation failed - Missing email parameter', [
                        'survey_id' => $surveyId,
                        'hash' => $hash,
                        'ip' => $request->ip()
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Acceso no autorizado. Esta URL no es válida o ha sido modificada.'
                    ], 401);
                }

                // Validar formato de email
                if (!URLIntegrityService::validateEmailFormat($email)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Acceso no autorizado. Esta URL no es válida o ha sido modificada.'
                    ], 401);
                }

                // Validar hash de integridad
                // validateHashWithDetails ya maneja tanto HMAC como legacy (con/sin timestamp)
                $hashValidationResult = URLIntegrityService::validateHashWithDetails($surveyId, $email, $hash);

                \Log::info('🔍 Hash validation result:', [
                    'survey_id' => $surveyId,
                    'email' => $email,
                    'hash_length' => strlen($hash),
                    'valid' => $hashValidationResult['valid'],
                    'error_type' => $hashValidationResult['error_type'] ?? 'none'
                ]);

                if (!$hashValidationResult['valid']) {
                    $errorType = $hashValidationResult['error_type'] ?? 'invalid_url';

                    switch ($errorType) {
                        case 'device_mismatch':
                            return response()->json([
                                'success' => false,
                                'message' => 'Este enlace solo puede ser usado desde el dispositivo original.',
                                'error_type' => 'device_mismatch'
                            ], 403);

                        case 'link_sharing':
                            return response()->json([
                                'success' => false,
                                'message' => 'Este enlace no puede ser compartido. Cada enlace es personal e intransferible.',
                                'error_type' => 'link_sharing'
                            ], 403);

                        case 'hash_expired':
                            return response()->json([
                                'success' => false,
                                'message' => 'El enlace de acceso ha expirado.',
                                'error_type' => 'expired'
                            ], 401);

                        case 'hash_tampering':
                        case 'invalid_format':
                        default:
                            return response()->json([
                                'success' => false,
                                'message' => 'Acceso no autorizado. Esta URL no es válida o ha sido modificada.',
                                'error_type' => 'invalid_url'
                            ], 401);
                    }
                }

                \Log::info('✅ Hash-based URL validation successful', [
                    'survey_id' => $surveyId,
                    'email' => $email,
                    'ip' => $request->ip()
                ]);

                // VALIDACIÓN CRÍTICA: Verificar que el enlace no haya sido compartido entre dispositivos
                $deviceValidationResult = URLIntegrityService::validateDeviceAccess($surveyId, $email, $hash);

                if (!$deviceValidationResult['valid']) {
                    $errorType = $deviceValidationResult['error_type'] ?? 'unknown';

                    switch ($errorType) {
                        case 'link_sharing':
                            \Log::warning('🚨 LINK SHARING BLOCKED', [
                                'survey_id' => $surveyId,
                                'email' => $email,
                                'ip' => $request->ip(),
                                'user_agent' => $request->userAgent()
                            ]);
                            return response()->json([
                                'success' => false,
                                'message' => 'Este enlace no puede ser compartido. Cada enlace es personal e intransferible.',
                                'error_type' => 'link_sharing'
                            ], 403);

                        case 'link_blocked':
                            return response()->json([
                                'success' => false,
                                'message' => 'Este enlace ha sido bloqueado por motivos de seguridad.',
                                'error_type' => 'link_blocked'
                            ], 403);

                        default:
                            return response()->json([
                                'success' => false,
                                'message' => 'Error de validación de acceso.',
                                'error_type' => 'validation_error'
                            ], 500);
                    }
                }

                if ($deviceValidationResult['is_first_access']) {
                    \Log::info('🔐 First device access registered', [
                        'survey_id' => $surveyId,
                        'email' => $email,
                        'access_token_id' => $deviceValidationResult['access_token_id'],
                        'ip' => $request->ip()
                    ]);
                } else {
                    \Log::info('✅ Returning device access validated', [
                        'survey_id' => $surveyId,
                        'email' => $email,
                        'access_token_id' => $deviceValidationResult['access_token_id'],
                        'ip' => $request->ip()
                    ]);
                }
            }

            // Verificar que la encuesta existe
            $survey = SurveyModel::with(['sections', 'surveyQuestions'])->find($surveyId);
            if (!$survey) {
                return response()->json([
                    'success' => false,
                    'message' => 'Encuesta no encontrada'
                ], 404);
            }

            // NUEVA VALIDACIÓN: Verificar que existe registro de notificación para este usuario
            // (aplica solo cuando hay email, ya sea con token o con hash)
            if ($email) {
                $notificationExists = NotificationSurvaysModel::where('id_survey', $surveyId)
                    ->where('destinatario', $email)
                    ->whereIn('state', ['1', 'pending_response', 'sent', 'enviado', 'enviada', 'completed'])
                    ->exists();

                if (!$notificationExists) {
                    \Log::warning('❌ ACCESO DENEGADO - No existe registro de notificación para este usuario', [
                        'survey_id' => $surveyId,
                        'email' => $email,
                        'ip' => $request->ip(),
                        'note' => 'El registro de notificación fue eliminado o nunca existió para este usuario'
                    ]);

                    return response()->json([
                        'success' => false,
                        'message' => 'Esta encuesta no está disponible para tu cuenta. El registro no existe o ha sido eliminado.',
                        'error_type' => 'not_found_for_user'
                    ], 404);
                }
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

                // CRÍTICO: Validar que el email del formulario coincida con el del token
                try {
                    $tokenData = Crypt::decrypt($data['token']);
                    if ($tokenData['email'] !== $data['respondent_email']) {
                        \Log::warning('🚨 INTENTO DE SUPLANTACIÓN DETECTADO:', [
                            'token_email' => $tokenData['email'],
                            'submitted_email' => $data['respondent_email'],
                            'survey_id' => $data['survey_id'],
                            'ip' => request()->ip(),
                            'user_agent' => request()->userAgent()
                        ]);

                        return response()->json([
                            'success' => false,
                            'message' => 'Email no autorizado para este enlace de encuesta',
                            'unauthorized' => true
                        ], 403);
                    }
                } catch (\Exception $e) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Token inválido o corrupto'
                    ], 401);
                }
            }

            // CORREGIDO: Buscar y actualizar registro existente en lugar de crear duplicados
            if (!empty($data['token'])) {
                // Para respuestas con token (desde email), buscar y actualizar el registro existente
                // CRÍTICO: Aceptar múltiples estados para soportar reenvíos
                $existingNotification = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                    ->where('destinatario', $data['respondent_email'])
                    ->whereIn('state', ['1', 'pending_response', 'sent', 'enviado', 'enviada']) // ✅ SOLUCIÓN: Aceptar múltiples estados
                    ->orderBy('date_insert', 'desc') // Obtener la más reciente
                    ->first();

                if ($existingNotification) {
                    // Actualizar el registro existente
                    $existingNotification->update([
                        'data' => json_encode([
                            'survey_id' => $data['survey_id'],
                            'respondent_name' => $data['respondent_name'],
                            'type' => 'email_survey_response',
                            'submitted_at' => Carbon::now()
                        ]),
                        'state' => 'completed',
                        'state_results' => 'true',
                        'respondent_name' => $data['respondent_name'],
                        'response_data' => $data['responses']
                    ]);

                    $notification = $existingNotification;
                    \Log::info('✅ Updated existing notification for token-based response', [
                        'notification_id' => $notification->id,
                        'survey_id' => $data['survey_id'],
                        'email' => $data['respondent_email']
                    ]);
                } else {
                    // Si no existe notificación previa, crear una nueva (caso excepcional)
                    $notification = NotificationSurvaysModel::create([
                        'data' => json_encode([
                            'survey_id' => $data['survey_id'],
                            'respondent_name' => $data['respondent_name'],
                            'type' => 'email_survey_response',
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

                    \Log::warning('⚠️ Created new notification for token-based response (no existing notification found)', [
                        'survey_id' => $data['survey_id'],
                        'email' => $data['respondent_email']
                    ]);
                }
            } else {
                // Para respuestas manuales (sin token), crear nuevo registro siempre
                $notification = NotificationSurvaysModel::create([
                    'data' => json_encode([
                        'survey_id' => $data['survey_id'],
                        'respondent_name' => $data['respondent_name'],
                        'type' => 'manual_response',
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
            }

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

    /**
     * Verificar si ya existe una respuesta para una combinación de survey_id + email
     */
    public function checkDuplicateResponse(Request $request)
    {
        try {
            // Validar los datos de entrada
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'email' => 'required|email|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();

            // Buscar si ya existe una respuesta completada para esta combinación
            $existingResponse = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['email'])
                ->where('state', 'completed')
                ->where('state_results', 'true')
                ->whereNotNull('response_data')
                ->first();

            if ($existingResponse) {
                return response()->json([
                    'success' => true,
                    'already_responded' => true,
                    'message' => 'Esta encuesta ya fue respondida por este correo electrónico',
                    'response_date' => $existingResponse->date_insert
                ]);
            }

            // Si no ha respondido, buscar información del destinatario y verificar si está habilitado
            $recipientInfo = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['email'])
                ->first(); // Buscar cualquier registro (sin filtrar por estado)

            // Verificar si el encuestado está deshabilitado
            if ($recipientInfo && !$recipientInfo->enabled) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta encuesta ya no está disponible para ti',
                    'disabled' => true,
                    'error_code' => 'RESPONDENT_DISABLED'
                ], 403);
            }

            $responseData = [
                'success' => true,
                'already_responded' => false,
                'message' => 'No se encontró respuesta previa para esta encuesta'
            ];

            // Agregar información del destinatario si está disponible
            if ($recipientInfo) {
                $responseData['recipient_data'] = [
                    'email' => $recipientInfo->destinatario,
                    'name' => $recipientInfo->respondent_name ?? null
                ];
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            \Log::error('Error checking duplicate response: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al verificar respuesta duplicada',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener las respuestas de un encuestado específico
     */
    public function getRespondentResponses(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer|min:1',
                'email' => 'required|email|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();

            // Buscar la respuesta específica del encuestado
            $respondentResponse = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['email'])
                ->where('state', 'completed')
                ->where('state_results', 'true')
                ->whereNotNull('response_data')
                ->first();

            if (!$respondentResponse) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron respuestas para este encuestado'
                ], 404);
            }

            // Formatear la respuesta
            $responseData = $respondentResponse->response_data;
            // Si response_data es un string JSON, parsearlo
            if (is_string($responseData)) {
                $responseData = json_decode($responseData, true);
            }

            $formattedResponse = [
                'id' => $respondentResponse->id,
                'survey_id' => $respondentResponse->id_survey,
                'respondent_name' => $respondentResponse->respondent_name,
                'respondent_email' => $respondentResponse->destinatario,
                'completed_at' => $respondentResponse->date_insert,
                'status' => 'Contestada',
                'responses' => $responseData
            ];

            return response()->json([
                'success' => true,
                'data' => $formattedResponse
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error getting respondent responses: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener respuestas del encuestado',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Habilitar o deshabilitar un encuestado para una encuesta específica
     */
    public function toggleRespondentStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer|min:1',
                'email' => 'required|email|max:255',
                'enabled' => 'required|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $data = $validator->validated();

            // Buscar el registro del encuestado en la encuesta
            $respondentRecord = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['email'])
                ->first();

            if (!$respondentRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró el encuestado en esta encuesta'
                ], 404);
            }

            // Lógica para guardar/restaurar el estado anterior
            if ($data['enabled'] === false && $respondentRecord->enabled === true) {
                // Se está deshabilitando: guardar el estado actual
                $currentStatus = null;
                if ($respondentRecord->state === 'completed') {
                    $currentStatus = 'Contestada';
                } elseif ($respondentRecord->state === '1') {
                    $currentStatus = 'Enviada';
                }

                $respondentRecord->update([
                    'enabled' => false,
                    'previous_status' => $currentStatus
                ]);
            } elseif ($data['enabled'] === true && $respondentRecord->enabled === false) {
                // Se está habilitando: restaurar el estado anterior si existe
                if ($respondentRecord->previous_status) {
                    // Restaurar el estado basado en el previous_status
                    $newState = null;
                    if ($respondentRecord->previous_status === 'Contestada') {
                        $newState = 'completed';
                    } elseif ($respondentRecord->previous_status === 'Enviada') {
                        $newState = '1';
                    }

                    $respondentRecord->update([
                        'enabled' => true,
                        'state' => $newState,
                        'previous_status' => null // Limpiar el estado anterior
                    ]);
                } else {
                    // Si no hay estado anterior, solo habilitar
                    $respondentRecord->update(['enabled' => true]);
                }
            } else {
                // No hay cambio en el estado enabled
                $respondentRecord->update(['enabled' => $data['enabled']]);
            }

            $statusText = $data['enabled'] ? 'habilitado' : 'deshabilitado';

            // \Log::info('Respondent status updated', [
            //     'survey_id' => $data['survey_id'],
            //     'email' => $data['email'],
            //     'enabled' => $data['enabled'],
            //     'respondent_name' => $respondentRecord->respondent_name
            // ]);

            return response()->json([
                'success' => true,
                'message' => "Encuestado {$statusText} exitosamente",
                'data' => [
                    'survey_id' => $data['survey_id'],
                    'email' => $data['email'],
                    'enabled' => $data['enabled'],
                    'respondent_name' => $respondentRecord->respondent_name,
                    'status_text' => $statusText
                ]
            ], 200);

        } catch (\Exception $e) {
            // \Log::error('Error toggling respondent status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cambiar el estado del encuestado',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}