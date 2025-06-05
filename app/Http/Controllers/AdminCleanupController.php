<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SurveyModel;
use App\Models\CategoryModel;
use App\Models\SectionModel;
use App\Models\SurveyquestionsModel;
use App\Models\QuestionModel;
use App\Models\TemporarySurveyModel;
use App\Models\SurveyAnswersModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminCleanupController extends Controller
{
    /**
     * Obtener estadísticas de la base de datos
     */
    public function getStats()
    {
        try {
            $stats = [
                'surveys' => SurveyModel::count(),
                'categories' => CategoryModel::count(),
                'sections' => SectionModel::count(),
                'questions' => QuestionModel::count(),
                'survey_questions' => SurveyquestionsModel::count(),
                'temporary_surveys' => TemporarySurveyModel::count(),
                'survey_answers' => SurveyAnswersModel::count(),
            ];

            // Categorías huérfanas
            $orphanCategories = CategoryModel::whereDoesntHave('surveys')->count();
            $stats['orphan_categories'] = $orphanCategories;

            // Secciones huérfanas
            $orphanSections = SectionModel::whereNull('id_survey')->count();
            $stats['orphan_sections'] = $orphanSections;

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting cleanup stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }

    /**
     * Limpiar todas las encuestas
     */
    public function cleanupSurveys(Request $request)
    {
        try {
            $dryRun = $request->get('dry_run', false);
            $surveys = SurveyModel::with(['category', 'sections', 'surveyQuestions'])->get();
            
            if ($surveys->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay encuestas para eliminar',
                    'deleted_count' => 0
                ]);
            }

            $deletedCount = 0;
            $surveysInfo = [];

            DB::transaction(function () use ($surveys, $dryRun, &$deletedCount, &$surveysInfo) {
                foreach ($surveys as $survey) {
                    $surveyInfo = [
                        'id' => $survey->id,
                        'title' => $survey->title,
                        'sections_count' => $survey->sections->count(),
                        'questions_count' => $survey->surveyQuestions->count()
                    ];

                    if (!$dryRun) {
                        $survey->delete();
                        $deletedCount++;
                    }

                    $surveysInfo[] = $surveyInfo;
                }
            });

            return response()->json([
                'success' => true,
                'message' => $dryRun ? 
                    'Simulación completada' : 
                    "Se eliminaron {$deletedCount} encuestas exitosamente",
                'deleted_count' => $dryRun ? 0 : $deletedCount,
                'surveys' => $surveysInfo,
                'dry_run' => $dryRun
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning up surveys: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar encuestas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar categorías huérfanas
     */
    public function cleanupOrphanCategories(Request $request)
    {
        try {
            $dryRun = $request->get('dry_run', false);
            $orphanCategories = CategoryModel::whereDoesntHave('surveys')->get();
            
            if ($orphanCategories->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay categorías huérfanas para eliminar',
                    'deleted_count' => 0
                ]);
            }

            $deletedCount = 0;
            $categoriesInfo = [];

            DB::transaction(function () use ($orphanCategories, $dryRun, &$deletedCount, &$categoriesInfo) {
                foreach ($orphanCategories as $category) {
                    $categoriesInfo[] = [
                        'id' => $category->id,
                        'title' => $category->title,
                        'description' => $category->descrip_cat
                    ];

                    if (!$dryRun) {
                        $category->delete();
                        $deletedCount++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => $dryRun ? 
                    'Simulación completada' : 
                    "Se eliminaron {$deletedCount} categorías huérfanas exitosamente",
                'deleted_count' => $dryRun ? 0 : $deletedCount,
                'categories' => $categoriesInfo,
                'dry_run' => $dryRun
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning up orphan categories: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar categorías: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpiar encuestas temporales
     */
    public function cleanupTemporarySurveys(Request $request)
    {
        try {
            $dryRun = $request->get('dry_run', false);
            $temporarySurveys = TemporarySurveyModel::all();
            
            if ($temporarySurveys->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No hay encuestas temporales para eliminar',
                    'deleted_count' => 0
                ]);
            }

            $deletedCount = 0;
            $temporariesInfo = [];

            DB::transaction(function () use ($temporarySurveys, $dryRun, &$deletedCount, &$temporariesInfo) {
                foreach ($temporarySurveys as $temp) {
                    $temporariesInfo[] = [
                        'id' => $temp->id,
                        'created_at' => $temp->created_at,
                        'updated_at' => $temp->updated_at
                    ];

                    if (!$dryRun) {
                        $temp->delete();
                        $deletedCount++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => $dryRun ? 
                    'Simulación completada' : 
                    "Se eliminaron {$deletedCount} encuestas temporales exitosamente",
                'deleted_count' => $dryRun ? 0 : $deletedCount,
                'temporaries' => $temporariesInfo,
                'dry_run' => $dryRun
            ]);

        } catch (\Exception $e) {
            Log::error('Error cleaning up temporary surveys: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar encuestas temporales: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar encuestas específicas
     */
    public function deleteSpecificSurveys(Request $request)
    {
        $request->validate([
            'survey_ids' => 'required|array',
            'survey_ids.*' => 'integer|exists:surveys,id'
        ]);

        try {
            $dryRun = $request->get('dry_run', false);
            $surveyIds = $request->get('survey_ids');
            $surveys = SurveyModel::whereIn('id', $surveyIds)->get();
            
            if ($surveys->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontraron encuestas con los IDs proporcionados'
                ], 404);
            }

            $deletedCount = 0;
            $surveysInfo = [];

            DB::transaction(function () use ($surveys, $dryRun, &$deletedCount, &$surveysInfo) {
                foreach ($surveys as $survey) {
                    $surveysInfo[] = [
                        'id' => $survey->id,
                        'title' => $survey->title
                    ];

                    if (!$dryRun) {
                        $survey->delete();
                        $deletedCount++;
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => $dryRun ? 
                    'Simulación completada' : 
                    "Se eliminaron {$deletedCount} encuestas específicas exitosamente",
                'deleted_count' => $dryRun ? 0 : $deletedCount,
                'surveys' => $surveysInfo,
                'dry_run' => $dryRun
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting specific surveys: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar encuestas específicas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Limpieza completa
     */
    public function cleanupAll(Request $request)
    {
        try {
            $dryRun = $request->get('dry_run', false);
            
            // Obtener estadísticas antes
            $beforeStats = [
                'surveys' => SurveyModel::count(),
                'categories' => CategoryModel::count(),
                'temporary_surveys' => TemporarySurveyModel::count(),
            ];

            $results = [];

            DB::transaction(function () use ($dryRun, &$results) {
                // Limpiar encuestas
                $surveysResult = $this->cleanupSurveys($request);
                $results['surveys'] = json_decode($surveysResult->getContent(), true);

                // Limpiar categorías huérfanas
                $categoriesResult = $this->cleanupOrphanCategories($request);
                $results['categories'] = json_decode($categoriesResult->getContent(), true);

                // Limpiar temporales
                $temporariesResult = $this->cleanupTemporarySurveys($request);
                $results['temporaries'] = json_decode($temporariesResult->getContent(), true);
            });

            return response()->json([
                'success' => true,
                'message' => $dryRun ? 
                    'Simulación de limpieza completa realizada' : 
                    'Limpieza completa realizada exitosamente',
                'before_stats' => $beforeStats,
                'results' => $results,
                'dry_run' => $dryRun
            ]);

        } catch (\Exception $e) {
            Log::error('Error in complete cleanup: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error en la limpieza completa: ' . $e->getMessage()
            ], 500);
        }
    }
}
