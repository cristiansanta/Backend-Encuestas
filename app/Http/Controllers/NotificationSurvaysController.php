<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Carbon\Carbon;

class NotificationSurvaysController extends Controller
{

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
            if (is_array($email)) {
                // Buscar registros que contengan cualquiera de los correos en el array
                $query->where(function($q) use ($email) {
                    foreach ($email as $singleEmail) {
                        $q->orWhereJsonContains('email', $singleEmail);
                    }
                });
            } else {
                // Búsqueda tradicional para un solo correo
                $query->where(function($q) use ($email) {
                    $q->where('email', $email)
                      ->orWhereJsonContains('email', $email);
                });
            }
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
            'state_results' => 'nullable|boolean',
            'date_insert' => 'nullable|date',
            'id_survey' => 'nullable|integer',
            'email' => 'nullable', // Permitir tanto string como array
            'expired_date' => 'nullable|date'
        ]);

        // Si email es un array, validar cada correo individualmente
        if (is_array($validatedData['email'])) {
            foreach ($validatedData['email'] as $email) {
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return response()->json(['error' => "Correo inválido: {$email}"], 422);
                }
            }
        } elseif ($validatedData['email'] && !filter_var($validatedData['email'], FILTER_VALIDATE_EMAIL)) {
            return response()->json(['error' => "Correo inválido: {$validatedData['email']}"], 422);
        }

        // Crear un nuevo registro en la base de datos
        $record = NotificationSurvaysModel::create($validatedData);

        // Retornar la respuesta en JSON
        return response()->json([
            'success' => true,
            'message' => is_array($validatedData['email']) 
                ? 'Notificación grupal creada exitosamente con ' . count($validatedData['email']) . ' correos'
                : 'Notificación individual creada exitosamente',
            'data' => $record,
            'email_count' => is_array($validatedData['email']) ? count($validatedData['email']) : 1
        ], 201);
    }

    public function update(Request $request)
    {
        // Validar los datos entrantes
        $validatedData = $request->validate([
            'id_survey' => 'required|integer', // Asegúrate de incluir el id_survey en la solicitud
            'email' => 'required|email',       // Asegúrate de incluir el email en la solicitud
            'state_results' => 'required|boolean', // El nuevo estado para el campo state_results
        ]);
    
        // Buscar el registro por id_survey y email
        $record = NotificationSurvaysModel::where('id_survey', $validatedData['id_survey'])
                    ->where('email', $validatedData['email'])
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
                    // Crear un token único para esta combinación de encuesta y email
                    $tokenData = [
                        'survey_id' => $surveyId,
                        'email' => $emailData['email'],
                        'respondent_name' => $emailData['name'] ?? null,
                        'issued_at' => Carbon::now()->timestamp,
                        'expires_at' => $survey->end_date ? $survey->end_date->timestamp : Carbon::now()->addDays(30)->timestamp,
                        'unique_id' => Str::uuid()
                    ];

                    // Cifrar el token con Laravel Crypt
                    $encryptedToken = Crypt::encrypt($tokenData);

                    // Crear o actualizar el registro de notificación
                    $notification = NotificationSurvaysModel::updateOrCreate(
                        [
                            'id_survey' => $surveyId,
                            'email' => json_encode([$emailData['email']])
                        ],
                        [
                            'data' => json_encode([
                                'survey_id' => $surveyId,
                                'respondent_name' => $emailData['name'],
                                'type' => 'mass_email_survey_access',
                                'token_issued_at' => Carbon::now(),
                                'unique_token' => $tokenData['unique_id']
                            ]),
                            'state' => 'pending_response',
                            'state_results' => false,
                            'date_insert' => Carbon::now(),
                            'expired_date' => $survey->end_date ?? Carbon::now()->addDays(30),
                            'respondent_name' => $emailData['name']
                        ]
                    );

                    // Generar la URL de la encuesta
                    $surveyUrl = config('app.frontend_url', 'http://localhost:3000') . 
                                '/survey-view-manual/' . $surveyId . 
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
                $hasResponded = $notification->state_results && !empty($notification->response_data);
                
                if ($isExpired) {
                    $statusSummary['expired_links']++;
                } elseif ($hasResponded) {
                    $statusSummary['completed_responses']++;
                } else {
                    $statusSummary['pending_responses']++;
                }

                $statusSummary['notifications'][] = [
                    'id' => $notification->id,
                    'emails' => $notification->email,
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
    
}
