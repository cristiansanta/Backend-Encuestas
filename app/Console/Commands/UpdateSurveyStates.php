<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SurveyModel;

class UpdateSurveyStates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'surveys:update-states';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update survey publication status based on start and end dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting survey states update...');
        
        try {
            // Obtener todas las encuestas con fechas definidas
            $surveys = SurveyModel::whereNotNull('start_date')
                                ->whereNotNull('end_date')
                                ->where('status', true) // Solo encuestas publicadas
                                ->get();
            
            $updatedCount = 0;
            $report = [];
            
            foreach ($surveys as $survey) {
                $now = now();
                $endDate = $survey->end_date;
                $startDate = $survey->start_date;
                
                $shouldBeFinished = $endDate < $now;
                $shouldBeScheduled = $startDate > $now;
                
                $needsUpdate = false;
                $newStatus = $survey->publication_status;
                $oldStatus = $survey->publication_status;
                
                // Determinar el estado correcto
                if ($shouldBeFinished && $survey->publication_status !== 'finished') {
                    $newStatus = 'finished';
                    $needsUpdate = true;
                }
                elseif ($shouldBeScheduled && $survey->publication_status !== 'scheduled') {
                    $newStatus = 'scheduled';
                    $needsUpdate = true;
                }
                elseif (!$shouldBeFinished && !$shouldBeScheduled && 
                        !in_array($survey->publication_status, ['published', 'finished'])) {
                    $newStatus = 'published';
                    $needsUpdate = true;
                }
                
                if ($needsUpdate) {
                    $survey->publication_status = $newStatus;
                    $survey->save();
                    
                    $report[] = [
                        'id' => $survey->id,
                        'title' => substr($survey->title, 0, 30) . '...',
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'start_date' => $startDate->format('Y-m-d H:i:s'),
                        'end_date' => $endDate->format('Y-m-d H:i:s')
                    ];
                    
                    $updatedCount++;
                    $this->line("Updated Survey {$survey->id}: '{$oldStatus}' → '{$newStatus}'");
                }
            }
            
            $this->info("Survey states update completed!");
            $this->info("Surveys checked: {$surveys->count()}");
            $this->info("Surveys updated: {$updatedCount}");
            
            if ($updatedCount > 0) {
                $this->newLine();
                $this->table(
                    ['ID', 'Title', 'Old Status', 'New Status', 'Start Date', 'End Date'],
                    $report
                );
            }
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("Error updating survey states: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}