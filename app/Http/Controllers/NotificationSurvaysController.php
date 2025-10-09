<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyModel;
use App\Models\SurveyRespondentModel;
use App\Models\GroupModel;
use App\Models\GroupUserModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Services\URLIntegrityService;

class NotificationSurvaysController extends Controller
{

    /**
     * Verificar qué usuarios de una lista ya recibieron una encuesta específica
     * CRÍTICO: Prevenir envíos duplicados en envíos masivos
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkDuplicateNotifications(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'survey_id' => 'required|integer',
                'emails' => 'required|array',
                'emails.*' => 'required|email'
            ]);

            $surveyId = $validatedData['survey_id'];
            $emails = $validatedData['emails'];

            // Buscar notificaciones existentes para esta encuesta y estos emails
            // Verificar CUALQUIER estado válido para detectar duplicados
            $existingNotifications = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->whereIn('destinatario', $emails)
                ->whereIn('state', ['1', 'sent', 'enviado', 'enviada']) // Cualquier estado enviado
                ->get(['destinatario', 'date_insert', 'respondent_name', 'state'])
                ->keyBy('destinatario');

            // Buscar también en SurveyRespondentModel para doble verificación
            $existingRespondents = SurveyRespondentModel::where('survey_id', $surveyId)
                ->whereIn('respondent_email', $emails)
                ->get(['respondent_email', 'sent_at', 'respondent_name', 'status'])
                ->keyBy('respondent_email');

            $result = [
                'already_notified' => [],
                'not_notified' => [],
                'summary' => [
                    'total_emails' => count($emails),
                    'already_sent' => 0,
                    'ready_to_send' => 0
                ]
            ];

            foreach ($emails as $email) {
                $alreadyNotified = $existingNotifications->has($email) || $existingRespondents->has($email);

                if ($alreadyNotified) {
                    $notificationData = $existingNotifications->get($email);
                    $respondentData = $existingRespondents->get($email);

                    $result['already_notified'][] = [
                        'email' => $email,
                        'name' => $notificationData?->respondent_name ?? $respondentData?->respondent_name ?? 'Usuario',
                        'sent_at' => $notificationData?->date_insert ?? $respondentData?->sent_at ?? null,
                        'source' => $notificationData ? 'notification' : 'respondent'
                    ];
                    $result['summary']['already_sent']++;
                } else {
                    $result['not_notified'][] = [
                        'email' => $email,
                        'ready_to_send' => true
                    ];
                    $result['summary']['ready_to_send']++;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => "Verificación completada: {$result['summary']['already_sent']} ya enviados, {$result['summary']['ready_to_send']} listos para enviar"
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al verificar duplicados',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function index(Request $request)
    {
        // Validar los parámetros opcionales id_survey y email
        $validatedData = $request->validate([
            'id_survey' => 'integer|nullable',
            'email' => 'nullable', // Permitir tanto string como array
        ]);
    
        // Construir la consulta en base a los parámetros proporcionados
        $query = NotificationSurvaysModel::query();
    
        if ($request->has('id_survey')) {
            $query->where('id_survey', $validatedData['id_survey']);
        }
    
        if ($request->has('email')) {
            $email = $validatedData['email'];
            // Buscar por el nuevo campo destinatario
            $query->where('destinatario', $email);
        }
    
        // Ejecutar la consulta
        $notificationSurvey = $query->get();
    
        return response()->json([
            'success' => true,
            'data' => $notificationSurvey,
            'count' => $notificationSurvey->count()
        ]);
    }
    

    public function store(Request $request)
    {
        // Validar los datos entrantes
        $validatedData = $request->validate([
            'data' => 'required',
            'state' => 'nullable|string|max:255',
            'state_results' => 'nullable|string|max:255', // Cambiar de boolean a string
            'date_insert' => 'nullable|date',
            'id_survey' => 'nullable|integer',
            'email' => 'required', // Ahora requiere email
            'expired_date' => 'nullable|date',
            'respondent_name' => 'nullable|string|max:255',
            'scheduled_sending' => 'nullable|boolean',
            'scheduled_date' => 'nullable|date',
            'send_immediately' => 'nullable|boolean'
        ]);

        // NUEVA LÓGICA: Solo permitir correos individuales (string)
        // Si viene un array, devolver error
        if (is_array($validatedData['email'])) {
            return response()->json([
                'error' => 'Solo se permiten correos individuales. Por favor envía una solicitud por cada correo electrónico.',
                'message' => 'Los arrays de correos ya no están soportados para mantener la integridad de los datos.'
            ], 422);
        }

        // Validar que el correo es válido
        if (!filter_var($validatedData['email'], FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => "Correo inválido: {$validatedData['email']}"], 422);
        }

        // BUSCAR DATOS COMPLETOS DEL USUARIO EN LA BASE DE DATOS PRIMERO
        $userData = GroupUserModel::where('correo', $validatedData['email'])->first();

        // Extraer información adicional de los datos
        // DEBUG: Log antes y después del JSON decode
        \Log::info('🔧 JSON Decode - Antes:', [
            'data_raw_type' => gettype($validatedData['data']),
            'data_raw_is_string' => is_string($validatedData['data']),
            'data_raw_length' => is_string($validatedData['data']) ? strlen($validatedData['data']) : 'N/A'
        ]);

        $data = is_string($validatedData['data']) ? json_decode($validatedData['data'], true) : $validatedData['data'];

        \Log::info('🔧 JSON Decode - Después:', [
            'data_decoded_type' => gettype($data),
            'data_is_array' => is_array($data),
            'data_keys' => is_array($data) ? array_keys($data) : 'N/A',
            'has_cuerpo_after_decode' => is_array($data) ? isset($data['cuerpo']) : false,
            'cuerpo_length_after_decode' => is_array($data) && isset($data['cuerpo']) ? strlen($data['cuerpo']) : 0,
            'json_last_error' => json_last_error(),
            'json_last_error_msg' => json_last_error_msg()
        ]);

        $groupName = $data['grupo'] ?? null;
        $respondentName = $validatedData['respondent_name'] ?? ($userData ? $userData->nombre : null) ?? $data['nombre'] ?? $this->extractNameFromEmail($validatedData['email']);

        // DEBUG: Log para verificar que los datos incluyen asunto y cuerpo
        \Log::info('NotificationSurvaysController - Datos recibidos:', [
            'email' => $validatedData['email'],
            'data_keys' => array_keys($data),
            'has_asunto' => isset($data['asunto']),
            'has_cuerpo' => isset($data['cuerpo']),
            'asunto_length' => isset($data['asunto']) ? strlen($data['asunto']) : 0,
            'cuerpo_length' => isset($data['cuerpo']) ? strlen($data['cuerpo']) : 0
        ]);

        // VERIFICAR DUPLICADOS: Evitar registros duplicados en NotificationSurvaysModel
        // Verificar CUALQUIER estado (1, sent, etc.) para la misma combinación survey+email
        $existingNotification = NotificationSurvaysModel::where('id_survey', $validatedData['id_survey'])
            ->where('destinatario', $validatedData['email'])
            ->whereIn('state', ['1', 'sent', 'enviado', 'enviada'])
            ->first();

        if ($existingNotification) {
            return response()->json([
                'success' => false,
                'message' => 'Esta encuesta ya fue enviada a este correo electrónico',
                'existing_notification' => [
                    'id' => $existingNotification->id,
                    'sent_at' => $existingNotification->date_insert,
                    'survey_id' => $existingNotification->id_survey,
                    'email' => $existingNotification->destinatario
                ]
            ], 409); // Conflict
        }

        // Separar información del correo de metadatos
        $asunto = $data['asunto'] ?? 'Invitación a Encuesta';
        $body = $data['cuerpo'] ?? '';

        // VALIDACIÓN CRÍTICA: Verificar que el body no esté vacío
        if (empty($body) || strlen(trim($body)) < 50) {
            \Log::error('🚨 BODY VACÍO DETECTADO - Rechazando inserción:', [
                'email' => $validatedData['email'],
                'survey_id' => $validatedData['id_survey'],
                'body_length' => strlen($body),
                'body_content' => substr($body, 0, 200),
                'full_data' => $data
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error: El contenido del correo está vacío. No se puede enviar una notificación sin contenido.',
                'error_type' => 'EMPTY_BODY_VALIDATION',
                'debug_info' => [
                    'body_length' => strlen($body),
                    'has_cuerpo_field' => isset($data['cuerpo']),
                    'available_fields' => array_keys($data)
                ]
            ], 400);
        }

        // DEBUG: Log específico para verificar asignación de variables
        \Log::info('🔧 Variables antes de insertar (BODY VÁLIDO):', [
            'asunto_var' => $asunto,
            'asunto_length' => strlen($asunto),
            'body_var' => substr($body, 0, 100) . '...', // Primeros 100 caracteres
            'body_full_length' => strlen($body),
            'body_is_empty' => empty($body),
            'body_type' => gettype($body)
        ]);

        // Preparar metadatos optimizados (sin HTML ni redundancias)
        $optimizedData = [
            'survey_id' => $validatedData['id_survey'],
            'type' => 'mass_email_notification',
            'metadata' => [
                'grupo' => $data['grupo'] ?? ($userData ? $userData->group->name ?? null : null),
                'regional' => $userData ? $userData->regional : ($data['regional'] ?? null),
                'tipoDocumento' => $userData ? $userData->tipo_documento : null,
                'numeroDocumento' => $userData ? $userData->numero_documento : null,
                'centroFormacion' => $userData ? $userData->centro_formacion : null,
                'programaFormacion' => $userData ? $userData->programa_formacion : null,
                'fichaGrupo' => $userData ? $userData->ficha_grupo : null,
                'tipoCaracterizacion' => $userData ? $userData->tipo_caracterizacion : null
            ],
            'scheduling' => [
                'scheduled_sending' => $validatedData['scheduled_sending'] ?? false,
                'scheduled_date' => ($validatedData['scheduled_sending'] ?? false)
                    ? ($validatedData['scheduled_date'] ?? null)
                    : now(), // Asignar fecha actual para envío inmediato
                'send_immediately' => $validatedData['send_immediately'] ?? true
            ],
            'sent_at' => now()
        ];

        // Preparar scheduled_at
        $scheduledAt = null;
        if ($validatedData['scheduled_sending'] ?? false) {
            $scheduledAt = $validatedData['scheduled_date'] ?? null;
        } else {
            $scheduledAt = now(); // Envío inmediato
        }

        // DEBUG: Log datos que van a la base de datos
        $dataToInsert = [
            'data' => json_encode($optimizedData),
            'state' => $validatedData['state'] ?? '1',
            'state_results' => $validatedData['state_results'] ?? 'false',
            'date_insert' => now(),
            'id_survey' => (int)$validatedData['id_survey'],
            'destinatario' => (string)$validatedData['email'], // Usar nuevo campo
            'asunto' => $asunto, // Separar asunto
            'body' => $body, // Separar cuerpo
            'expired_date' => $validatedData['expired_date'] ?? now()->addDays(30),
            'respondent_name' => $respondentName,
            'scheduled_sending' => $validatedData['scheduled_sending'] ?? false,
            'scheduled_date' => ($validatedData['scheduled_sending'] ?? false)
                ? ($validatedData['scheduled_date'] ?? null)
                : now(), // Asignar fecha actual para envío inmediato
            'send_immediately' => $validatedData['send_immediately'] ?? true,
            'scheduled_at' => $scheduledAt
        ];

        \Log::info('📝 Datos preparados para insertar en DB:', [
            'asunto_db' => $dataToInsert['asunto'],
            'body_db_length' => strlen($dataToInsert['body']),
            'body_db_sample' => substr($dataToInsert['body'], 0, 100) . '...',
            'destinatario' => $dataToInsert['destinatario'],
            'id_survey' => $dataToInsert['id_survey']
        ]);

        // CRÍTICO: Limpiar tokens bloqueados anteriores para este survey+email
        // Esto permite que los nuevos enlaces funcionen sin bloqueos previos
        URLIntegrityService::resetAccessTokensForResend($validatedData['id_survey'], $validatedData['email']);

        \Log::info('🔄 Access tokens cleaned before creating notification', [
            'survey_id' => $validatedData['id_survey'],
            'email' => $validatedData['email']
        ]);

        // Crear registro con nueva estructura optimizada
        $record = NotificationSurvaysModel::create($dataToInsert);

        // DEBUG: Verificar que el registro se creó correctamente
        \Log::info('✅ Registro creado - Verificación:', [
            'record_id' => $record->id,
            'record_body_length' => strlen($record->body ?? ''),
            'record_asunto' => $record->asunto,
            'record_destinatario' => $record->destinatario
        ]);

        // Crear registro de respondiente en estado "Enviada"
        if ($validatedData['id_survey'] && $validatedData['email']) {
            $groupId = null;
            $groupName = $optimizedData['metadata']['grupo'] ?? null;

            // Intentar encontrar el grupo por nombre
            if ($groupName) {
                $group = GroupModel::where('name', $groupName)->first();
                $groupId = $group ? $group->id : null;
            }

            // Verificar si ya existe un registro para esta combinación
            $existingRespondent = SurveyRespondentModel::where('survey_id', $validatedData['id_survey'])
                ->where('respondent_email', $validatedData['email'])
                ->first();

            if (!$existingRespondent) {
                // Crear token único para el correo
                $emailToken = Str::random(64);

                SurveyRespondentModel::create([
                    'survey_id' => $validatedData['id_survey'],
                    'respondent_name' => $respondentName,
                    'respondent_email' => $validatedData['email'],
                    'status' => 'Enviada',
                    'sent_at' => $scheduledAt, // Usar scheduled_at calculado
                    'notification_id' => $record->id,
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                    'email_token' => $emailToken
                ]);
            }
        }

        // Retornar la respuesta en JSON
        return response()->json([
            'success' => true,
            'message' => 'Notificación individual creada exitosamente',
            'data' => $record,
            'email_count' => 1,
            'respondent_name' => $respondentName,
            'group_name' => $groupName
        ], 201);
    }

    public function update(Request $request)
    {
        // Validar los datos entrantes
        $validatedData = $request->validate([
            'id_survey' => 'required|integer', // Asegúrate de incluir el id_survey en la solicitud
            'email' => 'required|email',       // Asegúrate de incluir el email en la solicitud
            'state_results' => 'required|string|max:255', // Cambiar de boolean a string
        ]);
    
        // Buscar el registro por id_survey y destinatario (nuevo campo)
        $record = NotificationSurvaysModel::where('id_survey', $validatedData['id_survey'])
                    ->where('destinatario', $validatedData['email'])
                    ->first();
    
        // Verificar si el registro existe
        if (!$record) {
            return response()->json(['error' => 'Registro no encontrado'], 404);
        }
    
        // Actualizar el campo 'state_results' en el registro
        $record->state_results = $validatedData['state_results'];
        $record->save();
    
        // Retornar la respuesta en JSON
        return response()->json(['message' => 'Registro actualizado con éxito', 'data' => $record], 200);
    }

    public function download()
    {
        // Ruta al archivo
        $filePath = storage_path('app/public/Formato_correos_notificacion.xlsx');
        
        // Verifica si el archivo existe
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Archivo no encontrado'], 404);
        }

        // Retorna el archivo como descarga
        return response()->download($filePath, 'Formato_correos_notificacion.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0'
        ]);
    }

    public function downloadRespondentsTemplate()
    {
        try {
            // Crear contenido CSV con las columnas requeridas
            $csvContent = "TipoDocumento,NumeroDocumento,Nombre,Correo,Regional,CentroFormacion,ProgramaFormacion,FichaGrupo,TipoCaracterizacion\n";
            $csvContent .= "CC,12345678,Juan Pérez,juan.perez@ejemplo.com,Antioquia,Centro Industrial,Tecnología en Sistemas,123456,Egresados\n";
            $csvContent .= "TI,87654321,María López,maria.lopez@ejemplo.com,Cundinamarca,Centro de Servicios,Administración,654321,Servidores\n";
            
            // Crear archivo temporal
            $tempPath = tempnam(sys_get_temp_dir(), 'plantilla_encuestados_');
            file_put_contents($tempPath, $csvContent);
            
            // Crear respuesta de descarga
            $response = response()->download($tempPath, 'Plantilla_Encuestados.csv', [
                'Content-Type' => 'text/csv',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
            
            // Eliminar archivo temporal después de la descarga
            register_shutdown_function(function() use ($tempPath) {
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            });
            
            return $response;
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al generar la plantilla',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera enlaces de encuesta con tokens JWT para envío masivo por correo
     */
    public function generateSurveyEmailLinks(Request $request)
    {
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'survey_id' => 'required|integer|exists:surveys,id',
                'emails' => 'required|array|min:1',
                'emails.*.email' => 'required|email',
                'emails.*.name' => 'nullable|string|max:255'
            ]);

            $surveyId = $validatedData['survey_id'];
            $emails = $validatedData['emails'];

            // Verificar que la encuesta existe y está activa
            $survey = SurveyModel::findOrFail($surveyId);
            
            if (!$survey->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está activa'
                ], 400);
            }

            $generatedLinks = [];
            $errors = [];

            foreach ($emails as $emailData) {
                try {
                    // BUSCAR DATOS COMPLETOS DEL USUARIO EN LA BASE DE DATOS
                    $userData = GroupUserModel::where('correo', $emailData['email'])->first();

                    // Crear un token único para esta combinación de encuesta y email
                    $tokenData = [
                        'survey_id' => $surveyId,
                        'email' => $emailData['email'],
                        'respondent_name' => $userData ? $userData->nombre : ($emailData['name'] ?? null),
                        'issued_at' => Carbon::now()->timestamp,
                        'expires_at' => $survey->end_date ? $survey->end_date->timestamp : Carbon::now()->addDays(30)->timestamp,
                        'unique_id' => Str::uuid()
                    ];

                    // Cifrar el token con Laravel Crypt
                    $encryptedToken = Crypt::encrypt($tokenData);

                    // Log debug para identificar problemas de tipos de datos
                    \Log::info('🔍 NotificationSurvaysController - Datos que se van a insertar:', [
                        'survey_id' => $surveyId,
                        'survey_id_type' => gettype($surveyId),
                        'email' => $emailData['email'],
                        'email_type' => gettype($emailData['email']),
                        'respondent_name' => $emailData['name'] ?? 'null',
                        'respondent_name_type' => gettype($emailData['name'] ?? null),
                        'survey_end_date' => $survey->end_date,
                        'survey_end_date_type' => gettype($survey->end_date)
                    ]);

                    // PERMITIR REENVÍOS: Crear nuevo registro siempre
                    $notification = NotificationSurvaysModel::create([
                        'data' => json_encode([
                            'survey_id' => (int)$surveyId,
                            'type' => 'mass_email_survey_access',
                            'metadata' => [
                                'grupo' => $userData ? ($userData->group->name ?? null) : null,
                                'regional' => $userData ? $userData->regional : null,
                                'tipoDocumento' => $userData ? $userData->tipo_documento : null,
                                'numeroDocumento' => $userData ? $userData->numero_documento : null,
                                'centroFormacion' => $userData ? $userData->centro_formacion : null,
                                'programaFormacion' => $userData ? $userData->programa_formacion : null,
                                'fichaGrupo' => $userData ? $userData->ficha_grupo : null,
                                'tipoCaracterizacion' => $userData ? $userData->tipo_caracterizacion : null
                            ],
                            'token_issued_at' => Carbon::now()->toISOString(),
                            'unique_token' => $tokenData['unique_id']
                        ]),
                        'state' => 'pending_response',
                        'state_results' => 'false',
                        'date_insert' => Carbon::now(),
                        'id_survey' => (int)$surveyId,
                        'destinatario' => (string)$emailData['email'], // Usar nuevo campo destinatario
                        'expired_date' => $survey->end_date ? Carbon::parse($survey->end_date) : Carbon::now()->addDays(30),
                        'respondent_name' => $userData ? $userData->nombre : ($emailData['name'] ?? null)
                    ]);

                    // SINCRONIZAR: Crear registro en survey_respondents para seguimiento
                    $existingRespondent = SurveyRespondentModel::where('survey_id', $surveyId)
                        ->where('respondent_email', $emailData['email'])
                        ->first();

                    if (!$existingRespondent) {
                        // Crear token único para el correo
                        $emailToken = Str::random(64);

                        SurveyRespondentModel::create([
                            'survey_id' => $surveyId,
                            'respondent_name' => $userData ? $userData->nombre : ($emailData['name'] ?? $this->extractNameFromEmail($emailData['email'])),
                            'respondent_email' => $emailData['email'],
                            'status' => 'Enviada',
                            'sent_at' => now(),
                            'notification_id' => $notification->id,
                            'email_token' => $emailToken
                        ]);
                    }

                    // Generar la URL de la encuesta
                    $surveyUrl = config('app.frontend_url', 'http://149.130.180.163:5173') .
                                '/encuestados/survey-view-manual/' . $surveyId .
                                '?token=' . urlencode($encryptedToken);

                    $generatedLinks[] = [
                        'email' => $emailData['email'],
                        'name' => $emailData['name'],
                        'survey_url' => $surveyUrl,
                        'encrypted_token' => $encryptedToken,
                        'notification_id' => $notification->id,
                        'expires_at' => $survey->end_date ?? Carbon::now()->addDays(30)
                    ];

                } catch (\Exception $e) {
                    $errors[] = [
                        'email' => $emailData['email'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Enlaces de encuesta generados exitosamente',
                'data' => [
                    'survey_id' => $surveyId,
                    'survey_title' => $survey->title,
                    'generated_links' => $generatedLinks,
                    'total_generated' => count($generatedLinks),
                    'errors' => $errors,
                    'total_errors' => count($errors)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar enlaces de encuesta',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene el estado de las notificaciones de una encuesta específica
     */
    public function getSurveyNotificationStatus(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'survey_id' => 'required|integer|exists:surveys,id'
            ]);

            $surveyId = $validatedData['survey_id'];

            // Obtener todas las notificaciones de la encuesta
            $notifications = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->with('survey')
                ->get();

            $statusSummary = [
                'total_notifications' => $notifications->count(),
                'pending_responses' => 0,
                'completed_responses' => 0,
                'expired_links' => 0,
                'notifications' => []
            ];

            foreach ($notifications as $notification) {
                $isExpired = $notification->expired_date && Carbon::now()->isAfter($notification->expired_date);
                $hasResponded = $notification->state_results === 'true' && !empty($notification->response_data);
                
                if ($isExpired) {
                    $statusSummary['expired_links']++;
                } elseif ($hasResponded) {
                    $statusSummary['completed_responses']++;
                } else {
                    $statusSummary['pending_responses']++;
                }

                $statusSummary['notifications'][] = [
                    'id' => $notification->id,
                    'emails' => $notification->destinatario,
                    'respondent_name' => $notification->respondent_name,
                    'has_responded' => $hasResponded,
                    'is_expired' => $isExpired,
                    'response_date' => $hasResponded ? $notification->date_insert : null,
                    'expired_date' => $notification->expired_date,
                    'state' => $notification->state
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $statusSummary
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estado de notificaciones',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reenviar encuesta a un usuario específico
     */
    public function resendSurveyToUser(Request $request)
    {
        try {
            \Log::info('🔄 RESEND SURVEY: Method called', [
                'request_data' => $request->all(),
                'method' => $request->method(),
                'url' => $request->url()
            ]);

            $validatedData = $request->validate([
                'survey_id' => 'required|integer|exists:surveys,id',
                'email' => 'required|email'
            ]);

            \Log::info('🔄 RESEND SURVEY: Validation passed', [
                'validated_data' => $validatedData
            ]);

            $surveyId = $validatedData['survey_id'];
            $email = $validatedData['email'];

            // Verificar que la encuesta existe y está activa
            $survey = SurveyModel::findOrFail($surveyId);

            \Log::info('🔄 RESEND SURVEY: Survey found', [
                'survey_id' => $survey->id,
                'survey_title' => $survey->title,
                'survey_status' => $survey->status
            ]);

            if (!$survey->status) {
                \Log::warning('🔄 RESEND SURVEY: Survey not active', [
                    'survey_id' => $surveyId,
                    'status' => $survey->status
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'La encuesta no está activa'
                ], 400);
            }

            // Verificar que el usuario no ha respondido ya
            $existingNotification = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->where('destinatario', $email)
                ->where('state_results', 'true')
                ->first();

            if ($existingNotification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este usuario ya ha respondido la encuesta'
                ], 400);
            }

            // BUSCAR DATOS COMPLETOS DEL USUARIO EN LA BASE DE DATOS
            $userData = GroupUserModel::where('correo', $email)->first();

            // Si no se encuentra en group_users, buscar en survey_respondents para obtener el nombre correcto
            if (!$userData) {
                $surveyRespondent = SurveyRespondentModel::where('respondent_email', $email)
                    ->where('survey_id', $surveyId)
                    ->first();
                $respondentName = $surveyRespondent ? $surveyRespondent->respondent_name : $this->extractNameFromEmail($email);

                \Log::info('🔄 RESEND SURVEY: Name resolution from survey_respondents', [
                    'email' => $email,
                    'survey_id' => $surveyId,
                    'respondent_found' => $surveyRespondent ? true : false,
                    'respondent_name' => $respondentName,
                    'source' => $surveyRespondent ? 'survey_respondents' : 'extractNameFromEmail'
                ]);
            } else {
                $respondentName = $userData->nombre;
                \Log::info('🔄 RESEND SURVEY: Name resolution from group_users', [
                    'email' => $email,
                    'respondent_name' => $respondentName,
                    'source' => 'group_users'
                ]);
            }

            // Crear un nuevo token único para esta combinación de encuesta y email
            $tokenData = [
                'survey_id' => $surveyId,
                'email' => $email,
                'respondent_name' => $respondentName,
                'issued_at' => Carbon::now()->timestamp,
                'expires_at' => $survey->end_date ? $survey->end_date->timestamp : Carbon::now()->addDays(30)->timestamp,
                'unique_id' => Str::uuid()
            ];

            // Cifrar el token con Laravel Crypt
            $encryptedToken = Crypt::encrypt($tokenData);

            // CARGAR DATOS DE EMAIL: Buscar una notificación existente de esta encuesta para obtener asunto y cuerpo
            $existingEmailNotification = NotificationSurvaysModel::where('id_survey', $surveyId)
                ->whereNotNull('asunto')
                ->whereNotNull('body')
                ->where('body', '!=', '')
                ->first();

            // Preparar los datos de email para la validación posterior
            $data = [];
            if ($existingEmailNotification) {
                $data['asunto'] = $existingEmailNotification->asunto;
                $data['cuerpo'] = $existingEmailNotification->body;

                // CRÍTICO: Reemplazar nombres hardcodeados en el cuerpo del email con el nombre correcto
                if ($data['cuerpo'] && $respondentName) {
                    // Lista de nombres comunes que pueden estar hardcodeados
                    $hardcodedNames = ['Ana Gómez', 'Ana Gomez', 'Ana García', 'María González', 'Juan Pérez'];

                    foreach ($hardcodedNames as $hardcodedName) {
                        if (strpos($data['cuerpo'], $hardcodedName) !== false) {
                            $data['cuerpo'] = str_replace($hardcodedName, $respondentName, $data['cuerpo']);
                            \Log::info('✅ RESEND: Replaced hardcoded name in email body', [
                                'hardcoded_name' => $hardcodedName,
                                'correct_name' => $respondentName,
                                'email' => $email
                            ]);
                        }
                    }
                }

                // CRÍTICO: Reemplazar URLs hardcodeadas con email incorrecto por el email correcto
                if ($data['cuerpo'] && $email) {
                    // Buscar y reemplazar URLs con emails incorrectos
                    $hardcodedEmails = ['ana.gomez@ejemplo.com', 'ana.gomez%40ejemplo.com'];
                    $correctEncodedEmail = urlencode($email);

                    foreach ($hardcodedEmails as $hardcodedEmail) {
                        if (strpos($data['cuerpo'], $hardcodedEmail) !== false) {
                            $data['cuerpo'] = str_replace($hardcodedEmail, $correctEncodedEmail, $data['cuerpo']);
                            \Log::info('✅ RESEND: Replaced hardcoded email in URL', [
                                'hardcoded_email' => $hardcodedEmail,
                                'correct_email' => $correctEncodedEmail,
                                'recipient' => $email
                            ]);
                        }
                    }

                    // CRÍTICO: Generar hash ÚNICO para cada reenvío con timestamp
                    // Esto permite que cada enlace tenga su propio registro independiente
                    // Hash viejo bloqueado → Permanece bloqueado
                    // Hash nuevo → Funciona sin restricciones

                    // Formato: base64(surveyId-email-timestamp_corto)
                    $timestamp = substr((string)time(), -6); // Últimos 6 dígitos del timestamp
                    $baseHashData = $surveyId . '-' . $email . '-' . $timestamp;
                    $newHash = base64_encode($baseHashData);
                    $newHash = str_replace(['+', '/', '='], ['', '', ''], $newHash); // URL-safe

                    \Log::info('🔄 RESEND: Generated unique timestamped hash', [
                        'survey_id' => $surveyId,
                        'email' => $email,
                        'timestamp' => $timestamp,
                        'hash_data' => $baseHashData,
                        'generated_hash' => $newHash,
                        'note' => 'Each resend gets unique hash to maintain independent block status'
                    ]);

                    // Reemplazar cualquier hash anterior en el cuerpo del email
                    $oldHashes = [
                        base64_encode($surveyId . '-ana.gomez@ejemplo.com'),
                        base64_encode($surveyId . '-' . $email), // Hash original sin timestamp
                    ];

                    foreach ($oldHashes as $oldHash) {
                        $oldHash = str_replace(['+', '/', '='], ['', '', ''], $oldHash);
                        if (strpos($data['cuerpo'], $oldHash) !== false) {
                            $data['cuerpo'] = str_replace($oldHash, $newHash, $data['cuerpo']);
                            \Log::info('✅ RESEND: Replaced hash in URL', [
                                'old_hash' => $oldHash,
                                'new_unique_hash' => $newHash,
                                'recipient' => $email,
                                'security_note' => 'New unique hash generated to avoid blocked hash conflicts'
                            ]);
                        }
                    }
                }

                \Log::info('🔄 RESEND SURVEY: Email data loaded from existing notification', [
                    'asunto' => $data['asunto'],
                    'cuerpo_length' => strlen($data['cuerpo']),
                    'source_notification_id' => $existingEmailNotification->id,
                    'recipient_name' => $respondentName
                ]);
            } else {
                // Usar valores por defecto si no hay notificación previa
                $data['asunto'] = 'Invitación a Encuesta - ' . $survey->title;
                $data['cuerpo'] = "Estimado/a participante,\n\nHa sido invitado/a a participar en la encuesta: {$survey->title}\n\nPor favor, haga clic en el enlace que se enviará a su correo electrónico para acceder a la encuesta.\n\nGracias por su participación.";
                \Log::info('🔄 RESEND SURVEY: Using default email data', [
                    'asunto' => $data['asunto'],
                    'cuerpo_length' => strlen($data['cuerpo'])
                ]);
            }

            // CRÍTICO: Limpiar tokens de acceso anteriores para permitir el nuevo hash
            URLIntegrityService::resetAccessTokensForResend($surveyId, $email);

            \Log::info('🔄 RESEND SURVEY: Proceeding to notification creation...', [
                'data_keys' => array_keys($data),
                'survey_id' => $surveyId,
                'email' => $email
            ]);

            // Actualizar o crear notificación para el reenvío
            try {
                \Log::info('🔄 RESEND SURVEY: About to update/create notification...', [
                    'survey_id' => $surveyId,
                    'email' => $email,
                    'respondent_name' => $respondentName
                ]);

                // Buscar notificación existente
                $notification = NotificationSurvaysModel::where('id_survey', $surveyId)
                    ->where('destinatario', $email)
                    ->first();

                $notificationData = [
                    'data' => json_encode([
                        'survey_id' => (int)$surveyId,
                        'type' => 'resend_email_survey_access',
                        'metadata' => [
                            'grupo' => $userData ? ($userData->group->name ?? null) : null,
                            'regional' => $userData ? $userData->regional : null,
                            'tipoDocumento' => $userData ? $userData->tipo_documento : null,
                            'numeroDocumento' => $userData ? $userData->numero_documento : null,
                            'centroFormacion' => $userData ? $userData->centro_formacion : null,
                            'programaFormacion' => $userData ? $userData->programa_formacion : null,
                            'fichaGrupo' => $userData ? $userData->ficha_grupo : null,
                            'tipoCaracterizacion' => $userData ? $userData->tipo_caracterizacion : null
                        ],
                        'token_issued_at' => Carbon::now()->toISOString(),
                        'unique_token' => $tokenData['unique_id'],
                        'resend_action' => true,
                        'resent_at' => Carbon::now()->toISOString()
                    ]),
                    'state' => 'pending_response',
                    'state_results' => 'false',
                    'date_insert' => Carbon::now(),
                    'id_survey' => (int)$surveyId,
                    'destinatario' => (string)$email,
                    'asunto' => $data['asunto'],
                    'body' => $data['cuerpo'],
                    'expired_date' => $survey->end_date ? Carbon::parse($survey->end_date) : Carbon::now()->addDays(30),
                    'respondent_name' => $respondentName,
                    'estado' => 'pendiente', // Cambiar a pendiente para que el servicio de correos lo procese
                    'sent_at' => null, // Resetear fecha de envío
                    'retry_count' => 0, // Resetear contador de reintentos
                    'last_error' => null // Limpiar último error
                ];

                if ($notification) {
                    // Actualizar notificación existente
                    $notification->update($notificationData);
                    \Log::info('🔄 RESEND SURVEY: Existing notification updated successfully', [
                        'notification_id' => $notification->id,
                        'survey_id' => $surveyId,
                        'email' => $email,
                        'estado_changed_to' => 'pendiente',
                        'sent_at_reset' => 'null',
                        'ready_for_email_service' => true
                    ]);
                } else {
                    // Crear nueva notificación si no existe
                    $notification = NotificationSurvaysModel::create($notificationData);
                    \Log::info('🔄 RESEND SURVEY: New notification created successfully', [
                        'notification_id' => $notification->id,
                        'survey_id' => $surveyId,
                        'email' => $email,
                        'estado_set_to' => 'pendiente',
                        'ready_for_email_service' => true
                    ]);
                }

                // NO pre-registrar el token aquí
                // Razón: El sistema validateDeviceAccess() creará el token automáticamente
                // cuando el usuario REAL acceda al enlace con su dispositivo REAL.
                // Pre-registrar con device_fingerprint falso causa que se bloquee el enlace nuevo.
                \Log::info('✅ RESEND: New hash will register on first user access', [
                    'survey_id' => $surveyId,
                    'email' => $email,
                    'security_note' => 'Token will be created when user opens link with real device'
                ]);
            } catch (\Exception $e) {
                \Log::error('🔄 RESEND SURVEY: Error updating/creating notification', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'survey_id' => $surveyId,
                    'email' => $email
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar la notificación de reenvío: ' . $e->getMessage()
                ], 500);
            }

            \Log::info('🔄 RESEND SURVEY: Notification update completed, moving to survey respondents...', [
                'notification_id' => $notification->id,
                'survey_id' => $surveyId,
                'email' => $email
            ]);

            // Actualizar el registro en survey_respondents
            try {
                \Log::info('🔄 RESEND SURVEY: About to update survey respondents...', [
                    'survey_id' => $surveyId,
                    'email' => $email
                ]);

            $existingRespondent = SurveyRespondentModel::where('survey_id', $surveyId)
                ->where('respondent_email', $email)
                ->first();

            if ($existingRespondent) {
                // Actualizar registro existente
                $existingRespondent->update([
                    'status' => 'Enviada',
                    'sent_at' => now(),
                    'notification_id' => $notification->id
                ]);
                \Log::info('🔄 RESEND SURVEY: Survey respondent updated successfully', [
                    'respondent_id' => $existingRespondent->id
                ]);
            } else {
                // Crear nuevo registro si no existe
                $emailToken = Str::random(64);

                $newRespondent = SurveyRespondentModel::create([
                    'survey_id' => $surveyId,
                    'respondent_name' => $respondentName,
                    'respondent_email' => $email,
                    'status' => 'Enviada',
                    'sent_at' => now(),
                    'notification_id' => $notification->id,
                    'email_token' => $emailToken
                ]);
                \Log::info('🔄 RESEND SURVEY: New survey respondent created successfully', [
                    'respondent_id' => $newRespondent->id
                ]);
            }

            } catch (\Exception $e) {
                \Log::error('🔄 RESEND SURVEY: Error updating/creating survey respondent', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'survey_id' => $surveyId,
                    'email' => $email
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Error al actualizar el registro de encuestado: ' . $e->getMessage()
                ], 500);
            }

            // Generar la URL de la encuesta
            $surveyUrl = config('app.frontend_url', 'http://149.130.180.163:5173') .
                        '/encuestados/survey-view-manual/' . $surveyId .
                        '?token=' . urlencode($encryptedToken);

            \Log::info('🔄 RESEND SURVEY: Method completed successfully', [
                'email' => $email,
                'notification_id' => $notification->id,
                'survey_url' => $surveyUrl
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Encuesta reenviada exitosamente',
                'data' => [
                    'email' => $email,
                    'respondent_name' => $respondentName,
                    'survey_url' => $surveyUrl,
                    'notification_id' => $notification->id,
                    'expires_at' => $survey->end_date ?? Carbon::now()->addDays(30),
                    'resent_at' => Carbon::now()->toISOString()
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al reenviar la encuesta',
                'error' => $e->getMessage()
            ], 500);
        }
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

}
