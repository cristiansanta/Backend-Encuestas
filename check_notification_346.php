<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar la notificación ID 346 que se está usando como fuente
$notification = DB::table('notificationsurvays')->where('id', 346)->first();

if ($notification) {
    echo "=== NOTIFICACIÓN ID 346 (FUENTE DEL EMAIL) ===" . PHP_EOL;
    echo "ID: {$notification->id}" . PHP_EOL;
    echo "Destinatario: {$notification->destinatario}" . PHP_EOL;
    
    if (isset($notification->respondent_name)) {
        echo "Respondent Name: {$notification->respondent_name}" . PHP_EOL;
    } else {
        echo "Respondent Name: [NO EXISTE]" . PHP_EOL;
    }
    
    echo "Survey ID: {$notification->id_survey}" . PHP_EOL;
    echo "Asunto: {$notification->asunto}" . PHP_EOL;
    echo "Created: {$notification->created_at}" . PHP_EOL;
    
    // Ver si hay un cuerpo del email
    if ($notification->body) {
        $body_preview = substr($notification->body, 0, 200);
        echo "Body preview: " . $body_preview . "..." . PHP_EOL;
        
        // Buscar el nombre en el cuerpo del email
        if (strpos($notification->body, 'Ana Gómez') !== false) {
            echo "⚠️  PROBLEMA ENCONTRADO: El cuerpo contiene 'Ana Gómez'" . PHP_EOL;
        }
        if (strpos($notification->body, 'andrw') !== false) {
            echo "✅ El cuerpo contiene 'andrw'" . PHP_EOL;
        }
    }
} else {
    echo "No se encontró la notificación ID 346" . PHP_EOL;
}
