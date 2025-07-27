<?php

// app/Models/Permission.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'category',
        'description'
    ];

    /**
     * Les rôles qui ont cette permission
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission')
            ->withTimestamps();
    }

    /**
     * Grouper les permissions par catégorie
     */
    public static function groupedByCategory()
    {
        return static::orderBy('category')
            ->orderBy('display_name')
            ->get()
            ->groupBy('category');
    }

    /**
     * Catégories disponibles
     */
    public static function getCategories(): array
    {
        return [
            'system' => 'Système',
            'users' => 'Utilisateurs',
            'distributeurs' => 'Distributeurs',
            'achats' => 'Achats',
            'products' => 'Produits',
            'bonuses' => 'Bonus',
            'workflow' => 'Workflow',
            'reports' => 'Rapports',
            'deletions' => 'Suppressions',
            'backups' => 'Sauvegardes'
        ];
    }
}
