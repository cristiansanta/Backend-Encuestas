<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SurveyRespondentModel;
use App\Models\SurveyModel;
use Illuminate\Support\Facades\Log;

class SurveyRespondentController extends Controller
{
    /**
     * Obtener todos los respondientes de una encuesta
     */
    public function getBySurvey($surveyId)
    {
        try {
            $respondents = SurveyRespondentModel::where('survey_id', $surveyId)
                ->with(['survey', 'group'])
                ->orderBy('sent_at', 'desc')
                ->get();

            // CORREGIDO: Obtener el estado actualizado desde notificationsurvays
            $respondents = $respondents->map(function ($respondent) {
                // Buscar el estado actual en la tabla notificationsurvays
                $notification = \App\Models\NotificationSurvaysModel::where('id_survey', $respondent->survey_id)
                    ->where('destinatario', $respondent->respondent_email)
                    ->first();

                if ($notification) {
                    // Determinar el estado basado en el estado de la notificación y habilitación
                    if (!$notification->enabled) {
                        // Si está deshabilitado, el estado es Inválida
                        $respondent->status = 'Inválida';
                    } elseif ($notification->state === 'completed') {
                        $respondent->status = 'Contestada';
                    } elseif ($notification->state === '1') {
                        $respondent->status = 'Enviada';
                    }

                    // Agregar el estado anterior para referencia
                    $respondent->previous_status = $notification->previous_status;

                    // Agregar el estado de habilitación
                    $respondent->enabled = $notification->enabled;

                    // Log para debugging (temporalmente deshabilitado)
                    // \Log::info('🔍 Updated respondent status', [
                    //     'email' => $respondent->respondent_email,
                    //     'notification_state' => $notification->state,
                    //     'enabled' => $notification->enabled,
                    //     'final_status' => $respondent->status
                    // ]);
                }

                return $respondent;
            });

            return response()->json([
                'success' => true,
                'data' => $respondents,
                'total' => $respondents->count()
            ]);
        } catch (\Exception $e) {
            // Log::error('Error fetching survey respondents: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener respondientes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Marcar un respondiente como contestado
     */
    public function markAsResponded(Request $request)
    {
        $validatedData = $request->validate([
            'survey_id' => 'required|integer',
            'respondent_email' => 'required|email',
            'response_data' => 'nullable|array'
        ]);

        try {
            $respondent = SurveyRespondentModel::where('survey_id', $validatedData['survey_id'])
                ->where('respondent_email', $validatedData['respondent_email'])
                ->first();

            if (!$respondent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Respondiente no encontrado'
                ], 404);
            }

            $respondent->markAsResponded($validatedData['response_data'] ?? null);

            return response()->json([
                'success' => true,
                'message' => 'Respondiente marcado como contestado',
                'data' => $respondent
            ]);
        } catch (\Exception $e) {
            Log::error('Error marking respondent as responded: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar estado del respondiente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de respondientes para una encuesta
     */
    public function getStats($surveyId)
    {
        try {
            $stats = [
                'total' => SurveyRespondentModel::where('survey_id', $surveyId)->count(),
                'enviada' => SurveyRespondentModel::where('survey_id', $surveyId)
                    ->where('status', 'Enviada')->count(),
                'contestada' => SurveyRespondentModel::where('survey_id', $surveyId)
                    ->where('status', 'Contestada')->count(),
                'invalida' => SurveyRespondentModel::where('survey_id', $surveyId)
                    ->where('status', 'Inválida')->count(),
            ];

            $stats['tasa_respuesta'] = $stats['total'] > 0 
                ? round(($stats['contestada'] / $stats['total']) * 100, 2) 
                : 0;

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching respondent stats: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar información de un respondiente
     */
    public function update(Request $request, $id)
    {
        $validatedData = $request->validate([
            'respondent_name' => 'nullable|string|max:255',
            'status' => 'nullable|in:Enviada,Contestada,Inválida',
            'response_data' => 'nullable|array'
        ]);

        try {
            $respondent = SurveyRespondentModel::findOrFail($id);
            
            // Si se está cambiando a "Contestada" y no tenía fecha de respuesta
            if (isset($validatedData['status']) && 
                $validatedData['status'] === 'Contestada' && 
                !$respondent->responded_at) {
                $validatedData['responded_at'] = now();
            }

            $respondent->update($validatedData);

            return response()->json([
                'success' => true,
                'message' => 'Respondiente actualizado correctamente',
                'data' => $respondent
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating respondent: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar respondiente',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un respondiente
     */
    public function destroy($id)
    {
        try {
            $respondent = SurveyRespondentModel::findOrFail($id);
            $respondent->delete();

            return response()->json([
                'success' => true,
                'message' => 'Respondiente eliminado correctamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting respondent: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar respondiente',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}