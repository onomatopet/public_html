<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * App\Models\User
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $email_verified_at
 * @property string $password
 * @property bool $is_active
 * @property string|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $remember_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @property-read \App\Models\Distributeur|null $distributeur
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Role[] $roles
 * @property-read int|null $roles_count
 *
 * @method bool hasPermission(string $permission)
 * @method bool hasRole(string|Role $role)
 * @method bool hasAnyRole(...$roles)
 * @method bool hasAllRoles(...$roles)
 * @method bool hasAnyPermission(...$permissions)
 * @method void assignRole(string|Role $role, int|null $assignedBy = null)
 * @method void removeRole(string|Role $role)
 * @method void syncRoles(array $roles, int|null $assignedBy = null)
 * @method bool canAccessAdmin()
 * @method void updateLastLogin()
 * @method bool isActive()
 * @method Collection permissions()
 */
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'last_login_at',
        'last_login_ip'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'is_active' => 'boolean',
        'password' => 'hashed',
    ];

    /**
     * Les rôles de l'utilisateur
     * IMPORTANT: Utilise la table 'user_role' et non 'role_user'
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')
            ->withPivot('assigned_at', 'assigned_by');
    }

    /**
     * Relation avec le distributeur
     */
    public function distributeur(): HasOne
    {
        return $this->hasOne(Distributeur::class, 'user_id');
    }

    /**
     * Obtenir toutes les permissions de l'utilisateur via ses rôles
     */
    public function permissions(): Collection
    {
        return $this->roles->flatMap->permissions->unique('id');
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     *
     * @param string|Role $role
     * @return bool
     */
    public function hasRole($role): bool
    {
        if (is_string($role)) {
            return $this->roles->contains('name', $role);
        }

        return $this->roles->contains($role);
    }

    /**
     * Vérifier si l'utilisateur a au moins un des rôles
     *
     * @param mixed ...$roles
     * @return bool
     */
    public function hasAnyRole(...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->hasRole($role)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifier si l'utilisateur a tous les rôles
     *
     * @param mixed ...$roles
     * @return bool
     */
    public function hasAllRoles(...$roles): bool
    {
        foreach ($roles as $role) {
            if (!$this->hasRole($role)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Vérifier si l'utilisateur a une permission spécifique
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission($permission): bool
    {
        // Super admin a toutes les permissions
        if ($this->hasRole('super_admin')) {
            return true;
        }

        return $this->permissions()->contains('name', $permission);
    }

    /**
     * Vérifier si l'utilisateur a au moins une des permissions
     *
     * @param mixed ...$permissions
     * @return bool
     */
    public function hasAnyPermission(...$permissions): bool
    {
        if ($this->hasRole('super_admin')) {
            return true;
        }

        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Assigner un rôle à l'utilisateur
     *
     * @param string|Role $role
     * @param int|null $assignedBy
     * @return void
     */
    public function assignRole($role, $assignedBy = null): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        if (!$this->hasRole($role)) {
            $this->roles()->attach($role->id, [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy ?? auth()->id()
            ]);
        }
    }

    /**
     * Retirer un rôle de l'utilisateur
     *
     * @param string|Role $role
     * @return void
     */
    public function removeRole($role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->first();
        }

        if ($role) {
            $this->roles()->detach($role->id);
        }
    }

    /**
     * Synchroniser les rôles de l'utilisateur
     *
     * @param array $roles
     * @param int|null $assignedBy
     * @return void
     */
    public function syncRoles($roles, $assignedBy = null): void
    {
        $roleIds = collect($roles)->map(function ($role) {
            if (is_numeric($role)) {
                return $role;
            }
            return Role::where('name', $role)->first()?->id;
        })->filter()->toArray();

        $syncData = [];
        foreach ($roleIds as $roleId) {
            $syncData[$roleId] = [
                'assigned_at' => now(),
                'assigned_by' => $assignedBy ?? auth()->id()
            ];
        }

        $this->roles()->sync($syncData);
    }

    /**
     * Vérifier si l'utilisateur peut accéder à l'administration
     *
     * @return bool
     */
    public function canAccessAdmin(): bool
    {
        return $this->hasPermission('access_admin') || $this->hasRole(['admin', 'super_admin']);
    }

    /**
     * Mettre à jour la dernière connexion
     *
     * @return void
     */
    public function updateLastLogin(): void
    {
        $this->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip()
        ]);
    }

    /**
     * Vérifier si l'utilisateur est actif
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Obtenir le nom complet de l'utilisateur
     *
     * @return string
     */
    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    /**
     * Obtenir le nom du rôle principal de l'utilisateur
     *
     * @return string
     */
    public function getRoleNameAttribute(): string
    {
        return $this->roles->first()?->display_name ?? 'Utilisateur';
    }

    /**
     * Vérifier si l'utilisateur est un super admin
     *
     * @return bool
     */
    public function getIsSuperAdminAttribute(): bool
    {
        return $this->hasRole('super_admin');
    }

    /**
     * Vérifier si l'utilisateur est un admin
     *
     * @return bool
     */
    public function getIsAdminAttribute(): bool
    {
        return $this->hasRole(['admin', 'super_admin']);
    }
}
