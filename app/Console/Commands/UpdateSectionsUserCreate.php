<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\SectionModel;

class UpdateSectionsUserCreate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sections:update-user-create';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing sections to assign proper user_create values based on survey ownership';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting sections user_create update...');

        // Get sections with null user_create
        $sectionsToUpdate = SectionModel::whereNull('user_create')->get();

        $this->info("Found {$sectionsToUpdate->count()} sections to update");

        $updated = 0;
        $defaultUser = 'Super Admin'; // Default fallback user

        foreach ($sectionsToUpdate as $section) {
            $userCreate = $defaultUser;

            // If section belongs to a survey, get the survey's user_create
            if ($section->id_survey) {
                $survey = DB::table('surveys')->where('id', $section->id_survey)->first();
                if ($survey && $survey->user_create) {
                    $userCreate = $survey->user_create;
                }
            }
            // For bank sections (id_survey is null), assign to default user

            $section->user_create = $userCreate;
            $section->save();

            $updated++;
            $this->line("Updated section ID {$section->id} ('{$section->title}') - assigned to: {$userCreate}");
        }

        $this->info("Successfully updated {$updated} sections");
        return 0;
    }
}
