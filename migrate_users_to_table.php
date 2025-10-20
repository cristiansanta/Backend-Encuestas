<?php

/**
 * Script para migrar usuarios de users_data (JSON) a la tabla group_users
 * Esto sincroniza los datos existentes para que estén en ambos lugares
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\GroupModel;
use App\Models\GroupUserModel;
use Illuminate\Support\Facades\DB;

echo "=== Iniciando migración de usuarios a tabla group_users ===\n\n";

try {
    DB::beginTransaction();

    // Obtener todos los grupos
    $groups = GroupModel::all();

    echo "Total de grupos encontrados: " . $groups->count() . "\n\n";

    $totalMigrated = 0;
    $totalSkipped = 0;
    $totalErrors = 0;

    foreach ($groups as $group) {
        echo "Procesando grupo: {$group->name} (ID: {$group->id})\n";

        $usersData = $group->users_data ?? [];

        if (empty($usersData) || !is_array($usersData)) {
            echo "  - Sin usuarios en array JSON\n";
            continue;
        }

        echo "  - Total usuarios en array: " . count($usersData) . "\n";

        $migratedInGroup = 0;
        $skippedInGroup = 0;
        $newUsersData = []; // Array actualizado con IDs de la tabla

        foreach ($usersData as $userData) {
            try {
                // Verificar si el usuario ya existe en la tabla (por correo O por documento)
                $existingUser = GroupUserModel::where('group_id', $group->id)
                    ->where('correo', $userData['correo'])
                    ->first();

                // Si no existe por correo, verificar por documento (debido a la restricción UNIQUE)
                if (!$existingUser && isset($userData['tipo_documento']) && isset($userData['numero_documento'])) {
                    $existingUser = GroupUserModel::where('tipo_documento', $userData['tipo_documento'])
                        ->where('numero_documento', $userData['numero_documento'])
                        ->first();

                    if ($existingUser) {
                        echo "    ⚠ Usuario con mismo documento ya existe en otro grupo: {$userData['correo']} (usando ID: {$existingUser->id})\n";
                    }
                }

                if ($existingUser) {
                    echo "    ✓ Usuario ya existe en tabla: {$userData['correo']}\n";

                    // Actualizar el array con el ID de la tabla
                    $userData['id'] = $existingUser->id;
                    $newUsersData[] = $userData;
                    $skippedInGroup++;
                    continue;
                }

                // Crear el usuario en la tabla
                $groupUser = GroupUserModel::create([
                    'group_id' => $group->id,
                    'nombre' => $userData['nombre'] ?? '',
                    'correo' => $userData['correo'] ?? '',
                    'categoria' => $userData['categoria'] ?? '',
                    'tipo_documento' => $userData['tipo_documento'] ?? null,
                    'numero_documento' => $userData['numero_documento'] ?? null,
                    'regional' => $userData['regional'] ?? null,
                    'centro_formacion' => $userData['centro_formacion'] ?? null,
                    'programa_formacion' => $userData['programa_formacion'] ?? null,
                    'ficha_grupo' => $userData['ficha_grupo'] ?? null,
                    'tipo_caracterizacion' => $userData['tipo_caracterizacion'] ?? null,
                    'created_by' => $userData['created_by'] ?? 1,
                    'created_at' => isset($userData['created_at']) ?
                        \Carbon\Carbon::parse($userData['created_at']) :
                        now(),
                    'updated_at' => isset($userData['updated_at']) ?
                        \Carbon\Carbon::parse($userData['updated_at']) :
                        now()
                ]);

                // Actualizar el ID en el array JSON para que coincida con la tabla
                $userData['id'] = $groupUser->id;
                $newUsersData[] = $userData;

                echo "    ✓ Migrado: {$userData['correo']} (ID tabla: {$groupUser->id})\n";
                $migratedInGroup++;

            } catch (\Exception $e) {
                echo "    ✗ Error con usuario {$userData['correo']}: " . $e->getMessage() . "\n";
                $totalErrors++;
                // Mantener el usuario en el array aunque falle
                $newUsersData[] = $userData;
            }
        }

        // Actualizar el grupo con los IDs sincronizados
        $group->users_data = $newUsersData;
        $group->user_count = count($newUsersData);
        $group->save();

        echo "  - Migrados en este grupo: {$migratedInGroup}\n";
        echo "  - Ya existían: {$skippedInGroup}\n\n";

        $totalMigrated += $migratedInGroup;
        $totalSkipped += $skippedInGroup;
    }

    DB::commit();

    echo "\n=== Migración completada ===\n";
    echo "Total usuarios migrados: {$totalMigrated}\n";
    echo "Total usuarios ya existían: {$totalSkipped}\n";
    echo "Total errores: {$totalErrors}\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n!!! ERROR DURANTE LA MIGRACIÓN !!!\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
