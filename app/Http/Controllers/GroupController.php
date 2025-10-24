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
     * Ahora solo cuenta de la tabla group_users (fuente de verdad única)
     * Respeta el permiso allow_view_other_users_groups
     */
    public function index()
    {
        try {
            // Obtener el usuario autenticado
            $user = Auth::user();

            if (!$user) {
                return response()->json([
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            $userId = $user->id;
            $userRole = $user->rol ?? '';

            // Verificar si el usuario tiene permiso para ver grupos de otros usuarios
            // Superadmin y Admin siempre pueden ver todos los grupos
            $canViewOtherUsersGroups = ($userRole === 'super_admin' || $userRole === 'admin')
                                        || ($user->allow_view_other_users_groups ?? false);

            // Construir query base
            $query = GroupModel::with('users');

            // Si NO tiene permiso para ver grupos de otros usuarios, filtrar solo sus grupos
            if (!$canViewOtherUsersGroups) {
                $query->where('created_by', $userId);
            }

            // Obtener grupos y mapear la respuesta
            $groups = $query->get()
                ->map(function ($group) {
                    // Contar SOLO de la tabla (fuente de verdad única)
                    $usersFromTable = $group->users->count();

                    return [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'count' => $usersFromTable,
                        'user_count' => $usersFromTable,
                        'users_count' => $usersFromTable, // Campo que espera el frontend
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
                'name' => 'required|string|max:30',
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

                    // NOTA: Los usuarios PUEDEN pertenecer a múltiples grupos
                    // No se valida duplicados - un mismo encuestado puede estar en varios grupos

                    // Procesar usuarios en lotes para optimizar memoria
                    $batchSize = 500;
                    $batches = array_chunk($requestData['users'], $batchSize);

                    // Rastrear correos procesados en memoria para evitar duplicados en el CSV
                    $processedEmails = [];

                    foreach ($batches as $batchIndex => $batch) {
                        \Log::info("GroupController store - Procesando lote " . ($batchIndex + 1) . " de " . count($batches));

                        foreach ($batch as $userData) {
                            $email = $userData['correo'];

                            // 1. Verificar si este correo ya fue procesado en ESTE request (duplicado en CSV)
                            if (isset($processedEmails[$email])) {
                                \Log::info("Usuario {$email} ya fue procesado en este request, omitiendo duplicado del CSV...");
                                continue;
                            }

                            // 2. Verificar si este usuario ya existe en ESTE grupo en la base de datos
                            $existsInThisGroup = GroupUserModel::where('group_id', $group->id)
                                ->where('correo', $email)
                                ->exists();

                            if ($existsInThisGroup) {
                                \Log::info("Usuario {$email} ya existe en este grupo en la BD, omitiendo...");
                                continue; // Skip este usuario, ya está en el grupo
                            }

                            // Marcar este correo como procesado
                            $processedEmails[$email] = true;

                            // Crear registro en la tabla group_users
                            $groupUser = GroupUserModel::create([
                                'group_id' => $group->id,
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
                                'created_by' => Auth::id() ?? 1
                            ]);

                            // También guardar en el array JSON usando el ID de la tabla
                            $userRecord = [
                                'id' => $groupUser->id, // Usar el ID real de la tabla
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
                                'created_at' => $groupUser->created_at->toISOString(),
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
                'name' => 'required|string|max:30|unique:groups,name,' . $id,
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
     * Obtener usuarios de un grupo específico
     * Ahora siempre devuelve de la tabla group_users (fuente de verdad única)
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

            // Obtener SIEMPRE de la tabla group_users (fuente de verdad)
            $users = GroupUserModel::where('group_id', $id)->get()->map(function ($user) {
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
                    'updated_at' => $user->updated_at
                ];
            })->toArray();

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

            // Verificar si el usuario ya existe en la tabla
            $emailExists = GroupUserModel::where('group_id', $group->id)
                ->where('correo', $request->correo)
                ->exists();

            if ($emailExists) {
                return response()->json([
                    'message' => 'El usuario ya existe en este grupo'
                ], 409);
            }

            // SIEMPRE guardar en ambos lugares: tabla y array JSON
            // 1. Primero crear en la tabla group_users
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

            // 2. También agregar al array JSON usando el ID de la tabla
            $currentUsersData = $group->users_data ?? [];
            $userRecord = [
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
                'created_at' => $groupUser->created_at->toISOString(),
                'created_by' => Auth::id() ?? 1
            ];

            $currentUsersData[] = $userRecord;
            $group->users_data = $currentUsersData;
            $group->user_count = count($currentUsersData);
            $group->save();

            // Formatear respuesta
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
                'groupName' => 'required|string|max:30',
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

                $addedUsers = [];
                $skippedUsers = [];
                $currentUsersData = $group->users_data ?? [];

                foreach ($request->users as $userData) {
                    // Verificar si el usuario ya existe en la tabla
                    $emailExists = GroupUserModel::where('group_id', $group->id)
                        ->where('correo', $userData['correo'])
                        ->exists();

                    if ($emailExists) {
                        $skippedUsers[] = $userData['correo'];
                        continue;
                    }

                    // SIEMPRE guardar en ambos lugares: tabla y array JSON
                    // 1. Crear en la tabla group_users
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

                    // 2. También agregar al array JSON usando el ID de la tabla
                    $userRecord = [
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
                        'created_at' => $groupUser->created_at->toISOString(),
                        'created_by' => Auth::id() ?? 1
                    ];

                    $currentUsersData[] = $userRecord;
                    $addedUsers[] = $userRecord;
                }

                // Actualizar el grupo con el array de usuarios y contador
                $group->users_data = $currentUsersData;
                $group->user_count = count($currentUsersData);
                $group->save();

                DB::commit();

                return response()->json([
                    'message' => 'Usuarios procesados exitosamente',
                    'storage_method' => 'dual', // Guardado en tabla y array
                    'group' => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'description' => $group->description,
                        'user_count' => $group->user_count,
                        'users_count_from_array' => count($group->users_data ?? [])
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

            // Buscar usuario en la tabla
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

            // SIEMPRE actualizar en ambos lugares
            // 1. Actualizar en la tabla
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

            // 2. Actualizar en el array JSON
            $usersData = $group->users_data ?? [];
            if (is_array($usersData) && count($usersData) > 0) {
                foreach ($usersData as $index => $user) {
                    if (isset($user['id']) && $user['id'] == $userId) {
                        $usersData[$index]['nombre'] = $groupUser->nombre;
                        $usersData[$index]['correo'] = $groupUser->correo;
                        $usersData[$index]['categoria'] = $groupUser->categoria;
                        $usersData[$index]['tipo_documento'] = $groupUser->tipo_documento;
                        $usersData[$index]['numero_documento'] = $groupUser->numero_documento;
                        $usersData[$index]['regional'] = $groupUser->regional;
                        $usersData[$index]['centro_formacion'] = $groupUser->centro_formacion;
                        $usersData[$index]['programa_formacion'] = $groupUser->programa_formacion;
                        $usersData[$index]['ficha_grupo'] = $groupUser->ficha_grupo;
                        $usersData[$index]['tipo_caracterizacion'] = $groupUser->tipo_caracterizacion;
                        $usersData[$index]['updated_at'] = $groupUser->updated_at->toISOString();
                        break;
                    }
                }
                $group->users_data = $usersData;
                $group->save();
            }

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

            // Buscar usuario en la tabla
            $groupUser = GroupUserModel::where('group_id', $groupId)
                ->where('id', $userId)
                ->first();

            if (!$groupUser) {
                return response()->json([
                    'message' => 'Usuario no encontrado en el grupo'
                ], 404);
            }

            // SIEMPRE eliminar de ambos lugares
            // 1. Eliminar de la tabla
            $groupUser->delete();

            // 2. Eliminar del array JSON
            $usersData = $group->users_data ?? [];
            if (is_array($usersData) && count($usersData) > 0) {
                $filteredUsers = [];
                foreach ($usersData as $user) {
                    if (isset($user['id']) && $user['id'] != $userId) {
                        $filteredUsers[] = $user;
                    }
                }
                $group->users_data = $filteredUsers;
                $group->user_count = count($filteredUsers);
                $group->save();
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