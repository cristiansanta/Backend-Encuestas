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
                
                // Sumar ambos conteos para obtener el total real
                $totalUsers = $usersFromTable + $usersFromArray;
                
                // El conteo final es la suma de ambos métodos de almacenamiento
                $finalCount = $totalUsers;
                
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'count' => $finalCount,
                    'user_count' => $group->user_count, // Campo directo del modelo
                    'users_count' => $finalCount, // Campo que espera el frontend
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
     * Crear un nuevo grupo con usuarios opcionales
     */
    public function store(Request $request)
    {
        try {
            // Aumentar límite de tiempo para importaciones grandes
            set_time_limit(300); // 5 minutos para importaciones grandes
            ini_set('memory_limit', '512M'); // Aumentar memoria disponible
            
            // Log de datos recibidos para debug
            \Log::info('GroupController store - Datos recibidos:', $request->all());
            
            // Normalizar los datos: convertir claves de usuarios a minúsculas
            $requestData = $request->all();
            if (isset($requestData['users']) && is_array($requestData['users'])) {
                $requestData['users'] = array_map(function($user) {
                    $normalizedUser = [];
                    foreach ($user as $key => $value) {
                        $normalizedKey = strtolower($key);
                        $normalizedUser[$normalizedKey] = $value;
                    }
                    return $normalizedUser;
                }, $requestData['users']);
            }
            
            $validator = Validator::make($requestData, [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'users' => 'nullable|array',
                'users.*.nombre' => 'required_with:users|string|max:255',
                'users.*.correo' => 'required_with:users|email|max:255',
                'users.*.categoria' => 'required_with:users|string|max:100',
                'users.*.tipo_documento' => 'nullable|string|max:100',
                'users.*.numero_documento' => 'nullable|string|max:100',
                'users.*.regional' => 'nullable|string|max:255',
                'users.*.centro_formacion' => 'nullable|string|max:255',
                'users.*.programa_formacion' => 'nullable|string|max:255',
                'users.*.ficha_grupo' => 'nullable|string|max:255',
                'users.*.tipo_caracterizacion' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                \Log::error('GroupController store - Validación fallida:', $validator->errors()->toArray());
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors(),
                    'received_data' => $request->all() // Incluir datos recibidos para debug
                ], 422);
            }

            DB::beginTransaction();

            try {
                // Verificar si ya existe un grupo con ese nombre
                $existingGroup = GroupModel::where('name', $requestData['name'])->first();
                
                if ($existingGroup) {
                    // Si ya existe, generar un nombre único
                    $baseName = $requestData['name'];
                    $counter = 1;
                    do {
                        $newName = $baseName . " ({$counter})";
                        $nameExists = GroupModel::where('name', $newName)->exists();
                        $counter++;
                    } while ($nameExists);
                    
                    $groupName = $newName;
                } else {
                    $groupName = $requestData['name'];
                }
                
                // Crear el grupo
                $group = GroupModel::create([
                    'name' => $groupName,
                    'description' => $requestData['description'] ?? 'Sin descripción',
                    'created_by' => Auth::id() ?? 1,
                    'user_count' => 0,
                    'users_data' => []
                ]);

                $addedUsers = [];
                
                // Si se proporcionaron usuarios, agregarlos
                if (isset($requestData['users']) && is_array($requestData['users'])) {
                    $usersData = [];
                    $userCount = count($requestData['users']);
                    
                    // Log para monitorear el progreso
                    \Log::info("GroupController store - Procesando {$userCount} usuarios");
                    
                    // Procesar usuarios en lotes para optimizar memoria
                    $batchSize = 500;
                    $batches = array_chunk($requestData['users'], $batchSize);
                    
                    foreach ($batches as $batchIndex => $batch) {
                        \Log::info("GroupController store - Procesando lote " . ($batchIndex + 1) . " de " . count($batches));
                        
                        foreach ($batch as $userData) {
                            $userRecord = [
                                'id' => uniqid('user_', true),
                                'nombre' => $userData['nombre'],
                                'correo' => $userData['correo'],
                                'categoria' => $userData['categoria'],
                                'tipo_documento' => $userData['tipo_documento'] ?? null,
                                'numero_documento' => $userData['numero_documento'] ?? null,
                                'regional' => $userData['regional'] ?? null,
                                'centro_formacion' => $userData['centro_formacion'] ?? null,
                                'programa_formacion' => $userData['programa_formacion'] ?? null,
                                'ficha_grupo' => $userData['ficha_grupo'] ?? null,
                                'tipo_caracterizacion' => $userData['tipo_caracterizacion'] ?? null,
                                'created_at' => now()->toISOString(),
                                'created_by' => Auth::id() ?? 1
                            ];
                            
                            $usersData[] = $userRecord;
                            $addedUsers[] = $userRecord;
                        }
                        
                        // Limpiar memoria después de cada lote
                        if (function_exists('gc_collect_cycles')) {
                            gc_collect_cycles();
                        }
                    }
                    
                    // Guardar usuarios en el array JSON
                    $group->users_data = $usersData;
                    $group->user_count = count($usersData);
                    $group->save();
                    
                    \Log::info("GroupController store - Usuarios procesados exitosamente: {$userCount}");
                }

                DB::commit();

                return response()->json([
                    'message' => 'Grupo creado exitosamente',
                    'group' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'user_count' => $group->user_count,
                        'users_count' => count($addedUsers),
                        'created_at' => $group->created_at,
                        'updated_at' => $group->updated_at
                    ],
                    'users_added' => count($addedUsers)
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

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
                        'tipo_documento' => $user['tipo_documento'] ?? null,
                        'numero_documento' => $user['numero_documento'] ?? null,
                        'regional' => $user['regional'] ?? null,
                        'centro_formacion' => $user['centro_formacion'] ?? null,
                        'programa_formacion' => $user['programa_formacion'] ?? null,
                        'ficha_grupo' => $user['ficha_grupo'] ?? null,
                        'tipo_caracterizacion' => $user['tipo_caracterizacion'] ?? null,
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
                        'tipo_documento' => $user->tipo_documento,
                        'numero_documento' => $user->numero_documento,
                        'regional' => $user->regional,
                        'centro_formacion' => $user->centro_formacion,
                        'programa_formacion' => $user->programa_formacion,
                        'ficha_grupo' => $user->ficha_grupo,
                        'tipo_caracterizacion' => $user->tipo_caracterizacion,
                        'fechaRegistro' => $user->created_at->format('Y-m-d'),
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                        'storage_type' => 'individual'
                    ];
                })->toArray();
                $source = 'individual';
            }

            return response()->json($users, 200);

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
                'tipo_documento' => 'required|string|max:100',
                'numero_documento' => 'required|string|max:100',
                'regional' => 'nullable|string|max:255',
                'centro_formacion' => 'nullable|string|max:255',
                'programa_formacion' => 'nullable|string|max:255',
                'ficha_grupo' => 'nullable|string|max:255',
                'tipo_caracterizacion' => 'nullable|string|max:255',
                'grupo_id' => 'nullable|integer|exists:groups,id',
                'use_array_storage' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Si se proporciona grupo_id, usar ese grupo; si no, buscar o crear por categoría
            if ($request->has('grupo_id') && $request->grupo_id) {
                $group = GroupModel::find($request->grupo_id);
                if (!$group) {
                    return response()->json([
                        'message' => 'Grupo no encontrado'
                    ], 404);
                }
            } else {
                // Buscar o crear el grupo basado en la categoría (comportamiento original)
                $group = GroupModel::firstOrCreate(
                    ['name' => $request->categoria],
                    [
                        'description' => 'Grupo creado automáticamente',
                        'created_by' => Auth::id() ?? 1,
                        'user_count' => 0,
                        'users_data' => []
                    ]
                );
            }

            // Detectar automáticamente el método de almacenamiento
            $currentUsersData = $group->users_data ?? [];
            $hasArrayData = !empty($currentUsersData) && is_array($currentUsersData);
            $hasTableData = $group->users()->count() > 0;
            
            // Si el grupo ya tiene datos en users_data, usar array storage
            // Si no, usar el parámetro proporcionado o array por defecto
            $useArrayStorage = $hasArrayData ? true : $request->get('use_array_storage', true);

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
                'tipo_documento' => $request->tipo_documento,
                'numero_documento' => $request->numero_documento,
                'regional' => $request->regional,
                'centro_formacion' => $request->centro_formacion,
                'programa_formacion' => $request->programa_formacion,
                'ficha_grupo' => $request->ficha_grupo,
                'tipo_caracterizacion' => $request->tipo_caracterizacion,
                'created_at' => now()->toISOString(),
                'created_by' => Auth::id() ?? 1
            ];

            if ($useArrayStorage) {
                // Agregar al array JSON
                $currentUsersData[] = $userRecord;
                $group->users_data = $currentUsersData;
                $group->user_count = count($currentUsersData);
                $group->save();

                // Formatear respuesta para que coincida con lo que espera el frontend
                $responseUser = [
                    'id' => $userRecord['id'],
                    'nombre' => $userRecord['nombre'],
                    'correo' => $userRecord['correo'],
                    'categoria' => $userRecord['categoria'],
                    'tipo_documento' => $userRecord['tipo_documento'],
                    'numero_documento' => $userRecord['numero_documento'],
                    'regional' => $userRecord['regional'],
                    'centro_formacion' => $userRecord['centro_formacion'],
                    'programa_formacion' => $userRecord['programa_formacion'],
                    'ficha_grupo' => $userRecord['ficha_grupo'],
                    'tipo_caracterizacion' => $userRecord['tipo_caracterizacion'],
                    'created_at' => $userRecord['created_at'],
                    'updated_at' => $userRecord['created_at']
                ];
            } else {
                // Crear registro individual (método original)
                $groupUser = GroupUserModel::create([
                    'group_id' => $group->id,
                    'nombre' => $request->nombre,
                    'correo' => $request->correo,
                    'categoria' => $request->categoria,
                    'tipo_documento' => $request->tipo_documento,
                    'numero_documento' => $request->numero_documento,
                    'regional' => $request->regional,
                    'centro_formacion' => $request->centro_formacion,
                    'programa_formacion' => $request->programa_formacion,
                    'ficha_grupo' => $request->ficha_grupo,
                    'tipo_caracterizacion' => $request->tipo_caracterizacion,
                    'created_by' => Auth::id() ?? 1
                ]);

                // Actualizar contador del grupo
                $group->updateUserCount();

                $responseUser = [
                    'id' => $groupUser->id,
                    'nombre' => $groupUser->nombre,
                    'correo' => $groupUser->correo,
                    'categoria' => $groupUser->categoria,
                    'tipo_documento' => $groupUser->tipo_documento,
                    'numero_documento' => $groupUser->numero_documento,
                    'regional' => $groupUser->regional,
                    'centro_formacion' => $groupUser->centro_formacion,
                    'programa_formacion' => $groupUser->programa_formacion,
                    'ficha_grupo' => $groupUser->ficha_grupo,
                    'tipo_caracterizacion' => $groupUser->tipo_caracterizacion,
                    'created_at' => $groupUser->created_at,
                    'updated_at' => $groupUser->updated_at
                ];
            }

            return response()->json($responseUser, 201);

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
                'users.*.tipo_documento' => 'required|string|max:100',
                'users.*.numero_documento' => 'required|string|max:100',
                'users.*.regional' => 'nullable|string|max:255',
                'users.*.centro_formacion' => 'nullable|string|max:255',
                'users.*.programa_formacion' => 'nullable|string|max:255',
                'users.*.ficha_grupo' => 'nullable|string|max:255',
                'users.*.tipo_caracterizacion' => 'nullable|string|max:255',
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
                        'tipo_documento' => $userData['tipo_documento'],
                        'numero_documento' => $userData['numero_documento'],
                        'regional' => $userData['regional'] ?? null,
                        'centro_formacion' => $userData['centro_formacion'] ?? null,
                        'programa_formacion' => $userData['programa_formacion'] ?? null,
                        'ficha_grupo' => $userData['ficha_grupo'] ?? null,
                        'tipo_caracterizacion' => $userData['tipo_caracterizacion'] ?? null,
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
                            'tipo_documento' => $userData['tipo_documento'],
                            'numero_documento' => $userData['numero_documento'],
                            'regional' => $userData['regional'] ?? null,
                            'centro_formacion' => $userData['centro_formacion'] ?? null,
                            'programa_formacion' => $userData['programa_formacion'] ?? null,
                            'ficha_grupo' => $userData['ficha_grupo'] ?? null,
                            'tipo_caracterizacion' => $userData['tipo_caracterizacion'] ?? null,
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
     * Actualizar usuario en un grupo - Método híbrido
     */
    public function updateUser(Request $request, $groupId, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nombre' => 'required|string|max:255',
                'correo' => 'required|email|max:255',
                'categoria' => 'string|max:100',
                'tipo_documento' => 'required|string|max:100',
                'numero_documento' => 'required|string|max:100',
                'regional' => 'nullable|string|max:255',
                'centro_formacion' => 'nullable|string|max:255',
                'programa_formacion' => 'nullable|string|max:255',
                'ficha_grupo' => 'nullable|string|max:255',
                'tipo_caracterizacion' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Datos de validación incorrectos',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Obtener el grupo
            $group = GroupModel::find($groupId);
            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $userFound = false;
            $updatedUser = null;

            // Verificar si el usuario está en users_data (array JSON)
            $usersData = $group->users_data ?? [];
            if (is_array($usersData) && count($usersData) > 0) {
                foreach ($usersData as $index => $user) {
                    if (isset($user['id']) && $user['id'] === $userId) {
                        // Verificar si el nuevo correo ya existe (excepto el usuario actual)
                        $emailExists = false;
                        foreach ($usersData as $otherUser) {
                            if ($otherUser['id'] !== $userId && $otherUser['correo'] === $request->correo) {
                                $emailExists = true;
                                break;
                            }
                        }

                        if ($emailExists) {
                            return response()->json([
                                'message' => 'El correo ya existe en este grupo'
                            ], 409);
                        }

                        // Actualizar usuario en el array
                        $usersData[$index]['nombre'] = $request->nombre;
                        $usersData[$index]['correo'] = $request->correo;
                        $usersData[$index]['categoria'] = $request->categoria ?? $user['categoria'];
                        $usersData[$index]['tipo_documento'] = $request->tipo_documento ?? $user['tipo_documento'];
                        $usersData[$index]['numero_documento'] = $request->numero_documento ?? $user['numero_documento'];
                        $usersData[$index]['regional'] = $request->regional ?? $user['regional'];
                        $usersData[$index]['centro_formacion'] = $request->centro_formacion ?? $user['centro_formacion'];
                        $usersData[$index]['programa_formacion'] = $request->programa_formacion ?? $user['programa_formacion'];
                        $usersData[$index]['ficha_grupo'] = $request->ficha_grupo ?? $user['ficha_grupo'];
                        $usersData[$index]['tipo_caracterizacion'] = $request->tipo_caracterizacion ?? $user['tipo_caracterizacion'];
                        $usersData[$index]['updated_at'] = now()->toISOString();

                        $group->users_data = $usersData;
                        $group->save();

                        $updatedUser = [
                            'id' => $usersData[$index]['id'],
                            'nombre' => $usersData[$index]['nombre'],
                            'correo' => $usersData[$index]['correo'],
                            'categoria' => $usersData[$index]['categoria'],
                            'tipo_documento' => $usersData[$index]['tipo_documento'],
                            'numero_documento' => $usersData[$index]['numero_documento'],
                            'regional' => $usersData[$index]['regional'],
                            'centro_formacion' => $usersData[$index]['centro_formacion'],
                            'programa_formacion' => $usersData[$index]['programa_formacion'],
                            'ficha_grupo' => $usersData[$index]['ficha_grupo'],
                            'tipo_caracterizacion' => $usersData[$index]['tipo_caracterizacion'],
                            'created_at' => $usersData[$index]['created_at'],
                            'updated_at' => $usersData[$index]['updated_at']
                        ];
                        $userFound = true;
                        break;
                    }
                }
            }

            // Si no se encontró en users_data, buscar en la tabla individual
            if (!$userFound) {
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
                    'categoria' => $request->categoria ?? $groupUser->categoria,
                    'tipo_documento' => $request->tipo_documento ?? $groupUser->tipo_documento,
                    'numero_documento' => $request->numero_documento ?? $groupUser->numero_documento,
                    'regional' => $request->regional ?? $groupUser->regional,
                    'centro_formacion' => $request->centro_formacion ?? $groupUser->centro_formacion,
                    'programa_formacion' => $request->programa_formacion ?? $groupUser->programa_formacion,
                    'ficha_grupo' => $request->ficha_grupo ?? $groupUser->ficha_grupo,
                    'tipo_caracterizacion' => $request->tipo_caracterizacion ?? $groupUser->tipo_caracterizacion
                ]);

                $updatedUser = [
                    'id' => $groupUser->id,
                    'nombre' => $groupUser->nombre,
                    'correo' => $groupUser->correo,
                    'categoria' => $groupUser->categoria,
                    'tipo_documento' => $groupUser->tipo_documento,
                    'numero_documento' => $groupUser->numero_documento,
                    'regional' => $groupUser->regional,
                    'centro_formacion' => $groupUser->centro_formacion,
                    'programa_formacion' => $groupUser->programa_formacion,
                    'ficha_grupo' => $groupUser->ficha_grupo,
                    'tipo_caracterizacion' => $groupUser->tipo_caracterizacion,
                    'created_at' => $groupUser->created_at,
                    'updated_at' => $groupUser->updated_at
                ];
            }

            return response()->json($updatedUser, 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al actualizar el usuario',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar usuario de un grupo - Método híbrido
     */
    public function deleteUser($groupId, $userId)
    {
        try {
            // Obtener el grupo
            $group = GroupModel::find($groupId);
            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado'
                ], 404);
            }

            $userFound = false;

            // Verificar si el usuario está en users_data (array JSON)
            $usersData = $group->users_data ?? [];
            if (is_array($usersData) && count($usersData) > 0) {
                $filteredUsers = [];
                foreach ($usersData as $user) {
                    if (isset($user['id']) && $user['id'] === $userId) {
                        $userFound = true;
                        // No agregar este usuario al array filtrado (efectivamente lo elimina)
                    } else {
                        $filteredUsers[] = $user;
                    }
                }

                if ($userFound) {
                    // Actualizar el array de usuarios y el contador
                    $group->users_data = $filteredUsers;
                    $group->user_count = count($filteredUsers);
                    $group->save();

                    return response()->json([
                        'message' => 'Usuario eliminado exitosamente del grupo'
                    ], 200);
                }
            }

            // Si no se encontró en users_data, buscar en la tabla individual
            if (!$userFound) {
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
                $group->updateUserCount();

                return response()->json([
                    'message' => 'Usuario eliminado exitosamente del grupo'
                ], 200);
            }

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