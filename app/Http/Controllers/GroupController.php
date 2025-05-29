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
     * Obtener todos los grupos
     */
    public function index()
    {
        try {
            $groups = GroupModel::with('users')->get()->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'count' => $group->users->count(),
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
     * Obtener usuarios de un grupo específico
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

            $users = GroupUserModel::where('group_id', $id)->get()->map(function ($user) {
                return [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'correo' => $user->correo,
                    'categoria' => $user->categoria,
                    'fechaRegistro' => $user->created_at->format('Y-m-d'),
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });

            return response()->json([
                'data' => $users
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al obtener los usuarios del grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar usuario individual a un grupo
     */
    public function addUser(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'correo' => 'required|email|max:255',
                'categoria' => 'required|string|max:100'
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
                    'user_count' => 0
                ]
            );

            // Verificar si el usuario ya existe en el grupo
            $existingUser = GroupUserModel::where('group_id', $group->id)
                ->where('correo', $request->correo)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'El usuario ya existe en este grupo'
                ], 409);
            }

            // Crear el usuario en el grupo
            $groupUser = GroupUserModel::create([
                'group_id' => $group->id,
                'nombre' => $request->nombre,
                'correo' => $request->correo,
                'categoria' => $request->categoria,
                'created_by' => Auth::id() ?? 1
            ]);

            // Actualizar contador del grupo
            $group->updateUserCount();

            return response()->json([
                'message' => 'Usuario agregado exitosamente al grupo',
                'user' => $groupUser,
                'group' => $group
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al agregar usuario al grupo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Agregar múltiples usuarios a grupos (desde CSV)
     */
    public function addUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'groupName' => 'required|string|max:255',
                'users' => 'required|array|min:1',
                'users.*.nombre' => 'required|string|max:255',
                'users.*.correo' => 'required|email|max:255',
                'users.*.categoria' => 'required|string|max:100'
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
                        'user_count' => 0
                    ]
                );

                $addedUsers = [];
                $skippedUsers = [];

                foreach ($request->users as $userData) {
                    // Verificar si el usuario ya existe en el grupo
                    $existingUser = GroupUserModel::where('group_id', $group->id)
                        ->where('correo', $userData['correo'])
                        ->first();

                    if ($existingUser) {
                        $skippedUsers[] = $userData['correo'];
                        continue;
                    }

                    // Crear el usuario en el grupo
                    $groupUser = GroupUserModel::create([
                        'group_id' => $group->id,
                        'nombre' => $userData['nombre'],
                        'correo' => $userData['correo'],
                        'categoria' => $userData['categoria'],
                        'created_by' => Auth::id() ?? 1
                    ]);

                    $addedUsers[] = $groupUser;
                }

                // Actualizar contador del grupo
                $group->updateUserCount();

                DB::commit();

                return response()->json([
                    'message' => 'Usuarios procesados exitosamente',
                    'group' => $group,
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