<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyModel;
use App\Models\SurveyRespondentModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Crypt;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class SurveyEmailController extends Controller
{
    /**
     * Obtener información del cliente para logging
     */
    private function getClientInfo(Request $request)
    {
        return [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device' => $this->detectDevice($request->userAgent()),
            'browser' => $this->detectBrowser($request->userAgent()),
            'platform' => $this->detectPlatform($request->userAgent()),
            'referer' => $request->header('referer'),
            'timestamp' => Carbon::now()->toISOString()
        ];
    }

    /**
     * Detectar tipo de dispositivo
     */
    private function detectDevice($userAgent)
    {
        if (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'mobile';
        } elseif (preg_match('/Tablet/', $userAgent)) {
            return 'tablet';
        }
        return 'desktop';
    }

    /**
     * Detectar navegador
     */
    private function detectBrowser($userAgent)
    {
        if (preg_match('/Chrome/', $userAgent)) return 'Chrome';
        if (preg_match('/Firefox/', $userAgent)) return 'Firefox';
        if (preg_match('/Safari/', $userAgent)) return 'Safari';
        if (preg_match('/Edge/', $userAgent)) return 'Edge';
        return 'Unknown';
    }

    /**
     * Detectar plataforma
     */
    private function detectPlatform($userAgent)
    {
        if (preg_match('/Windows/', $userAgent)) return 'Windows';
        if (preg_match('/Mac/', $userAgent)) return 'macOS';
        if (preg_match('/Linux/', $userAgent)) return 'Linux';
        if (preg_match('/Android/', $userAgent)) return 'Android';
        if (preg_match('/iOS/', $userAgent)) return 'iOS';
        return 'Unknown';
    }

    /**
     * Log de errores categorizado
     */
    private function logError($category, $message, $context = [])
    {
        Log::error("[SURVEY_EMAIL_{$category}] {$message}", $context);
    }

    /**
     * Log de información
     */
    private function logInfo($category, $message, $context = [])
    {
        Log::info("[SURVEY_EMAIL_{$category}] {$message}", $context);
    }

    /**
     * Extrae un nombre aproximado del correo electrónico
     */
    private function extractNameFromEmail($email)
    {
        // Extraer la parte antes del @
        $namePart = explode('@', $email)[0];

        // Reemplazar puntos, guiones y números con espacios
        $namePart = preg_replace('/[._-]/', ' ', $namePart);
        $namePart = preg_replace('/\d+/', '', $namePart);

        // Capitalizar cada palabra
        $name = ucwords(trim($namePart));

        // Si queda muy corto o vacío, usar el correo completo
        if (strlen($name) < 2) {
            return $email;
        }

        return $name;
    }

    /**
     * Genera un enlace de encuesta con JWT para acceso por correo
     */
    public function generateSurveyLink(Request $request)
    {
        try {
            // LOGGING CRÍTICO: Identificar quién está llamando este método
            \Log::emergency('🚨 generateSurveyLink LLAMADO - STACK TRACE:', [
                'request_data' => $request->all(),
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
                'referer' => $request->header('referer'),
                'stack_trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)
            ]);

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

            // Log debug para identificar problemas de tipos de datos
            \Log::info('🔍 SurveyEmailController - Datos que se van a insertar:', [
                'survey_id' => $data['survey_id'],
                'survey_id_type' => gettype($data['survey_id']),
                'email' => $data['email'],
                'email_type' => gettype($data['email']),
                'respondent_name' => $data['respondent_name'] ?? 'null',
                'respondent_name_type' => gettype($data['respondent_name']),
                'survey_end_date' => $survey->end_date,
                'survey_end_date_type' => gettype($survey->end_date),
                'current_datetime' => Carbon::now(),
                'addDays_result' => Carbon::now()->addDays(30)
            ]);

            // BUSCAR NOTIFICACIÓN EXISTENTE en lugar de crear nueva
            // Esto evita duplicados del flujo AsignationMigrate -> Notification/store
            // CORREGIDO: Buscar registros con CUALQUIER estado válido para evitar duplicados
            $notification = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['email']) // Usar nuevo campo destinatario
                ->whereIn('state', ['1', 'sent', 'enviado', 'enviada']) // Verificar cualquier estado enviado
                ->orderBy('date_insert', 'desc')
                ->first();

            \Log::emergency('🔍 generateSurveyLink - BÚSQUEDA DE NOTIFICACIÓN:', [
                'survey_id' => $data['survey_id'],
                'email' => $data['email'],
                'notification_found' => $notification ? true : false,
                'notification_id' => $notification ? $notification->id : null,
                'notification_state' => $notification ? $notification->state : null
            ]);

            // Si no existe notificación, crear una nueva (caso directo de generación de link)
            if (!$notification) {
                \Log::emergency('⚠️ generateSurveyLink - CREANDO NUEVA NOTIFICACIÓN porque no se encontró existente');
            } else {
                \Log::emergency('✅ generateSurveyLink - USANDO NOTIFICACIÓN EXISTENTE:', [
                    'existing_id' => $notification->id,
                    'existing_state' => $notification->state
                ]);
            }

            // CRÍTICO: No crear notificación aquí, solo generar token
            // Las notificaciones con contenido se crean en NotificationSurvaysController
            // Aquí solo registramos que se generó un token de acceso
            \Log::info('🔐 SurveyEmailController: Generando solo token, NO creando notificación con body vacío', [
                'survey_id' => (int)$data['survey_id'],
                'email' => $data['email'],
                'reason' => 'Evitar body vacío - notificación se creará en NotificationSurvaysController'
            ]);

            // No crear notificación aquí para evitar registros con body vacío
            // $notification permanece con el valor encontrado anteriormente o null

            // SINCRONIZAR: Crear registro en survey_respondents para tracking
            $existingRespondent = SurveyRespondentModel::where('survey_id', $data['survey_id'])
                ->where('respondent_email', $data['email'])
                ->first();

            if (!$existingRespondent) {
                // Crear token único para el correo
                $emailToken = Str::random(64);

                SurveyRespondentModel::create([
                    'survey_id' => $data['survey_id'],
                    'respondent_name' => $data['respondent_name'] ?? $this->extractNameFromEmail($data['email']),
                    'respondent_email' => $data['email'],
                    'status' => 'Enviada', // Usar valor válido según constraint - Enviada es el estado apropiado para tokens generados
                    'sent_at' => null, // Se actualiza cuando se envíe el correo real
                    'notification_id' => null, // Se asigna cuando se cree la notificación real
                    'email_token' => $emailToken
                ]);
            }

            // Generar la URL de la encuesta con formato email+hash (compatible con sistema existente)
            // Crear hash similar al sistema de validación existente
            $baseHash = $data['survey_id'] . '-' . $data['email'];
            $hash = base64_encode($baseHash);
            // Limpiar caracteres especiales del hash para URL
            $hash = str_replace(['+', '/', '='], ['', '', ''], $hash);

            $surveyUrl = config('app.frontend_url', 'http://localhost:5173') .
                        '/encuestados/survey-view-manual/' . $data['survey_id'] .
                        '?email=' . urlencode($data['email']) . '&hash=' . $hash;

            return response()->json([
                'success' => true,
                'message' => 'Enlace de encuesta generado exitosamente',
                'data' => [
                    'survey_url' => $surveyUrl,
                    'encrypted_token' => $encryptedToken,
                    'notification_id' => $notification ? $notification->id : null,
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
     * Valida el acceso a la encuesta mediante token JWT con manejo robusto de errores
     */
    public function validateAccess(Request $request)
    {
        $startTime = microtime(true);
        $clientInfo = $this->getClientInfo($request);

        try {
            // 1. ERRORES DE VALIDACIÓN DE DATOS
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer|min:1',
                'token' => 'required|string|min:10'
            ]);

            if ($validator->fails()) {
                $this->logError('VALIDATION_ERROR', 'Datos de validación incorrectos', [
                    'errors' => $validator->errors(),
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'VALIDATION_ERROR',
                    'message' => 'Los datos proporcionados no son válidos',
                    'details' => $validator->errors(),
                    'user_message' => 'Por favor verifica que el enlace esté completo y sea válido.'
                ], 400);
            }

            $surveyId = $request->input('survey_id');
            $token = $request->input('token');

            // 2. ERRORES DE AUTENTICACIÓN Y AUTORIZACIÓN
            try {
                $tokenData = Crypt::decrypt($token);

                // Validar estructura del token
                if (!is_array($tokenData) || !isset($tokenData['survey_id'], $tokenData['email'], $tokenData['expires_at'])) {
                    throw new \Exception('Token malformado');
                }

            } catch (\Exception $e) {
                $this->logError('AUTH_ERROR', 'Token inválido o corrupto', [
                    'token_length' => strlen($token),
                    'error' => $e->getMessage(),
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'INVALID_TOKEN',
                    'message' => 'El enlace de acceso no es válido o ha sido alterado',
                    'user_message' => 'Este enlace no es válido. Por favor contacta al administrador para obtener un nuevo enlace.',
                    'support_info' => [
                        'action' => 'Solicitar nuevo enlace',
                        'contact' => 'Contacta al administrador de la encuesta'
                    ]
                ], 401);
            }

            // Verificar que el token corresponde a esta encuesta
            if ($tokenData['survey_id'] != $surveyId) {
                $this->logError('AUTH_ERROR', 'Token no corresponde a la encuesta', [
                    'token_survey_id' => $tokenData['survey_id'],
                    'requested_survey_id' => $surveyId,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SURVEY_MISMATCH',
                    'message' => 'Este enlace no corresponde a la encuesta solicitada',
                    'user_message' => 'El enlace que usaste no corresponde a esta encuesta. Verifica que estés usando el enlace correcto.'
                ], 401);
            }

            // 3. ERRORES DE ESTADO DE ENCUESTA - Verificar fecha de expiración
            $expiresAt = Carbon::createFromTimestamp($tokenData['expires_at']);
            if ($expiresAt->isPast()) {
                $this->logError('SURVEY_STATE_ERROR', 'Token expirado', [
                    'expires_at' => $expiresAt->toISOString(),
                    'current_time' => Carbon::now()->toISOString(),
                    'survey_id' => $surveyId,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'TOKEN_EXPIRED',
                    'message' => 'El enlace de acceso ha expirado',
                    'expired' => true,
                    'expired_at' => $expiresAt->toISOString(),
                    'user_message' => 'Este enlace ha expirado. La encuesta ya no está disponible para responder.',
                    'support_info' => [
                        'expired_date' => $expiresAt->format('d/m/Y H:i'),
                        'action' => 'Contactar administrador si crees que esto es un error'
                    ]
                ], 410);
            }

            // Verificar que la encuesta existe y está activa
            $survey = SurveyModel::find($surveyId);
            if (!$survey) {
                $this->logError('SURVEY_STATE_ERROR', 'Encuesta no encontrada', [
                    'survey_id' => $surveyId,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SURVEY_NOT_FOUND',
                    'message' => 'La encuesta no fue encontrada',
                    'user_message' => 'Esta encuesta no existe o ha sido eliminada.',
                    'support_info' => [
                        'survey_id' => $surveyId,
                        'action' => 'Verificar con el administrador'
                    ]
                ], 404);
            }

            if (!$survey->status) {
                $this->logError('SURVEY_STATE_ERROR', 'Encuesta inactiva', [
                    'survey_id' => $surveyId,
                    'survey_status' => $survey->status,
                    'publication_status' => $survey->publication_status ?? 'null',
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SURVEY_INACTIVE',
                    'message' => 'La encuesta no está activa',
                    'user_message' => 'Esta encuesta no está disponible en este momento.',
                    'support_info' => [
                        'status' => 'Inactiva',
                        'action' => 'Contactar al administrador'
                    ]
                ], 403);
            }

            // Verificar fechas de la encuesta
            $now = Carbon::now();
            if ($survey->start_date && $now->isBefore($survey->start_date)) {
                $this->logError('SURVEY_STATE_ERROR', 'Encuesta no ha comenzado', [
                    'start_date' => $survey->start_date,
                    'current_time' => $now->toISOString(),
                    'survey_id' => $surveyId,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SURVEY_NOT_STARTED',
                    'message' => 'La encuesta aún no ha comenzado',
                    'start_date' => $survey->start_date,
                    'user_message' => 'Esta encuesta estará disponible a partir del ' . Carbon::parse($survey->start_date)->format('d/m/Y H:i'),
                    'support_info' => [
                        'start_date' => Carbon::parse($survey->start_date)->format('d/m/Y H:i'),
                        'action' => 'Vuelve en la fecha de inicio'
                    ]
                ], 425);
            }

            if ($survey->end_date && $now->isAfter($survey->end_date)) {
                $this->logError('SURVEY_STATE_ERROR', 'Encuesta finalizada', [
                    'end_date' => $survey->end_date,
                    'current_time' => $now->toISOString(),
                    'survey_id' => $surveyId,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SURVEY_ENDED',
                    'message' => 'La encuesta ha finalizado',
                    'expired' => true,
                    'end_date' => $survey->end_date,
                    'user_message' => 'Esta encuesta finalizó el ' . Carbon::parse($survey->end_date)->format('d/m/Y H:i'),
                    'support_info' => [
                        'end_date' => Carbon::parse($survey->end_date)->format('d/m/Y H:i'),
                        'action' => 'Contactar administrador si necesitas responder'
                    ]
                ], 410);
            }

            // 4. ERRORES DE AUTORIZACIÓN - Verificar si el encuestado está habilitado
            $respondentRecord = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->where('destinatario', $tokenData['email'])
                ->first();

            if ($respondentRecord && !$respondentRecord->enabled) {
                $this->logError('AUTH_ERROR', 'Encuestado deshabilitado', [
                    'survey_id' => $surveyId,
                    'email' => $tokenData['email'],
                    'respondent_name' => $respondentRecord->respondent_name,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'RESPONDENT_DISABLED',
                    'message' => 'No tienes autorización para responder esta encuesta',
                    'disabled' => true,
                    'user_message' => 'Esta encuesta ya no está disponible para ti.',
                    'support_info' => [
                        'action' => 'Contactar administrador si crees que esto es un error'
                    ]
                ], 403);
            }

            // 5. ERRORES DE SESIÓN - Verificar si ya se respondió la encuesta
            $notification = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->where('destinatario', $tokenData['email'])
                ->where('state', 'completed') // Verificar que esté completada
                ->where('state_results', 'true')
                ->whereNotNull('response_data')
                ->first();

            if ($notification) {
                $this->logError('SESSION_ERROR', 'Encuesta ya respondida', [
                    'survey_id' => $surveyId,
                    'email' => $tokenData['email'],
                    'response_date' => $notification->date_insert,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'ALREADY_RESPONDED',
                    'message' => 'Ya has respondido esta encuesta',
                    'already_responded' => true,
                    'response_date' => $notification->date_insert,
                    'user_message' => 'Ya completaste esta encuesta anteriormente.',
                    'support_info' => [
                        'response_date' => Carbon::parse($notification->date_insert)->format('d/m/Y H:i'),
                        'action' => 'Contactar administrador si necesitas modificar tu respuesta'
                    ]
                ], 409);
            }

            // 6. ERRORES DE CONTENIDO - Verificar integridad de la encuesta
            if (!$survey->title || empty(trim($survey->title))) {
                $this->logError('CONTENT_ERROR', 'Encuesta sin título', [
                    'survey_id' => $surveyId,
                    'client_info' => $clientInfo
                ]);

                return response()->json([
                    'success' => false,
                    'error_code' => 'SURVEY_INCOMPLETE',
                    'message' => 'La encuesta tiene problemas de contenido',
                    'user_message' => 'Esta encuesta no está configurada correctamente.',
                    'support_info' => [
                        'issue' => 'Contenido incompleto',
                        'action' => 'Reportar al administrador'
                    ]
                ], 422);
            }

            // Registro de acceso exitoso
            $this->logInfo('ACCESS_GRANTED', 'Acceso validado correctamente', [
                'survey_id' => $surveyId,
                'email' => $tokenData['email'],
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'client_info' => $clientInfo
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Acceso validado correctamente',
                'data' => [
                    'survey_id' => $surveyId,
                    'respondent_email' => $tokenData['email'],
                    'respondent_name' => $tokenData['respondent_name'] ?? null,
                    'survey_title' => $survey->title,
                    'survey_description' => $survey->descrip ?? '',
                    'expires_at' => Carbon::createFromTimestamp($tokenData['expires_at'])->toISOString(),
                    'time_remaining' => $expiresAt->diffForHumans(),
                    'server_time' => Carbon::now()->toISOString()
                ]
            ], 200);

        } catch (\Exception $e) {
            // 6. ERRORES DE RED Y CONECTIVIDAD / ERRORES INTERNOS
            $this->logError('SYSTEM_ERROR', 'Error interno del sistema', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'survey_id' => $surveyId ?? 'unknown',
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'client_info' => $clientInfo,
                'stack_trace' => config('app.debug') ? $e->getTraceAsString() : 'hidden'
            ]);

            return response()->json([
                'success' => false,
                'error_code' => 'SYSTEM_ERROR',
                'message' => 'Error interno del sistema',
                'user_message' => 'Ocurrió un problema técnico. Por favor intenta nuevamente en unos minutos.',
                'support_info' => [
                    'error_id' => uniqid('err_'),
                    'timestamp' => Carbon::now()->toISOString(),
                    'action' => 'Si el problema persiste, contacta al soporte técnico'
                ],
                'debug' => config('app.debug') ? $e->getMessage() : null
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

            // Usar transacción para asegurar consistencia entre tablas
            \DB::transaction(function() use ($surveyId, $tokenData, $respondentName, $responses) {
                // CORREGIDO: Buscar y actualizar el registro existente en lugar de crear uno nuevo
                $existingNotification = NotificationSurvaysModel::where('id_survey', $surveyId)
                    ->where('destinatario', $tokenData['email'])
                    ->where('state', '1') // Buscar notificaciones enviadas (state = '1')
                    ->first();

                if ($existingNotification) {
                    // Actualizar el registro existente
                    $existingNotification->update([
                        'data' => json_encode([
                            'survey_id' => $surveyId,
                            'respondent_name' => $respondentName ?? $tokenData['respondent_name'],
                            'type' => 'email_survey_response',
                            'submitted_at' => Carbon::now(),
                            'unique_token' => $tokenData['unique_id']
                        ]),
                        'state' => 'completed',
                        'state_results' => 'true',
                        'respondent_name' => $respondentName ?? $tokenData['respondent_name'],
                        'response_data' => $responses,
                        'scheduled_at' => Carbon::now()
                    ]);

                    $notification = $existingNotification;
                    \Log::info('✅ Updated existing notification for response', [
                        'notification_id' => $notification->id,
                        'survey_id' => $surveyId,
                        'email' => $tokenData['email']
                    ]);
                } else {
                    // Si no existe notificación previa, crear una nueva (caso excepcional)
                    $notification = NotificationSurvaysModel::create([
                        'data' => json_encode([
                            'survey_id' => $surveyId,
                            'respondent_name' => $respondentName ?? $tokenData['respondent_name'],
                            'type' => 'email_survey_response',
                            'submitted_at' => Carbon::now(),
                            'unique_token' => $tokenData['unique_id']
                        ]),
                        'state' => 'completed',
                        'state_results' => 'true',
                        'date_insert' => Carbon::now(),
                        'id_survey' => $surveyId,
                        'destinatario' => $tokenData['email'],
                        'asunto' => 'Respuesta de Encuesta',
                        'body' => '',
                        'expired_date' => Carbon::createFromTimestamp($tokenData['expires_at']),
                        'respondent_name' => $respondentName ?? $tokenData['respondent_name'],
                        'response_data' => $responses,
                        'scheduled_at' => Carbon::now()
                    ]);

                    \Log::warning('⚠️ Created new notification for response (no existing notification found)', [
                        'survey_id' => $surveyId,
                        'email' => $tokenData['email']
                    ]);
                }

                // CRÍTICO: Sincronizar con survey_respondents
                SurveyRespondentModel::where('survey_id', $surveyId)
                    ->where('respondent_email', $tokenData['email'])
                    ->update([
                        'status' => 'Contestada',
                        'responded_at' => Carbon::now(),
                        'response_data' => $responses,
                        'notification_id' => $notification->id
                    ]);

                $this->logInfo('RESPONSE_SYNC', 'Estados sincronizados correctamente', [
                    'survey_id' => $surveyId,
                    'email' => $tokenData['email'],
                    'notification_id' => $notification->id
                ]);
            });

            return response()->json([
                'success' => true,
                'message' => 'Respuesta guardada exitosamente. ¡Gracias por participar!',
                'data' => [
                    'survey_id' => $surveyId,
                    'submitted_at' => Carbon::now()->toISOString(),
                    'status_updated' => true
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
                ->where('destinatario', $email) // Usar nuevo campo destinatario
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
                    'has_responded' => $notification->state_results === 'true' && !empty($notification->response_data), // Cambiar lógica para string
                    'is_expired' => $isExpired,
                    'survey_status' => $survey ? $survey->status : false,
                    'response_date' => $notification->state_results === 'true' ? $notification->date_insert : null, // Cambiar lógica para string
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

    /**
     * Envía recordatorio automático de encuesta próxima a finalizar
     */
    public function sendReminder(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'to' => 'required|email',
                'subject' => 'required|string|max:255',
                'html_body' => 'required|string',
                'survey_id' => 'required|integer|exists:surveys,id',
                'days_remaining' => 'required|integer|min:1'
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

            if (!$survey->status || $survey->publication_status === 'finished') {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está activa o ya finalizó'
                ], 400);
            }

            // Verificar que la encuesta está próxima a finalizar
            if (!$survey->end_date) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no tiene fecha de finalización definida'
                ], 400);
            }

            $daysUntilEnd = Carbon::now()->diffInDays(Carbon::parse($survey->end_date), false);
            if ($daysUntilEnd > 3 || $daysUntilEnd < 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está en el período de recordatorio (3 días antes de finalizar)'
                ], 400);
            }

            // Verificar si el usuario ya respondió la encuesta
            $hasResponded = NotificationSurvaysModel::where('id_survey', $data['survey_id'])
                ->where('destinatario', $data['to']) // Usar nuevo campo destinatario
                ->where('state_results', 'true') // Cambiar a string
                ->exists();

            if ($hasResponded) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario ya respondió esta encuesta'
                ], 400);
            }

            // Configurar el mailer
            $mailer = config('mail.default');
            if ($mailer === 'log') {
                // En modo log, simular el envío
                \Log::info('Recordatorio de encuesta (simulado)', [
                    'to' => $data['to'],
                    'subject' => $data['subject'],
                    'survey_id' => $data['survey_id'],
                    'days_remaining' => $data['days_remaining'],
                    'html_preview' => substr($data['html_body'], 0, 200) . '...'
                ]);

                $emailSent = true;
            } else {
                // Envío real de email
                try {
                    \Mail::send([], [], function ($message) use ($data) {
                        $message->to($data['to'])
                               ->subject($data['subject'])
                               ->html($data['html_body'])
                               ->from(config('mail.from.address'), config('mail.from.name'));
                    });
                    $emailSent = true;
                } catch (\Exception $mailException) {
                    \Log::error('Error enviando recordatorio por email', [
                        'error' => $mailException->getMessage(),
                        'to' => $data['to'],
                        'survey_id' => $data['survey_id']
                    ]);
                    $emailSent = false;
                }
            }

            if (!$emailSent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error al enviar el recordatorio por email'
                ], 500);
            }

            // Registrar el envío del recordatorio
            NotificationSurvaysModel::create([
                'data' => json_encode([
                    'type' => 'reminder',
                    'days_remaining' => $data['days_remaining'],
                    'sent_at' => Carbon::now(),
                    'survey_title' => $survey->title
                ]),
                'state' => 'sent_reminder',
                'state_results' => 'false',
                'date_insert' => Carbon::now(),
                'id_survey' => $data['survey_id'],
                'destinatario' => $data['to'],
                'asunto' => $data['subject'],
                'body' => $data['html_body'],
                'expired_date' => $survey->end_date,
                'scheduled_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Recordatorio enviado exitosamente',
                'data' => [
                    'sent_to' => $data['to'],
                    'survey_id' => $data['survey_id'],
                    'days_remaining' => $data['days_remaining'],
                    'sent_at' => Carbon::now()->toISOString()
                ]
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Error en sendReminder', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al enviar recordatorio',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generar hash válido para URLs manuales
     */
    public function generateValidHash(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'survey_id' => 'required|integer',
                'email' => 'required|email',
                'type' => 'string|in:standard,fallback,reminder'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parámetros inválidos',
                    'errors' => $validator->errors()
                ], 400);
            }

            $surveyId = $request->input('survey_id');
            $email = $request->input('email');
            $type = $request->input('type', 'standard');

            // Generar hash usando el servicio oficial
            $hash = \App\Services\URLIntegrityService::generateHash($surveyId, $email, $type);

            return response()->json([
                'success' => true,
                'hash' => $hash,
                'survey_id' => $surveyId,
                'email' => $email,
                'type' => $type
            ]);

        } catch (\Exception $e) {
            \Log::error('Error generando hash válido:', [
                'error' => $e->getMessage(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno al generar hash'
            ], 500);
        }
    }
}