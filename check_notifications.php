<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar notificaciones para este email específico
$notifications = DB::table('notificationsurvays')
    ->where('email', 'andrwgmez68@gmail.com')
    ->orderBy('created_at', 'desc')
    ->get();

echo "=== NOTIFICACIONES PARA andrwgmez68@gmail.com ===" . PHP_EOL;
foreach ($notifications as $notification) {
    echo "ID: {$notification->id}" . PHP_EOL;
    echo "Email: {$notification->email}" . PHP_EOL;
    echo "Nombre: {$notification->name}" . PHP_EOL;
    echo "Survey ID: {$notification->survey_id}" . PHP_EOL;
    echo "Status: {$notification->status}" . PHP_EOL;
    echo "Created: {$notification->created_at}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

// Buscar también en survey_respondents si existe
try {
    $respondents = DB::table('survey_respondents')
        ->where('email', 'andrwgmez68@gmail.com')
        ->get();
    
    echo "=== SURVEY RESPONDENTS PARA andrwgmez68@gmail.com ===" . PHP_EOL;
    foreach ($respondents as $respondent) {
        echo "ID: {$respondent->id}" . PHP_EOL;
        echo "Email: {$respondent->email}" . PHP_EOL;
        echo "Name: {$respondent->name}" . PHP_EOL;
        echo "Survey ID: {$respondent->survey_id}" . PHP_EOL;
        echo "Created: {$respondent->created_at}" . PHP_EOL;
        echo "---" . PHP_EOL;
    }
} catch (Exception $e) {
    echo "Tabla survey_respondents no existe o error: " . $e->getMessage() . PHP_EOL;
}
