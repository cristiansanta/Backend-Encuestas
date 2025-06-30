<?php
/**
 * Debug script to check the exact API response structure
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\SurveyModel;
use App\Http\Controllers\SurveyController;
use Illuminate\Http\Request;

echo "=== DEBUGGING EXACT API RESPONSE ===\n\n";

// Test with survey ID 439
$surveyId = 439;

echo "Testing getSurveyQuestionsop for Survey ID: {$surveyId}\n\n";

// Simulate the exact controller method
$survey = SurveyModel::find($surveyId);
if ($survey) {
    $surveyQuestions = $survey->surveyQuestions()->with([
        'question.type',
        'question.options'
    ])->get();
    
    $response = [
        'survey_questions' => $surveyQuestions,
        'survey_info' => [
            'id' => $survey->id,
            'title' => $survey->title,
            'description' => $survey->descrip
        ]
    ];
    
    echo "1. Raw Laravel Collection Structure:\n";
    echo "Survey Questions count: " . $surveyQuestions->count() . "\n\n";
    
    echo "2. JSON Response Structure Analysis:\n";
    $jsonResponse = response()->json($response);
    $actualJson = $jsonResponse->getData(true); // Get as array
    
    echo "JSON structure keys: " . implode(', ', array_keys($actualJson)) . "\n\n";
    
    echo "3. Detailed Question Analysis:\n";
    foreach ($actualJson['survey_questions'] as $index => $surveyQuestion) {
        echo "Question {$index}: ID {$surveyQuestion['question']['id']}\n";
        echo "  Title: \"{$surveyQuestion['question']['title']}\"\n";
        echo "  Type: {$surveyQuestion['question']['type']['title']}\n";
        
        // Check if options key exists
        if (isset($surveyQuestion['question']['options'])) {
            echo "  Options key exists: YES\n";
            echo "  Options count: " . count($surveyQuestion['question']['options']) . "\n";
            echo "  Options type: " . gettype($surveyQuestion['question']['options']) . "\n";
            
            if (count($surveyQuestion['question']['options']) > 0) {
                echo "  Options content:\n";
                foreach ($surveyQuestion['question']['options'] as $optIndex => $option) {
                    echo "    [{$optIndex}] " . json_encode($option) . "\n";
                }
            } else {
                echo "  ❌ OPTIONS ARRAY IS EMPTY\n";
            }
        } else {
            echo "  ❌ OPTIONS KEY DOES NOT EXIST\n";
        }
        echo "\n";
    }
    
    echo "4. Raw JSON Output (first question only):\n";
    if (count($actualJson['survey_questions']) > 0) {
        echo json_encode($actualJson['survey_questions'][0], JSON_PRETTY_PRINT);
    }
    echo "\n\n";
    
    echo "5. Checking database directly for options:\n";
    foreach ($surveyQuestions as $sq) {
        $question = $sq->question;
        $dbOptions = \App\Models\QuestionsoptionsModel::where('questions_id', $question->id)->get();
        echo "Q{$question->id} database options: {$dbOptions->count()}\n";
        if ($dbOptions->count() > 0) {
            foreach ($dbOptions as $opt) {
                echo "  - {$opt->options}\n";
            }
        }
    }
    
} else {
    echo "❌ Survey {$surveyId} not found\n";
}

echo "\n=== DIAGNOSIS ===\n";
echo "If options exist in database but not in JSON response, then:\n";
echo "1. The relationship loading is failing\n";
echo "2. The JSON serialization is excluding options\n";
echo "3. There's a hidden configuration issue\n";

?>