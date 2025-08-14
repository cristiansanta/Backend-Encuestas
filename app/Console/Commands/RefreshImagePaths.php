<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RefreshImagePaths extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'images:refresh-paths';

    /**
     * The console command description.
     */
    protected $description = 'Actualiza las rutas de imágenes en la base de datos para usar el FileController';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando actualización de rutas de imágenes...');

        // Contar registros afectados en surveys
        $surveyCount = DB::table('surveys')
            ->where('descrip', 'like', '%/storage/images/%')
            ->count();

        // Contar registros afectados en questions  
        $questionCount = DB::table('questions')
            ->where('descrip', 'like', '%/storage/images/%')
            ->count();

        if ($surveyCount === 0 && $questionCount === 0) {
            $this->info('No se encontraron imágenes con rutas antiguas.');
            return Command::SUCCESS;
        }

        $this->info("Encuestas a actualizar: {$surveyCount}");
        $this->info("Preguntas a actualizar: {$questionCount}");

        if ($this->confirm('¿Deseas continuar con la actualización?')) {
            // Actualizar surveys
            if ($surveyCount > 0) {
                DB::statement("
                    UPDATE surveys 
                    SET descrip = REPLACE(descrip, '/storage/images/', '/api/storage/images/')
                    WHERE descrip LIKE '%/storage/images/%'
                ");
                $this->info("✓ Actualizadas {$surveyCount} encuestas");
            }

            // Actualizar questions
            if ($questionCount > 0) {
                DB::statement("
                    UPDATE questions 
                    SET descrip = REPLACE(descrip, '/api/storage/images/', '/api/storage/images/')
                    WHERE descrip LIKE '%/storage/images/%'
                ");
                $this->info("✓ Actualizadas {$questionCount} preguntas");
            }

            // Verificar que el directorio de imágenes privadas existe
            $privateImagesPath = storage_path('app/private/images');
            if (!is_dir($privateImagesPath)) {
                mkdir($privateImagesPath, 0755, true);
                $this->info('✓ Creado directorio de imágenes privadas');
            }

            // Contar archivos de imágenes
            $imageFiles = glob($privateImagesPath . '/*.{png,jpg,jpeg,svg}', GLOB_BRACE);
            $imageCount = count($imageFiles);
            $this->info("✓ Encontradas {$imageCount} imágenes en almacenamiento privado");

            $this->success('¡Actualización completada exitosamente!');
            
        } else {
            $this->info('Operación cancelada.');
        }

        return Command::SUCCESS;
    }
}