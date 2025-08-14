#!/usr/bin/env php
<?php

/*
 * Script para arreglar los permisos de las imágenes existentes
 * Debe ejecutarse como sudo o con permisos de www-data
 */

// Cargar el framework de Laravel
require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$imagesDir = storage_path('app/private/images/');

if (!is_dir($imagesDir)) {
    echo "Error: Directorio de imágenes no encontrado: $imagesDir\n";
    exit(1);
}

echo "Arreglando permisos de imágenes en: $imagesDir\n";

$files = glob($imagesDir . '*');
$fixed = 0;
$errors = 0;

foreach ($files as $file) {
    if (is_file($file)) {
        echo "Procesando: " . basename($file) . " - ";
        
        $currentPerms = fileperms($file);
        $currentPermsOctal = sprintf('%o', $currentPerms & 0777);
        
        echo "Permisos actuales: $currentPermsOctal - ";
        
        if (chmod($file, 0644)) {
            echo "✓ Arreglado\n";
            $fixed++;
        } else {
            echo "✗ Error al cambiar permisos\n";
            $errors++;
        }
    }
}

echo "\nResumen:\n";
echo "- Archivos procesados: " . count($files) . "\n";
echo "- Permisos arreglados: $fixed\n";
echo "- Errores: $errors\n";

if ($errors > 0) {
    echo "\nSi hay errores, ejecuta el script como sudo:\n";
    echo "sudo php " . __FILE__ . "\n";
    exit(1);
} else {
    echo "\n✓ Todos los permisos han sido arreglados exitosamente!\n";
    exit(0);
}