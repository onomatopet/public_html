<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Créer les permissions
            $permissions = [
                // Système
                ['name' => 'access_admin', 'display_name' => 'Accès au panneau admin', 'category' => 'system'],
                ['name' => 'view_dashboard', 'display_name' => 'Voir le tableau de bord', 'category' => 'system'],
                ['name' => 'manage_settings', 'display_name' => 'Gérer les paramètres', 'category' => 'system'],

                // Utilisateurs
                ['name' => 'view_users', 'display_name' => 'Voir les utilisateurs', 'category' => 'users'],
                ['name' => 'create_users', 'display_name' => 'Créer des utilisateurs', 'category' => 'users'],
                ['name' => 'edit_users', 'display_name' => 'Modifier les utilisateurs', 'category' => 'users'],
                ['name' => 'delete_users', 'display_name' => 'Supprimer les utilisateurs', 'category' => 'users'],
                ['name' => 'manage_roles', 'display_name' => 'Gérer les rôles et permissions', 'category' => 'users'],

                // Distributeurs
                ['name' => 'view_distributeurs', 'display_name' => 'Voir les distributeurs', 'category' => 'distributeurs'],
                ['name' => 'create_distributeurs', 'display_name' => 'Créer des distributeurs', 'category' => 'distributeurs'],
                ['name' => 'edit_distributeurs', 'display_name' => 'Modifier les distributeurs', 'category' => 'distributeurs'],
                ['name' => 'delete_distributeurs', 'display_name' => 'Supprimer les distributeurs', 'category' => 'distributeurs'],
                ['name' => 'view_network', 'display_name' => 'Voir la structure réseau', 'category' => 'distributeurs'],

                // Achats
                ['name' => 'view_achats', 'display_name' => 'Voir les achats', 'category' => 'achats'],
                ['name' => 'create_achats', 'display_name' => 'Créer des achats', 'category' => 'achats'],
                ['name' => 'edit_achats', 'display_name' => 'Modifier les achats', 'category' => 'achats'],
                ['name' => 'delete_achats', 'display_name' => 'Supprimer les achats', 'category' => 'achats'],
                ['name' => 'validate_achats', 'display_name' => 'Valider les achats', 'category' => 'achats'],

                // Produits
                ['name' => 'view_products', 'display_name' => 'Voir les produits', 'category' => 'products'],
                ['name' => 'create_products', 'display_name' => 'Créer des produits', 'category' => 'products'],
                ['name' => 'edit_products', 'display_name' => 'Modifier les produits', 'category' => 'products'],
                ['name' => 'delete_products', 'display_name' => 'Supprimer les produits', 'category' => 'products'],

                // Bonus
                ['name' => 'view_bonuses', 'display_name' => 'Voir les bonus', 'category' => 'bonuses'],
                ['name' => 'create_bonuses', 'display_name' => 'Créer des bonus', 'category' => 'bonuses'],
                ['name' => 'edit_bonuses', 'display_name' => 'Modifier les bonus', 'category' => 'bonuses'],
                ['name' => 'delete_bonuses', 'display_name' => 'Supprimer les bonus', 'category' => 'bonuses'],
                ['name' => 'validate_bonuses', 'display_name' => 'Valider les bonus', 'category' => 'bonuses'],

                // Workflow
                ['name' => 'view_workflow', 'display_name' => 'Voir le workflow', 'category' => 'workflow'],
                ['name' => 'execute_workflow', 'display_name' => 'Exécuter les étapes du workflow', 'category' => 'workflow'],
                ['name' => 'validate_purchases', 'display_name' => 'Valider les achats (workflow)', 'category' => 'workflow'],
                ['name' => 'aggregate_purchases', 'display_name' => 'Agréger les achats', 'category' => 'workflow'],
                ['name' => 'calculate_advancements', 'display_name' => 'Calculer les avancements', 'category' => 'workflow'],
                ['name' => 'close_period', 'display_name' => 'Clôturer les périodes', 'category' => 'workflow'],
                ['name' => 'reset_period', 'display_name' => 'Réinitialiser les périodes', 'category' => 'workflow', 'description' => 'Permet de réinitialiser complètement une période en cours'],
                ['name' => 'view_period_backups', 'display_name' => 'Voir les sauvegardes de périodes', 'category' => 'backups'],
                ['name' => 'restore_period_backups', 'display_name' => 'Restaurer les sauvegardes de périodes', 'category' => 'backups'],


                // Rapports
                ['name' => 'view_reports', 'display_name' => 'Voir les rapports', 'category' => 'reports'],
                ['name' => 'export_data', 'display_name' => 'Exporter les données', 'category' => 'reports'],
                ['name' => 'view_analytics', 'display_name' => 'Voir les analyses', 'category' => 'reports'],

                // Suppressions
                ['name' => 'view_deletion_requests', 'display_name' => 'Voir les demandes de suppression', 'category' => 'deletions'],
                ['name' => 'create_deletion_requests', 'display_name' => 'Créer des demandes de suppression', 'category' => 'deletions'],
                ['name' => 'approve_deletions', 'display_name' => 'Approuver les suppressions', 'category' => 'deletions'],
                ['name' => 'execute_deletions', 'display_name' => 'Exécuter les suppressions', 'category' => 'deletions'],

                // Sauvegardes
                ['name' => 'view_backups', 'display_name' => 'Voir les sauvegardes', 'category' => 'backups'],
                ['name' => 'create_backups', 'display_name' => 'Créer des sauvegardes', 'category' => 'backups'],
                ['name' => 'restore_backups', 'display_name' => 'Restaurer les sauvegardes', 'category' => 'backups'],
            ];

            foreach ($permissions as $permission) {
                Permission::firstOrCreate(
                    ['name' => $permission['name']],
                    $permission
                );
            }

            // Créer les rôles
            $superAdmin = Role::firstOrCreate(
                ['name' => 'super_admin'],
                [
                    'display_name' => 'Super Administrateur',
                    'description' => 'Accès complet au système avec tous les privilèges',
                    'is_system' => true,
                    'priority' => 1
                ]
            );

            $admin = Role::firstOrCreate(
                ['name' => 'admin'],
                [
                    'display_name' => 'Administrateur',
                    'description' => 'Accès administratif avec permissions limitées',
                    'is_system' => true,
                    'priority' => 2
                ]
            );

            $manager = Role::firstOrCreate(
                ['name' => 'manager'],
                [
                    'display_name' => 'Gestionnaire',
                    'description' => 'Gestion des distributeurs et des achats',
                    'is_system' => true,
                    'priority' => 3
                ]
            );

            $user = Role::firstOrCreate(
                ['name' => 'user'],
                [
                    'display_name' => 'Utilisateur',
                    'description' => 'Utilisateur standard avec accès limité',
                    'is_system' => true,
                    'priority' => 4
                ]
            );

            // Assigner les permissions aux rôles

            // Super Admin - toutes les permissions
            $superAdmin->permissions()->sync(Permission::all());

            // Admin - toutes sauf gestion des rôles et suppressions critiques
            $adminPermissions = Permission::whereNotIn('name', [
                'manage_roles',
                'execute_deletions',
                'restore_backups'
            ])->pluck('id');
            $admin->permissions()->sync($adminPermissions);

            // Manager - permissions de gestion courante
            $managerPermissions = Permission::whereIn('name', [
                'access_admin',
                'view_dashboard',
                'view_distributeurs',
                'create_distributeurs',
                'edit_distributeurs',
                'view_achats',
                'create_achats',
                'edit_achats',
                'view_products',
                'view_bonuses',
                'view_network',
                'view_reports',
                'export_data'
            ])->pluck('id');
            $manager->permissions()->sync($managerPermissions);

            // User - permissions minimales
            $userPermissions = Permission::whereIn('name', [
                'view_dashboard',
                'view_distributeurs',
                'view_achats',
                'view_products'
            ])->pluck('id');
            $user->permissions()->sync($userPermissions);

            // Créer un super admin par défaut si aucun n'existe
            $firstUser = User::first();
            if ($firstUser && !$firstUser->hasRole('super_admin')) {
                $firstUser->assignRole('super_admin');
                $this->command->info('Super Admin role assigned to first user: ' . $firstUser->email);
            }

            DB::commit();

            $this->command->info('Roles and permissions seeded successfully!');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Error seeding roles and permissions: ' . $e->getMessage());
            throw $e;
        }
    }
}
