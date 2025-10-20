<?php

/**
 * Muestra el fingerprint del dispositivo actual
 * Ejecutar desde el navegador para ver el fingerprint
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$request = request();
$userAgent = $request->userAgent();

// Generar fingerprint
$fingerprintString = $userAgent;
$fingerprint = substr(hash('sha256', $fingerprintString), 0, 8);

echo "<h1>Device Fingerprint Info</h1>";
echo "<p><strong>User-Agent:</strong> {$userAgent}</p>";
echo "<p><strong>Fingerprint:</strong> <code>{$fingerprint}</code></p>";
echo "<p><strong>IP Address:</strong> {$request->ip()}</p>";
echo "<hr>";
echo "<p>Este fingerprint identifica tu navegador de forma única.</p>";
echo "<p>Si abres este mismo archivo desde un navegador diferente, el fingerprint será diferente.</p>";
