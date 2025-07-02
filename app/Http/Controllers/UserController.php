<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use PhpParser\Node\Stmt\Echo_;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }
    
   
    public function store(Request $request)
    {
        // Validación de los datos de entrada
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed', // Confirmed necesita un 'password_confirmation' en la solicitud
            'document_type' => 'nullable|in:cedula_ciudadania,tarjeta_identidad,cedula_extranjeria,pep,permiso_proteccion_temporal',
            'document_number' => 'nullable|string|max:50',
        ]);
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        try {
            // Creación del usuario
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'active' => true, // Por defecto activo
                'document_type' => $request->document_type,
                'document_number' => $request->document_number,
            ]);
    
            return response()->json(['user' => $user, 'message' => 'Usuario creado exitosamente'], 201);
        } catch (\Exception $e) {
            // Manejo de errores con detalles para debug
            return response()->json([
                'error' => 'Error al crear el usuario. Intenta nuevamente.',
                'details' => $e->getMessage()
            ], 500);
        }
    }    public function show(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        return response()->json($user);
    }
    
    public function update(Request $request, string $id)
{
    // Buscar el usuario
    $user = User::find($id);
    if (!$user) {
        return response()->json(['error' => 'User not found'], 404);
    }

    // Actualizar el campo 'active' si está presente en la solicitud
    if ($request->has('active')) {
        $user->active = $request->input('active');
    }

    // Actualizar otros campos solo si están presentes
    if ($request->has('name')) {
        $user->name = $request->input('name');
    }

    if ($request->has('email')) {
        $user->email = $request->input('email');
    }

    if ($request->has('password')) {
        $user->password = bcrypt($request->input('password'));
    }

    // Actualizar campos de documento
    if ($request->has('document_type')) {
        $user->document_type = $request->input('document_type');
    }

    if ($request->has('document_number')) {
        $user->document_number = $request->input('document_number');
    }

    // Guardar los cambios
    $user->save();

    // Retornar la respuesta
    return response()->json($user);
}

    
    public function destroy(string $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        $user->delete();
        return response()->json(null, 204);
    }

// optiene los usuarios con los roles asignados 
    public function getUsersWithRoles()
    {
        try {
            // Obtener los usuarios con sus roles
            $users = User::with('roles')->get();
    
            return response()->json([
                'success' => true,
                'data' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
