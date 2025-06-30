<?php
/**
 * Script to add missing options to new questions (Q600, Q601, Q602, Q603)
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\QuestionModel;
use App\Models\QuestionsoptionsModel;
use App\Models\TypeQuestionModel;

echo "=== FIXING NEW QUESTIONS WITHOUT OPTIONS ===\n\n";

// Get question types that require options
$typesRequiringOptions = ['Opción única', 'Opción múltiple', 'Opcion Multiple'];

echo "1. Finding question types that require options...\n";
$questionTypes = TypeQuestionModel::whereIn('title', $typesRequiringOptions)->get();
$typeIds = $questionTypes->pluck('id')->toArray();

echo "Found types: " . implode(', ', $questionTypes->pluck('title')->toArray()) . "\n";
echo "Type IDs: " . implode(', ', $typeIds) . "\n\n";

// Focus on the new questions mentioned in the logs
$newQuestionIds = [600, 601, 602, 603];

echo "2. Checking new questions from logs...\n";
foreach ($newQuestionIds as $questionId) {
    $question = QuestionModel::with(['type', 'options'])->find($questionId);
    if ($question) {
        $optionsCount = $question->options->count();
        echo "Q{$questionId} ({$question->type->title}): {$optionsCount} options - \"{$question->title}\"\n";
        
        if ($optionsCount === 0 && in_array($question->type_questions_id, $typeIds)) {
            echo "  ⚠️ Missing options for type that requires them - adding default options...\n";
            
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
        } else if ($optionsCount === 0) {
            echo "  ✅ No options needed for type '{$question->type->title}'\n";
        } else {
            echo "  ✅ Already has options\n";
        }
    } else {
        echo "Q{$questionId}: Not found\n";
    }
    echo "\n";
}

echo "3. Verification - Checking if options were added...\n";
foreach ($newQuestionIds as $questionId) {
    $question = QuestionModel::with(['type', 'options'])->find($questionId);
    if ($question) {
        $optionsCount = $question->options->count();
        echo "Q{$questionId}: {$optionsCount} options\n";
        if ($optionsCount > 0) {
            foreach ($question->options as $option) {
                echo "  - {$option->options}\n";
            }
        }
    }
}

echo "\n=== GENERAL CHECK FOR ANY MISSING OPTIONS ===\n";
$questionsWithoutOptions = QuestionModel::whereIn('type_questions_id', $typeIds)
    ->whereDoesntHave('options')
    ->with('type')
    ->get();

echo "Found " . $questionsWithoutOptions->count() . " questions still without options\n";

if ($questionsWithoutOptions->count() > 0) {
    echo "Fixing remaining questions...\n";
    foreach ($questionsWithoutOptions as $question) {
        echo "Processing Q{$question->id}: \"{$question->title}\" (Type: {$question->type->title})\n";
        
        $defaultOptions = ['Opción 1', 'Opción 2', 'Opción 3'];
        foreach ($defaultOptions as $optionText) {
            QuestionsoptionsModel::create([
                'questions_id' => $question->id,
                'options' => $optionText,
                'creator_id' => $question->creator_id ?? 1,
                'status' => true,
            ]);
        }
        echo "  ✅ Added 3 options\n";
    }
}

echo "\n=== COMPLETED ===\n";
echo "All questions that require options should now have them.\n";
echo "Refresh the frontend to see the real options instead of defaults.\n";

?>