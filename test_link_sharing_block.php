<?php

/**
 * Script de prueba para verificar el bloqueo de compartir enlaces
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Services\URLIntegrityService;
use App\Models\SurveyAccessToken;
use Illuminate\Support\Facades\DB;

echo "=== TEST: Bloqueo de Link Sharing ===\n\n";

// Limpiar registros de prueba previos
echo "Limpiando registros de prueba...\n";
SurveyAccessToken::where('survey_id', 999)->delete();

// SIMULAR ESCENARIO:
// Usuario A: usuarioA@test.com con Chrome
// Usuario B: usuarioB@test.com intenta usar el enlace de Usuario A

$surveyId = 999;
$emailA = 'usuarioA@test.com';
$emailB = 'usuarioB@test.com';

// 1. Generar hash para Usuario A
echo "\n1. Generando hash para Usuario A...\n";
$hashA = URLIntegrityService::generateHash($surveyId, $emailA, 'standard');
echo "   Hash generado: " . substr($hashA, 0, 20) . "...\n";

// 2. Simular primer acceso de Usuario A (Chrome)
echo "\n2. Simulando primer acceso de Usuario A (Chrome)...\n";

// Simular request de Usuario A
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0';
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

// Crear request simulado
app()->singleton('request', function() {
    return \Illuminate\Http\Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR']
    ]);
});

$result1 = URLIntegrityService::validateDeviceAccess($surveyId, $emailA, $hashA);
echo "   Resultado: " . ($result1['valid'] ? '✅ ACCESO PERMITIDO' : '❌ ACCESO DENEGADO') . "\n";
echo "   Primer acceso: " . ($result1['is_first_access'] ? 'SÍ' : 'NO') . "\n";

// Verificar que se registró
$token = SurveyAccessToken::where('survey_id', $surveyId)
    ->where('email', $emailA)
    ->first();

if ($token) {
    echo "   Token registrado:\n";
    echo "     - Email: {$token->email}\n";
    echo "     - Device fingerprint: {$token->device_fingerprint}\n";
    echo "     - IP: {$token->ip_address}\n";
    echo "     - Status: {$token->status}\n";
    echo "     - Access count: {$token->access_count}\n";
}

// 3. Simular segundo acceso de Usuario A (mismo navegador - DEBE PERMITIR)
echo "\n3. Simulando segundo acceso de Usuario A (mismo Chrome)...\n";

// Mismo User-Agent, misma IP
$result2 = URLIntegrityService::validateDeviceAccess($surveyId, $emailA, $hashA);
echo "   Resultado: " . ($result2['valid'] ? '✅ ACCESO PERMITIDO' : '❌ ACCESO DENEGADO') . "\n";

$token->refresh();
echo "   Access count actualizado: {$token->access_count}\n";
echo "   Status: {$token->status}\n";

// 4. Simular Usuario B intenta acceder con Firefox (DEBE BLOQUEAR)
echo "\n4. Simulando Usuario B intenta acceder con Firefox (LINK SHARING)...\n";

// Cambiar User-Agent a Firefox
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:120.0) Gecko/20100101 Firefox/120.0';
$_SERVER['REMOTE_ADDR'] = '192.168.1.200';

// Actualizar request
app()->singleton('request', function() {
    return \Illuminate\Http\Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR']
    ]);
});

$result3 = URLIntegrityService::validateDeviceAccess($surveyId, $emailA, $hashA);
echo "   Resultado: " . ($result3['valid'] ? '✅ ACCESO PERMITIDO' : '❌ ACCESO DENEGADO') . "\n";
echo "   Error type: " . ($result3['error_type'] ?? 'none') . "\n";

$token->refresh();
echo "   Status del token: {$token->status}\n";

// 5. Verificar que Usuario A también quedó bloqueado
echo "\n5. Verificando que Usuario A también quedó bloqueado...\n";

// Volver al User-Agent de Usuario A
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0';
$_SERVER['REMOTE_ADDR'] = '192.168.1.100';

app()->singleton('request', function() {
    return \Illuminate\Http\Request::create('/test', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'],
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR']
    ]);
});

$result4 = URLIntegrityService::validateDeviceAccess($surveyId, $emailA, $hashA);
echo "   Usuario A intenta acceder nuevamente: " . ($result4['valid'] ? '✅ PERMITIDO' : '❌ BLOQUEADO') . "\n";

echo "\n=== RESUMEN ===\n";
echo "Acceso 1 (Usuario A Chrome):     " . ($result1['valid'] ? '✅ PERMITIDO' : '❌ BLOQUEADO') . "\n";
echo "Acceso 2 (Usuario A Chrome):     " . ($result2['valid'] ? '✅ PERMITIDO' : '❌ BLOQUEADO') . "\n";
echo "Acceso 3 (Usuario B Firefox):    " . ($result3['valid'] ? '✅ PERMITIDO - ERROR' : '❌ BLOQUEADO - CORRECTO') . "\n";
echo "Acceso 4 (Usuario A Chrome post-block): " . ($result4['valid'] ? '✅ PERMITIDO - ERROR' : '❌ BLOQUEADO - CORRECTO') . "\n";

// Limpiar
echo "\nLimpiando registros de prueba...\n";
SurveyAccessToken::where('survey_id', 999)->delete();

echo "\n✅ Test completado\n";
