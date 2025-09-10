<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use PhpParser\Node\Stmt\Echo_;

class UserController extends Controller
{
    /**
     * Validate if a string contains only letters, spaces and accented characters
     */
    private function validateNameCharacters($name) {
        // Validación simple que funciona en la mayoria de versiones de PHP/PCRE
        // Permitir letras básicas y espacios solamente para evitar errores de compatibilidad
        return preg_match('/^[a-zA-Z\s]+$/', $name);
    }
    
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
            'phone_number' => 'nullable|string|min:10|max:10',
            'password' => 'required|string|min:8|confirmed', // Confirmed necesita un 'password_confirmation' en la solicitud
            'document_type' => 'nullable|in:cedula_ciudadania,tarjeta_identidad,cedula_extranjeria,pep,permiso_proteccion_temporal',
            'document_number' => 'nullable|string|max:50',
            'allow_view_questions_categories' => 'nullable|boolean',
            'allow_view_other_users_groups' => 'nullable|boolean',
        ], [
            'name.required' => 'El nombre es obligatorio.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'email.email' => 'El correo electrónico debe ser válido.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.'
        ]);
        
        // Validación personalizada para el nombre
        if ($request->has('name') && !$this->validateNameCharacters($request->name)) {
            return response()->json([
                'errors' => ['name' => ['El nombre solo debe contener letras y espacios.']]
            ], 422);
        }
    
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
    
        try {
            // Creación del usuario
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone_number' => $request->phone_number ?: null,
                'password' => Hash::make($request->password),
                'active' => true, // Por defecto activo
                'document_type' => $request->document_type,
                'document_number' => $request->document_number,
                'allow_view_questions_categories' => $request->allow_view_questions_categories ?? false,
                'allow_view_other_users_groups' => $request->allow_view_other_users_groups ?? false,
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

        // Validación de los datos según lo que se esté actualizando
        $rules = [];
        
        if ($request->has('name')) {
            $rules['name'] = ['required', 'string', 'max:255'];
        }
        
        if ($request->has('email')) {
            $rules['email'] = 'required|string|email|max:255|unique:users,email,' . $id;
        }
        
        if ($request->has('password')) {
            $rules['password'] = 'required|string|min:8';
        }
        
        if ($request->has('phone_number')) {
            $rules['phone_number'] = 'nullable|string|min:10|max:10';
        }
        
        if ($request->has('document_type')) {
            $rules['document_type'] = 'nullable|in:cedula_ciudadania,tarjeta_identidad,cedula_extranjeria,pep,permiso_proteccion_temporal';
        }
        
        if ($request->has('document_number')) {
            $rules['document_number'] = 'nullable|string|max:50';
        }
        
        if ($request->has('allow_view_questions_categories')) {
            $rules['allow_view_questions_categories'] = 'nullable|boolean';
        }
        
        if ($request->has('allow_view_other_users_groups')) {
            $rules['allow_view_other_users_groups'] = 'nullable|boolean';
        }
        
        // Validación personalizada para el nombre
        if ($request->has('name') && !$this->validateNameCharacters($request->name)) {
            return response()->json([
                'errors' => ['name' => ['El nombre solo debe contener letras y espacios.']]
            ], 422);
        }
        
        // Validar solo los campos que se están enviando
        if (!empty($rules)) {
            $messages = [
                'name.required' => 'El nombre es obligatorio.',
                'email.unique' => 'Este correo electrónico ya está registrado.',
                'email.email' => 'El correo electrónico debe ser válido.',
                'password.min' => 'La contraseña debe tener al menos 8 caracteres.'
            ];
            
            $validator = Validator::make($request->all(), $rules, $messages);
            
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }
        }

        try {
            // Actualizar el campo 'active' si está presente en la solicitud
            if ($request->has('active')) {
                // Evitar que un usuario se desactive a sí mismo
                $currentUserId = auth()->id();
                if ($currentUserId == $user->id && !$request->input('active')) {
                    return response()->json([
                        'error' => 'No puedes desactivarte a ti mismo',
                        'details' => 'Por razones de seguridad, los usuarios no pueden desactivar sus propias cuentas'
                    ], 403);
                }
                
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
                $user->password = Hash::make($request->input('password'));
            }

            // Actualizar campos de documento
            if ($request->has('document_type')) {
                $user->document_type = $request->input('document_type');
            }

            if ($request->has('document_number')) {
                $user->document_number = $request->input('document_number');
            }

            if ($request->has('phone_number')) {
                $user->phone_number = $request->input('phone_number') ?: null;
            }

            // Actualizar campos de permisos de visibilidad
            if ($request->has('allow_view_questions_categories')) {
                $user->allow_view_questions_categories = $request->input('allow_view_questions_categories') ?? false;
            }

            if ($request->has('allow_view_other_users_groups')) {
                $user->allow_view_other_users_groups = $request->input('allow_view_other_users_groups') ?? false;
            }

            // Guardar los cambios
            $user->save();

            // Retornar la respuesta
            return response()->json([
                'user' => $user,
                'message' => 'Usuario actualizado exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar el usuario',
                'details' => $e->getMessage()
            ], 500);
        }
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
