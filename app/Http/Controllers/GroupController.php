<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\GroupModel;
use App\Models\GroupUserModel;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GroupController extends Controller
{
    /**
     * Obtener todos los grupos - Método híbrido
     */
    public function index()
    {
        try {
            $groups = GroupModel::with('users')->get()->map(function ($group) {
                // Contar usuarios de ambos métodos de almacenamiento
                $usersFromTable = $group->users->count();
                $usersFromArray = is_array($group->users_data) ? count($group->users_data) : 0;
                
                // Usar la fuente que tenga más usuarios (generalmente será el método principal)
                $totalUsers = max($usersFromTable, $usersFromArray);
                
                // Si el grupo tiene users_data, usar ese conteo; si no, usar la tabla
                $finalCount = $usersFromArray > 0 ? $usersFromArray : $usersFromTable;
                
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'count' => $finalCount,
                    'user_count' => $group->user_count, // Campo directo del modelo
                    'users_from_table' => $usersFromTable,
                    'users_from_array' => $usersFromArray,
                    'storage_method' => $usersFromArray > 0 ? 'array' : 'table',
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at
                ];
            });

            return response()->json($groups, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:groups,name',
                'description' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $group = GroupModel::create([
                'name' => $request->name,
                'description' => $request->description ?? 'Sin descripción',
                'created_by' => Auth::id() ?? 1, // Default user if not authenticated
                'user_count' => 0
            ]);

            return response()->json([
                'message' => 'Grupo creado exitosamente',
                'group' => $group
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al crear el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener un grupo específico con sus usuarios
     */
    public function show($id)
    {
        try {
            $group = GroupModel::with('users')->find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            return response()->json([
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'count' => $group->users->count(),
                'users' => $group->users,
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar un grupo específico
     */
    public function update(Request $request, $id)
    {
        try {
            $group = GroupModel::find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:groups,name,' . $id,
                'description' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $group->update([
                'name' => $request->name,
                'description' => $request->description ?? $group->description
            ]);

            return response()->json([
                'message' => 'Grupo actualizado exitosamente',
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'user_count' => $group->user_count,
                    'updated_at' => $group->updated_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener usuarios de un grupo específico - Método híbrido
     */
    public function getGroupUsers($id)
    {
        try {
            $group = GroupModel::find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $users = [];
            $source = 'individual'; // Por defecto buscar en registros individuales

            // Primero verificar si hay usuarios en el array JSON
            if (!empty($group->users_data) && is_array($group->users_data)) {
                $users = collect($group->users_data)->map(function ($user) {
                    return [
                        'id' => $user['id'] ?? uniqid('user_', true),
                        'nombre' => $user['nombre'],
                        'correo' => $user['correo'],
                        'categoria' => $user['categoria'],
                        'fechaRegistro' => isset($user['created_at']) ? 
                            \Carbon\Carbon::parse($user['created_at'])->format('Y-m-d') : 
                            now()->format('Y-m-d'),
                        'created_at' => $user['created_at'] ?? now()->toISOString(),
                        'updated_at' => $user['updated_at'] ?? $user['created_at'] ?? now()->toISOString(),
                        'storage_type' => 'array'
                    ];
                })->toArray();
                $source = 'array';
            }

            // Si no hay usuarios en el array, buscar en registros individuales
            if (empty($users)) {
                $individualUsers = GroupUserModel::where('group_id', $id)->get();
                $users = $individualUsers->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'nombre' => $user->nombre,
                        'correo' => $user->correo,
                        'categoria' => $user->categoria,
                        'fechaRegistro' => $user->created_at->format('Y-m-d'),
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'storage_type' => 'individual'
                    ];
                })->toArray();
                $source = 'individual';
            }

            return response()->json([
                'data' => $users,
                'source' => $source,
                'count' => count($users),
                'group_info' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'user_count' => $group->user_count,
                    'has_array_data' => !empty($group->users_data)
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los usuarios del grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar usuario individual a un grupo - Método híbrido
     */
    public function addUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'correo' => 'required|email|max:255',
                'categoria' => 'required|string|max:100',
                'use_array_storage' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Buscar o crear el grupo basado en la categoría
            $group = GroupModel::firstOrCreate(
                ['name' => $request->categoria],
                [
                    'description' => 'Grupo creado automáticamente',
                    'created_by' => Auth::id() ?? 1,
                    'user_count' => 0,
                    'users_data' => []
                ]
            );

            $useArrayStorage = $request->get('use_array_storage', true);
            $currentUsersData = $group->users_data ?? [];

            // Verificar si el usuario ya existe
            if ($useArrayStorage) {
                $emailExists = collect($currentUsersData)->contains('correo', $request->correo);
            } else {
                $emailExists = GroupUserModel::where('group_id', $group->id)
                    ->where('correo', $request->correo)
                    ->exists();
            }

            if ($emailExists) {
                return response()->json([
                    'message' => 'El usuario ya existe en este grupo'
                ], 409);
            }

            $userRecord = [
                'id' => uniqid('user_', true),
                'nombre' => $request->nombre,
                'correo' => $request->correo,
                'categoria' => $request->categoria,
                'created_at' => now()->toISOString(),
                'created_by' => Auth::id() ?? 1
            ];

            if ($useArrayStorage) {
                // Agregar al array JSON
                $currentUsersData[] = $userRecord;
                $group->users_data = $currentUsersData;
                $group->user_count = count($currentUsersData);
                $group->save();

                $responseUser = $userRecord;
            } else {
                // Crear registro individual (método original)
                $groupUser = GroupUserModel::create([
                    'group_id' => $group->id,
                    'nombre' => $request->nombre,
                    'correo' => $request->correo,
                    'categoria' => $request->categoria,
                    'created_by' => Auth::id() ?? 1
                ]);

                // Actualizar contador del grupo
                $group->updateUserCount();

                $responseUser = $groupUser;
            }

            return response()->json([
                'message' => 'Usuario agregado exitosamente al grupo',
                'storage_method' => $useArrayStorage ? 'array' : 'individual',
                'user' => $responseUser,
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'user_count' => $group->user_count
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar usuario al grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar múltiples usuarios a grupos (desde CSV) - Método híbrido
     */
    public function addUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'groupName' => 'required|string|max:255',
                'users' => 'required|array|min:1',
                'users.*.nombre' => 'required|string|max:255',
                'users.*.correo' => 'required|email|max:255',
                'users.*.categoria' => 'required|string|max:100',
                'use_array_storage' => 'boolean' // Parámetro para elegir el método de almacenamiento
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Buscar o crear el grupo
                $group = GroupModel::firstOrCreate(
                    ['name' => $request->groupName],
                    [
                        'description' => 'Grupo creado automáticamente',
                        'created_by' => Auth::id() ?? 1,
                        'user_count' => 0,
                        'users_data' => []
                    ]
                );

                $useArrayStorage = $request->get('use_array_storage', true); // Por defecto usar array
                $addedUsers = [];
                $skippedUsers = [];
                $currentUsersData = $group->users_data ?? [];

                foreach ($request->users as $userData) {
                    // Verificar si el usuario ya existe
                    $emailExists = false;
                    
                    if ($useArrayStorage) {
                        // Verificar en el array JSON
                        $emailExists = collect($currentUsersData)->contains('correo', $userData['correo']);
                    } else {
                        // Verificar en la tabla individual
                        $emailExists = GroupUserModel::where('group_id', $group->id)
                            ->where('correo', $userData['correo'])
                            ->exists();
                    }

                    if ($emailExists) {
                        $skippedUsers[] = $userData['correo'];
                        continue;
                    }

                    $userRecord = [
                        'id' => uniqid('user_', true), // ID único para el array
                        'nombre' => $userData['nombre'],
                        'correo' => $userData['correo'],
                        'categoria' => $userData['categoria'],
                        'created_at' => now()->toISOString(),
                        'created_by' => Auth::id() ?? 1
                    ];

                    if ($useArrayStorage) {
                        // Agregar al array JSON
                        $currentUsersData[] = $userRecord;
                    } else {
                        // Crear registro individual (método original)
                        $groupUser = GroupUserModel::create([
                            'group_id' => $group->id,
                            'nombre' => $userData['nombre'],
                            'correo' => $userData['correo'],
                            'categoria' => $userData['categoria'],
                            'created_by' => Auth::id() ?? 1
                        ]);
                        $userRecord['id'] = $groupUser->id;
                    }

                    $addedUsers[] = $userRecord;
                }

                if ($useArrayStorage) {
                    // Actualizar el grupo con el array de usuarios
                    $group->users_data = $currentUsersData;
                    $group->user_count = count($currentUsersData);
                    $group->save();
                } else {
                    // Actualizar contador del grupo (método original)
                    $group->updateUserCount();
                }

                DB::commit();

                return response()->json([
                    'message' => 'Usuarios procesados exitosamente',
                    'storage_method' => $useArrayStorage ? 'array' : 'individual',
                    'group' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'user_count' => $group->user_count,
                        'users_count_from_array' => $useArrayStorage ? count($group->users_data ?? []) : null
                    ],
                    'added_count' => count($addedUsers),
                    'skipped_count' => count($skippedUsers),
                    'skipped_emails' => $skippedUsers
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar usuarios a los grupos',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualizar usuario en un grupo
     */
    public function updateUser(Request $request, $groupId, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'correo' => 'required|email|max:255',
                'categoria' => 'string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            $groupUser = GroupUserModel::where('group_id', $groupId)
                ->where('id', $userId)
                ->first();

            if (!$groupUser) {
                return response()->json([
                    'message' => 'Usuario no encontrado en el grupo'
                ], 404);
            }

            // Verificar si el nuevo correo ya existe en el grupo (excepto el usuario actual)
            $existingUser = GroupUserModel::where('group_id', $groupId)
                ->where('correo', $request->correo)
                ->where('id', '!=', $userId)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'El correo ya existe en este grupo'
                ], 409);
            }

            $groupUser->update([
                'nombre' => $request->nombre,
                'correo' => $request->correo,
                'categoria' => $request->categoria ?? $groupUser->categoria
            ]);

            return response()->json([
                'message' => 'Usuario actualizado exitosamente',
                'user' => $groupUser
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar usuario de un grupo
     */
    public function deleteUser($groupId, $userId)
    {
        try {
            $groupUser = GroupUserModel::where('group_id', $groupId)
                ->where('id', $userId)
                ->first();

            if (!$groupUser) {
                return response()->json([
                    'message' => 'Usuario no encontrado en el grupo'
                ], 404);
            }

            $groupUser->delete();

            // Actualizar contador del grupo
            $group = GroupModel::find($groupId);
            if ($group) {
                $group->updateUserCount();
            }

            return response()->json([
                'message' => 'Usuario eliminado exitosamente del grupo'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el usuario del grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un grupo completo
     */
    public function destroy($id)
    {
        try {
            $group = GroupModel::find($id);

            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            DB::beginTransaction();

            try {
                // Eliminar todos los usuarios del grupo
                GroupUserModel::where('group_id', $id)->delete();
                
                // Eliminar el grupo
                $group->delete();

                DB::commit();

                return response()->json([
                    'message' => 'Grupo eliminado exitosamente'
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}