<?php 

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;



class RoleController extends Controller
{
    // asigna al usuario un rol
    public function assignRole(Request $request)
    {
        // Validar los datos del request
        try {
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
                'role' => 'required|exists:roles,name',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Captura las excepciones de validación y retorna un mensaje de error claro
            return response()->json(['error' => 'Validation failed', 'details' => $e->errors()], 422);
        }
    
        try {
            // Intentar encontrar el usuario
            $user = User::findOrFail($validated['user_id']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Captura la excepción si el usuario no se encuentra
            return response()->json(['error' => 'User not found'], 404);
        }
    
        try {
            // Asignar el rol al usuario
            $user->assignRole($validated['role']);
        } catch (\Spatie\Permission\Exceptions\RoleDoesNotExist $e) {
            // Captura la excepción si el rol no existe
            return response()->json(['error' => 'Role not found'], 404);
        } catch (\Exception $e) {
            // Captura cualquier otra excepción inesperada
            return response()->json(['error' => 'An unexpected error occurred', 'details' => $e->getMessage()], 500);
        }
    
        // Si todo va bien, retornar el éxito
        return response()->json(['message' => 'Role assigned successfully'], 200);
    }

  

    ///Asignan los permisos a los roles
    public function assignPermissionsToRole(Request $request)
{
    $validated = $request->validate([
        'role' => 'required|exists:roles,name',
        'permissions' => 'required|array',
        //'permissions.*' => 'exists:permissions,name',
    ]);

    $role = Role::where('name', $validated['role'])->firstOrFail();

    // Assign multiple permissions
    $role->givePermissionTo($validated['permissions']);

    return response()->json(['message' => 'Permissions assigned to role successfully'], 200);
  }

    
  
  ///Crea nuevos roles 
  public function createRoles(Request $request)
    {
        // Validate the request data
        $validated = $request->validate([
            'roles' => 'required|array',                          // roles must be an array
            'roles.*' => 'required|string|unique:roles,name',     // each role name must be unique in the roles table
        ]);

        // Create each role
        $createdRoles = [];
        foreach ($validated['roles'] as $roleName) {
            $createdRoles[] = Role::create(['name' => $roleName]);
        }

        // Return a success response with the created roles
        return response()->json(['message' => 'Roles created successfully', 'roles' => $createdRoles], 201);
    }

    //Consulta los roles existentes
    public function getAllRoles()
{
    // Obtener todos los roles
    $roles = Role::all();

    // Retornar los roles en una respuesta JSON
    return response()->json(['roles' => $roles], 200);
}



    //Se obtienen los permisos del usuario
    public function getUserPermissions(Request $request)
    {
        // Validar que el ID de usuario está presente y es válido
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        // Encontrar al usuario por su ID
        $user = User::findOrFail($validated['user_id']);

        // Obtener los permisos del usuario
        $permissions = $user->getAllPermissions()->pluck('name');

        return response()->json([
            'permissions' => $permissions,
        ]);
    }

    //otorga permisos unitarios o particulares a usuarios 
    public function updateUserPermissions(Request $request)
    {
        // Validar la solicitud
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'permissions' => 'required|array',
            'permissions.*' => 'exists:permissions,name',
        ]);

        // Encontrar al usuario
        $user = User::findOrFail($validated['user_id']);

        // Obtener los permisos del usuario actual
        $currentPermissions = $user->getPermissionNames();

        // Actualizar los permisos: eliminar los que no están en la nueva lista, y agregar los nuevos
        $user->syncPermissions($validated['permissions']);

        return response()->json([
            'message' => 'Permissions updated successfully',
            'current_permissions' => $currentPermissions,
            'new_permissions' => $user->getPermissionNames(),
        ]);
    }



    public function getUserRolesAndPermissions(Request $request)
    {
        try {
            // Validar el parámetro `user_id`
            $validated = $request->validate([
                'user_id' => 'required|exists:users,id',
            ]);
            
            // Encontrar el usuario por ID
            $user = User::findOrFail($validated['user_id']);
            
            // Obtener los roles asignados al usuario
            $roles = $user->roles->pluck('name'); // Extrae solo los nombres de los roles
    
            // Obtener los permisos asociados a los roles del usuario
            $permissions = $user->getPermissionsViaRoles()->pluck('name'); // Extrae permisos basados en roles
    
            // Opcionalmente, agregar permisos directos del usuario
            $directPermissions = $user->permissions->pluck('name'); // Permisos asignados directamente al usuario
    
            // Combinar permisos (si quieres mostrarlos juntos)
            $allPermissions = $permissions->merge($directPermissions)->unique();
    
            // Retornar los roles y permisos como JSON
            return response()->json([
                'user_id' => $user->id,
                'roles' => $roles,
                'permissions' => $allPermissions, // Todos los permisos únicos
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Manejo de error de validación
            return response()->json([
                'error' => 'Validation failed',
                'details' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            // Manejo de error si no encuentra el usuario
            return response()->json([
                'error' => 'User not found',
            ], 404);
        } catch (\Exception $e) {
            // Manejo de cualquier otro error
            return response()->json([
                'error' => 'An unexpected error occurred',
                'details' => $e->getMessage(),
            ], 500);
        }
    }
    


}