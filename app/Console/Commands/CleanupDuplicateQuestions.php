<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\QuestionModel;
use App\Models\SurveyquestionsModel;

class CleanupDuplicateQuestions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'cleanup:duplicate-questions {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up duplicate questions in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $this->info('Starting duplicate question cleanup...');
        
        if ($dryRun) {
            $this->info('DRY RUN MODE - No actual changes will be made');
        }

        // Find duplicates based on title, section_id, and creator_id
        $duplicateGroups = DB::table('questions')
            ->select('title', 'section_id', 'creator_id', DB::raw('COUNT(*) as count'), DB::raw('MIN(id) as keep_id'), DB::raw('string_agg(id::text, \',\') as all_ids'))
            ->groupBy('title', 'section_id', 'creator_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicateGroups->isEmpty()) {
            $this->info('No duplicate questions found.');
            return 0;
        }

        $this->info(sprintf('Found %d groups of duplicate questions:', $duplicateGroups->count()));

        $totalDeleted = 0;
        $totalReferences = 0;

        foreach ($duplicateGroups as $group) {
            $allIds = explode(',', $group->all_ids);
            $keepId = $group->keep_id;
            $deleteIds = array_filter($allIds, fn($id) => $id != $keepId);
            
            $sectionText = $group->section_id ? "section {$group->section_id}" : "no section";
            $this->info(sprintf(
                '  Title: "%s" (%s, creator: %d) - Keep ID %d, Delete IDs: %s (%d duplicates)',
                $group->title,
                $sectionText,
                $group->creator_id,
                $keepId,
                implode(', ', $deleteIds),
                count($deleteIds)
            ));

            if (!$dryRun) {
                DB::beginTransaction();
                
                try {
                    // Update survey_questions references to point to the kept question
                    foreach ($deleteIds as $deleteId) {
                        $referencesUpdated = DB::table('survey_questions')
                            ->where('question_id', $deleteId)
                            ->update(['question_id' => $keepId]);
                        
                        $totalReferences += $referencesUpdated;
                        
                        if ($referencesUpdated > 0) {
                            $this->info(sprintf('    Updated %d survey_question references from ID %d to %d', 
                                $referencesUpdated, $deleteId, $keepId));
                        }
                    }
                    
                    // Delete the duplicate questions
                    $deleted = QuestionModel::whereIn('id', $deleteIds)->delete();
                    $totalDeleted += $deleted;
                    
                    $this->info(sprintf('    Deleted %d duplicate question(s)', $deleted));
                    
                    DB::commit();
                    
                } catch (\Exception $e) {
                    DB::rollback();
                    $this->error(sprintf('Error processing group "%s": %s', $group->title, $e->getMessage()));
                    continue;
                }
            }
        }

        if ($dryRun) {
            $this->info(sprintf('DRY RUN: Would delete %d duplicate questions from %d groups', 
                $duplicateGroups->sum(fn($group) => substr_count($group->all_ids, ',') + 1 - 1), 
                $duplicateGroups->count()
            ));
        } else {
            $this->info(sprintf('Cleanup completed: Deleted %d duplicate questions and updated %d survey_question references', 
                $totalDeleted, $totalReferences));
        }

        return 0;
    }
}