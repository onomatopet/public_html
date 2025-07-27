<?php

// app/Http/Controllers/Admin/RolePermissionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RolePermissionController extends Controller
{
    /**
     * Afficher la liste des rôles
     */
    public function index()
    {
        $roles = Role::withCount(['users', 'permissions'])
            ->orderBy('priority')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Afficher le formulaire de création d'un rôle
     */
    public function create()
    {
        $permissions = Permission::groupedByCategory();
        $categories = Permission::getCategories();

        return view('admin.roles.create', compact('permissions', 'categories'));
    }

    /**
     * Enregistrer un nouveau rôle
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:roles',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $validated['name'],
                'display_name' => $validated['display_name'],
                'description' => $validated['description'],
                'priority' => Role::max('priority') + 1
            ]);

            if (!empty($validated['permissions'])) {
                $role->permissions()->attach($validated['permissions']);
            }

            DB::commit();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Rôle créé avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur création rôle: ' . $e->getMessage());
            
            return back()->withInput()
                ->with('error', 'Erreur lors de la création du rôle.');
        }
    }

    /**
     * Afficher les détails d'un rôle
     */
    public function show(Role $role)
    {
        $role->load(['permissions', 'users']);
        $permissionsByCategory = $role->permissions->groupBy('category');
        $categories = Permission::getCategories();

        return view('admin.roles.show', compact('role', 'permissionsByCategory', 'categories'));
    }

    /**
     * Afficher le formulaire d'édition d'un rôle
     */
    public function edit(Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Les rôles système ne peuvent pas être modifiés.');
        }

        $permissions = Permission::groupedByCategory();
        $categories = Permission::getCategories();
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        return view('admin.roles.edit', compact('role', 'permissions', 'categories', 'rolePermissions'));
    }

    /**
     * Mettre à jour un rôle
     */
    public function update(Request $request, Role $role)
    {
        if ($role->is_system) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Les rôles système ne peuvent pas être modifiés.');
        }

        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        DB::beginTransaction();
        try {
            $role->update([
                'display_name' => $validated['display_name'],
                'description' => $validated['description']
            ]);

            $role->permissions()->sync($validated['permissions'] ?? []);

            DB::commit();

            return redirect()->route('admin.roles.show', $role)
                ->with('success', 'Rôle mis à jour avec succès.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur mise à jour rôle: ' . $e->getMessage());
            
            return back()->withInput()
                ->with('error', 'Erreur lors de la mise à jour du rôle.');
        }
    }

    /**
     * Supprimer un rôle
     */
    public function destroy(Role $role)
    {
        if ($role->is_system) {
            return response()->json(['error' => 'Les rôles système ne peuvent pas être supprimés.'], 403);
        }

        if ($role->users()->count() > 0) {
            return response()->json(['error' => 'Ce rôle est assigné à des utilisateurs.'], 403);
        }

        try {
            $role->delete();
            return response()->json(['success' => 'Rôle supprimé avec succès.']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la suppression.'], 500);
        }
    }

    /**
     * Afficher la gestion des permissions des utilisateurs
     */
    public function users(Request $request)
    {
        $query = User::with('roles')
            ->when($request->search, function ($q) use ($request) {
                $q->where(function ($query) use ($request) {
                    $query->where('name', 'like', "%{$request->search}%")
                        ->orWhere('email', 'like', "%{$request->search}%");
                });
            })
            ->when($request->role, function ($q) use ($request) {
                $q->whereHas('roles', function ($query) use ($request) {
                    $query->where('name', $request->role);
                });
            });

        $users = $query->paginate(20);
        $roles = Role::orderBy('priority')->get();

        return view('admin.roles.users', compact('users', 'roles'));
    }

    /**
     * Mettre à jour les rôles d'un utilisateur
     */
    public function updateUserRoles(Request $request, User $user)
    {
        $validated = $request->validate([
            'roles' => 'array',
            'roles.*' => 'exists:roles,id'
        ]);

        try {
            $user->syncRoles($validated['roles'] ?? [], auth()->id());

            return back()->with('success', 'Rôles de l\'utilisateur mis à jour.');
        } catch (\Exception $e) {
            Log::error('Erreur mise à jour rôles utilisateur: ' . $e->getMessage());
            
            return back()->with('error', 'Erreur lors de la mise à jour des rôles.');
        }
    }

    /**
     * Basculer l'état actif/inactif d'un utilisateur
     */
    public function toggleUserStatus(User $user)
    {
        try {
            $user->update(['is_active' => !$user->is_active]);

            return response()->json([
                'success' => true,
                'is_active' => $user->is_active,
                'message' => $user->is_active ? 'Utilisateur activé' : 'Utilisateur désactivé'
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Erreur lors de la mise à jour.'], 500);
        }
    }
}