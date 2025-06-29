<?php
/**
 * Script to add missing options to existing questions
 * This fixes questions that were created before the QuestionController fix
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\QuestionModel;
use App\Models\QuestionsoptionsModel;
use App\Models\TypeQuestionModel;

echo "=== FIXING MISSING OPTIONS FOR EXISTING QUESTIONS ===\n\n";

// Get question types that require options
$typesRequiringOptions = ['Opción única', 'Opción múltiple', 'Opcion Multiple'];

echo "1. Finding question types that require options...\n";
$questionTypes = TypeQuestionModel::whereIn('title', $typesRequiringOptions)->get();
$typeIds = $questionTypes->pluck('id')->toArray();

echo "Found types: " . implode(', ', $questionTypes->pluck('title')->toArray()) . "\n";
echo "Type IDs: " . implode(', ', $typeIds) . "\n\n";

echo "2. Finding questions without options...\n";
$questionsWithoutOptions = QuestionModel::whereIn('type_questions_id', $typeIds)
    ->whereDoesntHave('options')
    ->with('type')
    ->get();

echo "Found " . $questionsWithoutOptions->count() . " questions without options\n\n";

if ($questionsWithoutOptions->count() > 0) {
    echo "3. Adding default options to questions...\n";
    
    foreach ($questionsWithoutOptions as $question) {
        echo "Processing Question {$question->id}: \"{$question->title}\" (Type: {$question->type->title})\n";
        
        // Create default options
        $defaultOptions = ['Opción 1', 'Opción 2', 'Opción 3'];
        
        foreach ($defaultOptions as $optionText) {
            QuestionsoptionsModel::create([
                'questions_id' => $question->id,
                'options' => $optionText,
                'creator_id' => $question->creator_id ?? 1,
                'status' => true,
            ]);
        }
        
        echo "  ✅ Added " . count($defaultOptions) . " default options\n";
    }
    
    echo "\n4. Verification - Checking if options were added...\n";
    
    foreach ($questionsWithoutOptions as $question) {
        $optionsCount = QuestionsoptionsModel::where('questions_id', $question->id)->count();
        echo "Question {$question->id}: {$optionsCount} options\n";
    }
    
} else {
    echo "✅ All questions already have options!\n";
}

echo "\n=== SPECIFIC QUESTIONS FROM LOGS ===\n";
$logQuestionIds = [440, 457, 461, 456, 460, 598];

foreach ($logQuestionIds as $questionId) {
    $question = QuestionModel::with(['type', 'options'])->find($questionId);
    if ($question) {
        $optionsCount = $question->options->count();
        echo "Q{$questionId} ({$question->type->title}): {$optionsCount} options\n";
        
        if ($optionsCount === 0 && in_array($question->type_questions_id, $typeIds)) {
            echo "  ⚠️ Missing options - adding default options...\n";
            
            $defaultOptions = ['Opción 1', 'Opción 2', 'Opción 3'];
            foreach ($defaultOptions as $optionText) {
                QuestionsoptionsModel::create([
                    'questions_id' => $question->id,
                    'options' => $optionText,
                    'creator_id' => $question->creator_id ?? 1,
                    'status' => true,
                ]);
            }
            echo "  ✅ Added 3 default options\n";
        }
    } else {
        echo "Q{$questionId}: Not found\n";
    }
}

echo "\n=== COMPLETED ===\n";
echo "Now test the /questionsop endpoint to see if options are loaded properly.\n";

?>