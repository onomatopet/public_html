<?php

// app/Models/Role.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'is_system',
        'priority'
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'priority' => 'integer'
    ];

    /**
     * Les permissions assignées à ce rôle
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission')
            ->withTimestamps();
    }

    /**
     * Les utilisateurs ayant ce rôle
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')
            ->withPivot('assigned_at', 'assigned_by');
            // Retiré ->withTimestamps() car nous n'avons pas created_at/updated_at dans user_role
    }

    /**
     * Assigner une permission au rôle
     */
    public function givePermissionTo($permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->firstOrFail();
        }

        if (!$this->permissions->contains($permission)) {
            $this->permissions()->attach($permission);
        }
    }

    /**
     * Retirer une permission du rôle
     */
    public function revokePermissionTo($permission): void
    {
        if (is_string($permission)) {
            $permission = Permission::where('name', $permission)->first();
        }

        if ($permission) {
            $this->permissions()->detach($permission);
        }
    }

    /**
     * Synchroniser les permissions
     */
    public function syncPermissions(array $permissions): void
    {
        $permissionIds = collect($permissions)->map(function ($permission) {
            if (is_numeric($permission)) {
                return $permission;
            }
            return Permission::where('name', $permission)->first()?->id;
        })->filter()->toArray();

        $this->permissions()->sync($permissionIds);
    }

    /**
     * Vérifier si le rôle a une permission
     */
    public function hasPermissionTo($permission): bool
    {
        if (is_string($permission)) {
            return $this->permissions->contains('name', $permission);
        }

        return $this->permissions->contains($permission);
    }
}
