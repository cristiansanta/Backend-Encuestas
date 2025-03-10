<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Mostrar mensaje inicial para saber que la función fue llamada
      
    
        try {
            // Validación de credenciales
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
    
            // Mostrar las credenciales para verificar que se recibieron correctamente
            // Intento de autenticación
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $token = $user->createToken('auth_token')->plainTextToken;
    
                // Responder con encabezados CORS para el frontend
                return response()->json([
                
                    'message' => 'Inicio de sesión exitoso.',
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'user' => [
                    'name' => $user->name,
                    'id' => $user->id,
                    'active' => $user->active
                ],
                    
                ], 200)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->header('Access-Control-Allow-Credentials', 'true');
            }
    
            // Mostrar mensaje si las credenciales son incorrectas
            dd('Credenciales incorrectas proporcionadas.');
    
            return response()->json(['message' => 'Detalles de inicio de sesión inválidos'], 401)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
                ->header('Access-Control-Allow-Credentials', 'true');
                
        } catch (ValidationException $e) {
            // Mostrar errores de validación
            dd('Error de validación:', $e->errors());
    
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors(),
            ], 422)
            ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->header('Access-Control-Allow-Credentials', 'true');
        } catch (\Exception $e) {
            // Captura y muestra cualquier otro error inesperado
            dd('Error inesperado durante el inicio de sesión:', $e->getMessage());
    
            return response()->json([
                'message' => 'Error inesperado en el servidor',
                'error' => $e->getMessage(),
            ], 500)
            ->header('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->header('Access-Control-Allow-Credentials', 'true');
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





}