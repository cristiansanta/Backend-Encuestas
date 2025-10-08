<?php
require 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Buscar en group_users para este email específico
$groupUsers = DB::table('group_users')
    ->where('correo', 'andrwgmez68@gmail.com')
    ->get();

echo "=== GROUP USERS PARA andrwgmez68@gmail.com ===" . PHP_EOL;
foreach ($groupUsers as $user) {
    echo "ID: {$user->id}" . PHP_EOL;
    echo "Email/Correo: {$user->correo}" . PHP_EOL;
    echo "Nombre: {$user->nombre}" . PHP_EOL;
    if (isset($user->group_id)) {
        echo "Group ID: {$user->group_id}" . PHP_EOL;
    }
    echo "Created: {$user->created_at}" . PHP_EOL;
    echo "---" . PHP_EOL;
}

if (count($groupUsers) == 0) {
    echo "No se encontraron group_users para andrwgmez68@gmail.com" . PHP_EOL;
}
