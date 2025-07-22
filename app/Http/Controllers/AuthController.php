<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

use App\Models\User;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            // Debug: intentar diferentes métodos para obtener los datos
            $jsonData = [];
            
            // Método 1: $request->json()
            if ($request->json() && $request->json()->all()) {
                $jsonData = $request->json()->all();
            }
            // Método 2: Decodificar manualmente el contenido
            elseif ($request->getContent()) {
                $rawContent = $request->getContent();
                $decodedData = json_decode($rawContent, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
                    $jsonData = $decodedData;
                }
            }
            // Método 3: Input normal
            else {
                $jsonData = $request->all();
            }
            
            // Validación de credenciales usando los datos JSON
            $validator = \Validator::make($jsonData, [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
            
            if ($validator->fails()) {
                return response()->json([
                    'message' => 'Error de validación',
                    'errors' => $validator->errors(),
                ], 422)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            }
            
            $credentials = $validator->validated();
    
            // Intento de autenticación
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                
                // Verificar si el usuario está activo
                if (!$user->active) {
                    return response()->json([
                        'message' => 'Usuario inactivo. Contacte al administrador.'
                    ], 403)
                    ->header('Access-Control-Allow-Origin', '*')
                    ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                    ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                }
                
                $token = $user->createToken('auth_token')->plainTextToken;
    
                // Cargar roles y permisos para incluir en la respuesta
                $user->load('roles', 'permissions');
                
                // Obtener todos los permisos del usuario
                $allPermissions = $user->getAllPermissions();
    
                return response()->json([
                    'message' => 'Inicio de sesión exitoso.',
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'active' => $user->active,
                        'rol' => $user->rol,
                        'roles' => $user->roles->map(function($role) {
                            return [
                                'id' => $role->id,
                                'name' => $role->name
                            ];
                        }),
                        'permissions' => $allPermissions->pluck('name')->toArray()
                    ],
                ], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
            }
    
            // Credenciales incorrectas
            return response()->json(['message' => 'Credenciales incorrectas'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
                
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error inesperado en el servidor',
                'error' => $e->getMessage(),
            ], 500)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        }
    }
    


    public function logout(Request $request)
    {
        
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'No authenticated user found'], 401);
        }
        $tokens = $user->tokens;
        if ($tokens->isEmpty()) {
            return response()->json(['message' => 'No active token found for the user'], 404);
        }
        $deletedCount = $tokens->each->delete()->count();
        return response()->json([
            'message' => 'Logged out successfully',
            'tokens_revoked' => $deletedCount,
            'tokens_remaining' => $user->tokens()->count()
        ], 200);
    }

    public function getTokenByEmail(Request $request)
{
    $email = $request->input('email');

    // Validar si el correo ha sido proporcionado
    if (!$email) {
        return response()->json(['message' => 'Email is required'], 400);
    }

    // Buscar al usuario por correo electrónico
    $user = User::where('email', $email)->first();

    // Verificar si el usuario existe
    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Obtener el primer token activo del usuario
    $token = $user->tokens()->latest()->first();

    // Verificar si hay un token activo
    if (!$token) {
        return response()->json(['message' => 'No active token found for the user'], 404);
    }

    // Devolver el token en el formato requerido
    return response()->json([
        'access_token' => $token->token,
        'token_type' => 'Bearer'
    ], 200);
}

    public function getCurrentUser(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'No authenticated user found'], 401);
            }

            // Cargar roles y permisos
            $user->load('roles', 'permissions');
            
            // Obtener permisos a través de roles
            $permissionsViaRoles = $user->getPermissionsViaRoles();
            $directPermissions = $user->permissions;
            $allPermissions = $user->getAllPermissions();

            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'active' => $user->active,
                    'rol' => $user->rol,
                    'roles' => $user->roles->map(function($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name
                        ];
                    }),
                    'permissions' => $allPermissions->pluck('name'),
                    'permissions_via_roles' => $permissionsViaRoles->pluck('name'),
                    'direct_permissions' => $directPermissions->pluck('name')
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving user information',
                'error' => $e->getMessage()
            ], 500);
        }
    }





}