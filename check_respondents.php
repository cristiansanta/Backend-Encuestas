<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar en survey_respondents para este email específico
$respondents = DB::table('survey_respondents')
    ->where('respondent_email', 'andrwgmez68@gmail.com')
    ->orderBy('created_at', 'desc')
    ->get();

echo "=== SURVEY RESPONDENTS PARA andrwgmez68@gmail.com ===" . PHP_EOL;
foreach ($respondents as $respondent) {
    echo "ID: {$respondent->id}" . PHP_EOL;
    echo "Email: {$respondent->respondent_email}" . PHP_EOL;
    echo "Nombre: {$respondent->respondent_name}" . PHP_EOL;
    echo "Survey ID: {$respondent->survey_id}" . PHP_EOL;
    echo "Status: {$respondent->status}" . PHP_EOL;
    echo "Created: {$respondent->created_at}" . PHP_EOL;
    echo "Email Token: {$respondent->email_token}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

if (count($respondents) == 0) {
    echo "No se encontraron respondents para andrwgmez68@gmail.com" . PHP_EOL;
}
