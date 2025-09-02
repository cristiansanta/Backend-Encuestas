<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SurveyModel;
use App\Models\CategoryModel;
use App\Models\SectionModel;
use App\Models\SurveyquestionsModel;
use App\Models\QuestionModel;
use App\Models\QuestionsoptionsModel;
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
                            {--questions : Solo eliminar preguntas huérfanas}
                            {--options : Solo eliminar opciones de respuesta huérfanas}
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
        } elseif ($this->option('questions')) {
            $this->cleanupOrphanQuestions($dryRun, $force);
        } elseif ($this->option('options')) {
            $this->cleanupOrphanOptions($dryRun, $force);
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
        $optionsCount = QuestionsoptionsModel::count();
        $orphanOptionsCount = QuestionsoptionsModel::whereDoesntHave('question')->count();
        $orphanQuestionsCount = QuestionModel::whereDoesntHave('surveyQuestions')->count();

        $this->info('📊 Estadísticas actuales:');
        $this->table(['Tabla', 'Registros'], [
            ['Encuestas', $surveysCount],
            ['Categorías', $categoriesCount],
            ['Secciones', $sectionsCount],
            ['Preguntas', $questionsCount],
            ['Preguntas huérfanas (sin encuesta)', $orphanQuestionsCount],
            ['Opciones de respuesta', $optionsCount],
            ['Opciones huérfanas', $orphanOptionsCount],
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
                'questions' => 'Solo preguntas huérfanas (sin encuesta)',
                'all_questions' => 'TODAS las preguntas (peligroso)',
                'options' => 'Solo opciones de respuesta huérfanas',
                'problematic_options' => 'Solo opciones problemáticas (f, x, d)',
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
            case 'questions':
                $this->cleanupOrphanQuestions($dryRun, $force);
                break;
            case 'all_questions':
                $this->cleanupAllQuestions($dryRun, $force);
                break;
            case 'options':
                $this->cleanupOrphanOptions($dryRun, $force);
                break;
            case 'problematic_options':
                $this->cleanupProblematicOptions($dryRun, $force);
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
        $this->cleanupOrphanQuestions($dryRun, true);
        $this->cleanupOrphanOptions($dryRun, true);
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

    /**
     * Limpiar opciones de respuesta huérfanas (sin pregunta asociada)
     */
    private function cleanupOrphanOptions($dryRun, $force)
    {
        $orphanOptions = QuestionsoptionsModel::whereDoesntHave('question')->get();
        
        if ($orphanOptions->isEmpty()) {
            $this->info('No hay opciones de respuesta huérfanas para eliminar');
            return;
        }

        $this->info("🔍 Encontradas {$orphanOptions->count()} opciones de respuesta huérfanas");

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación de opciones sin pregunta asociada?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;

        foreach ($orphanOptions as $option) {
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría opción huérfana: '{$option->options}' (ID: {$option->id}, questions_id: {$option->questions_id})");
            } else {
                $option->delete();
                $this->line("✅ Eliminada opción huérfana: '{$option->options}' (ID: {$option->id})");
                $deletedCount++;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} opciones de respuesta huérfanas eliminadas");
        }
    }

    /**
     * Limpiar opciones problemáticas específicas (f, x, d)
     */
    private function cleanupProblematicOptions($dryRun, $force)
    {
        $problematicOptions = QuestionsoptionsModel::whereIn('options', ['f', 'x', 'd'])->get();
        
        if ($problematicOptions->isEmpty()) {
            $this->info('No hay opciones problemáticas (f, x, d) para eliminar');
            return;
        }

        $this->info("🔍 Encontradas {$problematicOptions->count()} opciones problemáticas");
        
        // Mostrar información detallada de las opciones problemáticas
        $this->info('📋 Opciones problemáticas encontradas:');
        $this->table(['ID', 'Pregunta ID', 'Opción', 'Creada'], 
            $problematicOptions->map(function($option) {
                return [$option->id, $option->questions_id, $option->options, $option->created_at];
            })->toArray()
        );

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación de estas opciones problemáticas?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;

        foreach ($problematicOptions as $option) {
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría opción problemática: '{$option->options}' (ID: {$option->id}, Pregunta: {$option->questions_id})");
            } else {
                $option->delete();
                $this->line("✅ Eliminada opción problemática: '{$option->options}' (ID: {$option->id}, Pregunta: {$option->questions_id})");
                $deletedCount++;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} opciones problemáticas eliminadas");
        }
    }

    /**
     * Limpiar preguntas huérfanas (sin encuesta asociada)
     */
    private function cleanupOrphanQuestions($dryRun, $force)
    {
        $orphanQuestions = QuestionModel::whereDoesntHave('surveyQuestions')->with('options')->get();
        
        if ($orphanQuestions->isEmpty()) {
            $this->info('No hay preguntas huérfanas para eliminar');
            return;
        }

        $this->info("🔍 Encontradas {$orphanQuestions->count()} preguntas huérfanas (sin encuesta asociada)");

        // Contar opciones asociadas que también se eliminarán
        $totalOptions = $orphanQuestions->sum(function($question) {
            return $question->options->count();
        });

        if ($totalOptions > 0) {
            $this->warn("⚠️  Esto también eliminará {$totalOptions} opciones de respuesta asociadas");
        }

        if (!$force && !$dryRun && !$this->confirm('¿Confirmas la eliminación de preguntas sin encuesta asociada?')) {
            $this->info('Operación cancelada');
            return;
        }

        $deletedCount = 0;
        $deletedOptionsCount = 0;

        foreach ($orphanQuestions as $question) {
            $optionsCount = $question->options->count();
            
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría pregunta huérfana: '{$question->title}' (ID: {$question->id}) con {$optionsCount} opciones");
            } else {
                $question->delete(); // Las opciones se eliminan por cascada
                $this->line("✅ Eliminada pregunta huérfana: '{$question->title}' (ID: {$question->id}) con {$optionsCount} opciones");
                $deletedCount++;
                $deletedOptionsCount += $optionsCount;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} preguntas huérfanas eliminadas junto con {$deletedOptionsCount} opciones");
        }
    }

    /**
     * Limpiar TODAS las preguntas (peligroso)
     */
    private function cleanupAllQuestions($dryRun, $force)
    {
        $allQuestions = QuestionModel::with('options')->get();
        
        if ($allQuestions->isEmpty()) {
            $this->info('No hay preguntas para eliminar');
            return;
        }

        $this->error("⚠️  PELIGRO: Esto eliminará TODAS las {$allQuestions->count()} preguntas del sistema");
        
        $totalOptions = $allQuestions->sum(function($question) {
            return $question->options->count();
        });

        $this->error("⚠️  También eliminará {$totalOptions} opciones de respuesta");

        if (!$force && !$dryRun) {
            $this->error('Esta es una operación DESTRUCTIVA que eliminará TODAS las preguntas');
            if (!$this->confirm('¿Estás ABSOLUTAMENTE seguro? Esta acción NO se puede deshacer')) {
                $this->info('Operación cancelada (sensato)');
                return;
            }
            if (!$this->confirm('Confirma por segunda vez: ¿Eliminar TODAS las preguntas?')) {
                $this->info('Operación cancelada');
                return;
            }
        }

        $deletedCount = 0;
        $deletedOptionsCount = 0;

        foreach ($allQuestions as $question) {
            $optionsCount = $question->options->count();
            
            if ($dryRun) {
                $this->line("🗑️  [DRY-RUN] Se eliminaría pregunta: '{$question->title}' (ID: {$question->id}) con {$optionsCount} opciones");
            } else {
                $question->delete();
                $this->line("✅ Eliminada pregunta: '{$question->title}' (ID: {$question->id}) con {$optionsCount} opciones");
                $deletedCount++;
                $deletedOptionsCount += $optionsCount;
            }
        }

        if (!$dryRun) {
            $this->info("✅ {$deletedCount} preguntas eliminadas junto con {$deletedOptionsCount} opciones");
        }
    }
}
