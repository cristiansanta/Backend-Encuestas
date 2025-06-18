<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NotificationSurvaysModel;

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
    
}
