<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SurveyModel;
use App\Models\CategoryModel;
use App\Models\SectionModel;
use App\Models\SurveyquestionsModel;
use App\Models\QuestionModel;
use App\Models\TemporarySurveyModel;
use App\Models\SurveyAnswersModel;
use Illuminate\Support\Facades\DB;

class CleanupSurveysAndCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cleanup:surveys-and-categories 
                            {--all : Eliminar todas las encuestas y categorías}
                            {--surveys : Solo eliminar encuestas}
                            {--categories : Solo eliminar categorías huérfanas}
                            {--dry-run : Mostrar qué se eliminaría sin ejecutar}
                            {--force : Forzar eliminación sin confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia encuestas y categorías de la base de datos de manera segura';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧹 Iniciando limpieza de encuestas y categorías...');
        
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        
        if ($dryRun) {
            $this->warn('🔍 MODO DRY-RUN: Solo se mostrará qué se eliminaría');
        }

        // Mostrar estadísticas actuales
        $this->showCurrentStats();

        if ($this->option('all')) {
            $this->cleanupAll($dryRun, $force);
        } elseif ($this->option('surveys')) {
            $this->cleanupSurveys($dryRun, $force);
        } elseif ($this->option('categories')) {
            $this->cleanupOrphanCategories($dryRun, $force);
        } else {
            $this->showMenu($dryRun, $force);
        }

        $this->info('✅ Proceso de limpieza completado');
    }

    /**
     * Mostrar estadísticas actuales
     */
    private function showCurrentStats()
    {
        $surveysCount = SurveyModel::count();
        $categoriesCount = CategoryModel::count();
        $sectionsCount = SectionModel::count();
        $questionsCount = QuestionModel::count();
        $surveyQuestionsCount = SurveyquestionsModel::count();
        $temporaryCount = TemporarySurveyModel::count();
        $answersCount = SurveyAnswersModel::count();

        $this->info('📊 Estadísticas actuales:');
        $this->table(['Tabla', 'Registros'], [
            ['Encuestas', $surveysCount],
            ['Categorías', $categoriesCount],
            ['Secciones', $sectionsCount],
            ['Preguntas', $questionsCount],
            ['Survey-Questions (pivot)', $surveyQuestionsCount],
            ['Encuestas temporales', $temporaryCount],
            ['Respuestas de encuestas', $answersCount],
        ]);
    }

    /**
     * Mostrar menú interactivo
     */
    private function showMenu($dryRun, $force)
    {
        $choice = $this->choice(
            '¿Qué deseas limpiar?',
            [
                'all' => 'Todo (encuestas, categorías y datos relacionados)',
                'surveys' => 'Solo encuestas',
                'categories' => 'Solo categorías huérfanas',
                'temporary' => 'Solo encuestas temporales',
                'specific' => 'Encuestas específicas',
                'cancel' => 'Cancelar'
            ],
            'cancel'
        );

        switch ($choice) {
            case 'all':
                $this->cleanupAll($dryRun, $force);
                break;
            case 'surveys':
                $this->cleanupSurveys($dryRun, $force);
                break;
            case 'categories':
                $this->cleanupOrphanCategories($dryRun, $force);
                break;
            case 'temporary':
                $this->cleanupTemporarySurveys($dryRun, $force);
                break;
            case 'specific':
                $this->cleanupSpecificSurveys($dryRun, $force);
                break;
            case 'cancel':
                $this->info('Operación cancelada');
                break;
        }
    }

    /**
     * Limpiar todo
     */
    private function cleanupAll($dryRun, $force)
    {
        $this->warn('⚠️  ATENCIÓN: Esto eliminará TODAS las encuestas y categorías');
        
        if (!$force && !$dryRun && !$this->confirm('¿Estás seguro de que deseas continuar?')) {
            $this->info('Operación cancelada');
            return;
        }

        $this->cleanupSurveys($dryRun, true);
        $this->cleanupOrphanCategories($dryRun, true);
        $this->cleanupTemporarySurveys($dryRun, true);
    }

    /**
     * Limpiar todas las encuestas
     */
    private function cleanupSurveys($dryRun, $force)
    {
        $surveys = SurveyModel::with(['category', 'sections', 'surveyQuestions'])->get();
        
        if ($surveys->isEmpty()) {
            $this->info('No hay encuestas para eliminar');
            return;
        }

        $this->info("🔍 Encontradas {$surveys->count()} encuestas para eliminar");

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación de todas las encuestas?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;
        $errors = 0;

        foreach ($surveys as $survey) {
            try {
                if ($dryRun) {
                    $this->line("🗑️  [DRY-RUN] Se eliminaría: {$survey->title} (ID: {$survey->id})");
                    $this->line("   - Secciones: {$survey->sections->count()}");
                    $this->line("   - Preguntas: {$survey->surveyQuestions->count()}");
                } else {
                    DB::transaction(function () use ($survey) {
                        // Las relaciones se eliminan automáticamente por el evento deleting
                        $survey->delete();
                    });
                    
                    $this->line("✅ Eliminada: {$survey->title}");
                    $deletedCount++;
                }
            } catch (\Exception $e) {
                $this->error("❌ Error eliminando encuesta {$survey->id}: " . $e->getMessage());
                $errors++;
            }
        }

        if (!$dryRun) {
            $this->info("✅ Proceso completado: {$deletedCount} encuestas eliminadas, {$errors} errores");
        }
    }

    /**
     * Limpiar categorías huérfanas (sin encuestas asociadas)
     */
    private function cleanupOrphanCategories($dryRun, $force)
    {
        $orphanCategories = CategoryModel::whereDoesntHave('surveys')->get();
        
        if ($orphanCategories->isEmpty()) {
            $this->info('No hay categorías huérfanas para eliminar');
            return;
        }

        $this->info("🔍 Encontradas {$orphanCategories->count()} categorías huérfanas");

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación de categorías sin encuestas?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;

        foreach ($orphanCategories as $category) {
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría categoría: {$category->title} (ID: {$category->id})");
            } else {
                $category->delete();
                $this->line("✅ Eliminada categoría: {$category->title}");
                $deletedCount++;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} categorías huérfanas eliminadas");
        }
    }

    /**
     * Limpiar encuestas temporales
     */
    private function cleanupTemporarySurveys($dryRun, $force)
    {
        $temporarySurveys = TemporarySurveyModel::all();
        
        if ($temporarySurveys->isEmpty()) {
            $this->info('No hay encuestas temporales para eliminar');
            return;
        }

        $this->info("🔍 Encontradas {$temporarySurveys->count()} encuestas temporales");

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación de encuestas temporales?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;

        foreach ($temporarySurveys as $temp) {
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría temporal: ID {$temp->id} (Creada: {$temp->created_at})");
            } else {
                $temp->delete();
                $this->line("✅ Eliminada encuesta temporal ID: {$temp->id}");
                $deletedCount++;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} encuestas temporales eliminadas");
        }
    }

    /**
     * Limpiar encuestas específicas
     */
    private function cleanupSpecificSurveys($dryRun, $force)
    {
        $surveys = SurveyModel::select('id', 'title', 'created_at')->get();
        
        if ($surveys->isEmpty()) {
            $this->info('No hay encuestas disponibles');
            return;
        }

        $this->info('📋 Encuestas disponibles:');
        $this->table(['ID', 'Título', 'Creada'], 
            $surveys->map(function($survey) {
                return [$survey->id, $survey->title, $survey->created_at];
            })->toArray()
        );

        $ids = $this->ask('Ingresa los IDs de las encuestas a eliminar (separados por comas)');
        
        if (!$ids) {
            $this->info('Operación cancelada');
            return;
        }

        $idArray = array_map('trim', explode(',', $ids));
        $surveysToDelete = SurveyModel::whereIn('id', $idArray)->get();

        if ($surveysToDelete->isEmpty()) {
            $this->error('No se encontraron encuestas con los IDs proporcionados');
            return;
        }

        $this->info("🎯 Se eliminarán {$surveysToDelete->count()} encuestas específicas");

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;

        foreach ($surveysToDelete as $survey) {
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría: {$survey->title} (ID: {$survey->id})");
            } else {
                $survey->delete();
                $this->line("✅ Eliminada: {$survey->title}");
                $deletedCount++;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} encuestas específicas eliminadas");
        }
    }
}
