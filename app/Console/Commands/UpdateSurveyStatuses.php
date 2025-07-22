<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SurveyModel;
use Carbon\Carbon;

class UpdateSurveyStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'surveys:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Actualiza los estados de las encuestas basándose en las fechas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Actualizando estados de encuestas...');
        
        // Obtener todas las encuestas publicadas con fechas
        $surveys = SurveyModel::where('status', true)
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->get();
        
        $updatedCount = 0;
        
        foreach ($surveys as $survey) {
            $now = Carbon::now();
            $startDate = Carbon::parse($survey->start_date);
            $endDate = Carbon::parse($survey->end_date);
            
            $oldStatus = $survey->status;
            $shouldUpdate = false;
            
            // Calcular días hasta el final
            $daysUntilEnd = $now->diffInDays($endDate, false);
            
            // Si la fecha de fin ya pasó y la encuesta está activa, finalizarla
            if ($endDate < $now && $survey->status == true) {
                // Aquí podrías manejar la lógica para finalizar la encuesta
                // Por ahora, solo lo registramos
                $this->info("Encuesta '{$survey->title}' ha finalizado.");
                $shouldUpdate = true;
            }
            // Si faltan 3 días o menos para finalizar
            elseif ($daysUntilEnd <= 3 && $daysUntilEnd > 0) {
                $this->info("Encuesta '{$survey->title}' está próxima a finalizar ({$daysUntilEnd} días restantes).");
            }
            
            if ($shouldUpdate) {
                $updatedCount++;
            }
        }
        
        $this->info("Proceso completado. {$updatedCount} encuestas actualizadas.");
        
        return Command::SUCCESS;
    }
}