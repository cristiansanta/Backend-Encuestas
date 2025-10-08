<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar todas las notificaciones para la encuesta 577
$notifications = DB::table('notificationsurvays')
    ->where('id_survey', 577)
    ->orderBy('created_at', 'desc')
    ->get();

echo "=== TODAS LAS NOTIFICACIONES PARA ENCUESTA 577 ===" . PHP_EOL;
foreach ($notifications as $notification) {
    echo "ID: {$notification->id}" . PHP_EOL;
    echo "Destinatario: " . ($notification->destinatario ?? 'NULL') . PHP_EOL;
    
    if (isset($notification->respondent_name)) {
        echo "Respondent Name: {$notification->respondent_name}" . PHP_EOL;
    } else {
        echo "Respondent Name: [NO EXISTE]" . PHP_EOL;
    }
    
    echo "Asunto: " . ($notification->asunto ?? 'NULL') . PHP_EOL;
    echo "Created: {$notification->created_at}" . PHP_EOL;
    
    // Verificar si tiene cuerpo de email
    if (isset($notification->body) && $notification->body) {
        if (strpos($notification->body, 'Ana Gómez') !== false) {
            echo "⚠️  PROBLEMA: Contiene 'Ana Gómez' en el cuerpo" . PHP_EOL;
        }
        if (strpos($notification->body, 'andrw') !== false) {
            echo "✅ Contiene 'andrw' en el cuerpo" . PHP_EOL;
        }
    }
    echo "---" . PHP_EOL;
}

if (count($notifications) == 0) {
    echo "No se encontraron notificaciones para la encuesta 577" . PHP_EOL;
}
