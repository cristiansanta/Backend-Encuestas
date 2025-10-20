<?php

/**
 * Script para sincronizar users_data (JSON) con la tabla group_users
 * El JSON se actualiza para reflejar exactamente lo que está en la tabla
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\GroupModel;
use App\Models\GroupUserModel;
use Illuminate\Support\Facades\DB;

echo "=== Sincronizando JSON con tabla group_users ===\n\n";

try {
    DB::beginTransaction();

    $groups = GroupModel::all();

    foreach ($groups as $group) {
        echo "Grupo: {$group->name} (ID: {$group->id})\n";

        // Obtener usuarios de la tabla (fuente de verdad)
        $usersInTable = GroupUserModel::where('group_id', $group->id)->get();

        echo "  - Usuarios en tabla: " . $usersInTable->count() . "\n";
        echo "  - Usuarios en JSON: " . (is_array($group->users_data) ? count($group->users_data) : 0) . "\n";

        // Construir el array JSON desde la tabla
        $newUsersData = $usersInTable->map(function ($user) {
            return [
                'id' => $user->id,
                'nombre' => $user->nombre,
                'correo' => $user->correo,
                'categoria' => $user->categoria,
                'tipo_documento' => $user->tipo_documento,
                'numero_documento' => $user->numero_documento,
                'regional' => $user->regional,
                'centro_formacion' => $user->centro_formacion,
                'programa_formacion' => $user->programa_formacion,
                'ficha_grupo' => $user->ficha_grupo,
                'tipo_caracterizacion' => $user->tipo_caracterizacion,
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
                'created_by' => $user->created_by
            ];
        })->toArray();

        // Actualizar el grupo
        $group->users_data = $newUsersData;
        $group->user_count = count($newUsersData);
        $group->save();

        echo "  ✓ Sincronizado: {$usersInTable->count()} usuarios\n\n";
    }

    DB::commit();

    echo "=== Sincronización completada ===\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n!!! ERROR DURANTE LA SINCRONIZACIÓN !!!\n";
    echo $e->getMessage() . "\n";
}
