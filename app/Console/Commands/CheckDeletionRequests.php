<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\DeletionRequest;
use App\Models\Distributeur;
use App\Models\User;
use Carbon\Carbon;

class CheckDeletionRequests extends Command
{
    protected $signature = 'deletion-requests:check {--create-sample : Créer des exemples de demandes}';
    protected $description = 'Vérifie les demandes de suppression et peut créer des exemples';

    public function handle()
    {
        $this->info('Vérification des demandes de suppression...');

        // Compter les demandes existantes
        $total = DeletionRequest::count();
        $pending = DeletionRequest::where('status', DeletionRequest::STATUS_PENDING)->count();
        $approved = DeletionRequest::where('status', DeletionRequest::STATUS_APPROVED)->count();
        $completed = DeletionRequest::where('status', DeletionRequest::STATUS_COMPLETED)->count();
        $rejected = DeletionRequest::where('status', DeletionRequest::STATUS_REJECTED)->count();

        $this->table(
            ['Statut', 'Nombre'],
            [
                ['Total', $total],
                ['En attente', $pending],
                ['Approuvées', $approved],
                ['Complétées', $completed],
                ['Rejetées', $rejected],
            ]
        );

        if ($total == 0) {
            $this->warn('Aucune demande de suppression trouvée dans la base de données.');

            if ($this->option('create-sample')) {
                $this->createSampleRequests();
            } else {
                $this->info('Utilisez --create-sample pour créer des exemples de demandes.');
            }
        } else {
            // Afficher les dernières demandes
            $this->info("\nDernières demandes:");
            $recent = DeletionRequest::with(['requestedBy'])->latest()->take(5)->get();

            foreach ($recent as $request) {
                $this->line(sprintf(
                    "[%s] %s - %s #%d - Demandé par: %s - Statut: %s",
                    $request->created_at->format('Y-m-d H:i'),
                    $request->entity_type,
                    $request->entity_id,
                    $request->id,
                    $request->requestedBy->name ?? 'N/A',
                    $request->status
                ));
            }
        }

        // Vérifier les permissions
        $this->checkPermissions();
    }

    private function createSampleRequests()
    {
        $this->info('Création d\'exemples de demandes de suppression...');

        // Récupérer un utilisateur admin
        $admin = User::first();
        if (!$admin) {
            $this->error('Aucun utilisateur trouvé pour créer les demandes.');
            return;
        }

        // Créer quelques demandes d'exemple
        $samples = [
            [
                'entity_type' => 'distributeur',
                'entity_id' => 999999, // ID fictif
                'status' => DeletionRequest::STATUS_PENDING,
                'reason' => 'Distributeur inactif depuis plus de 2 ans',
                'impact_analysis' => [
                    'children_count' => 0,
                    'active_bonuses' => 0,
                    'total_purchases' => 0
                ],
            ],
            [
                'entity_type' => 'achat',
                'entity_id' => 888888, // ID fictif
                'status' => DeletionRequest::STATUS_APPROVED,
                'reason' => 'Achat en double suite à erreur de saisie',
                'approved_at' => Carbon::now()->subHours(2),
            ],
            [
                'entity_type' => 'distributeur',
                'entity_id' => 777777, // ID fictif
                'status' => DeletionRequest::STATUS_COMPLETED,
                'reason' => 'Demande du distributeur pour suppression RGPD',
                'approved_at' => Carbon::now()->subDays(1),
                'completed_at' => Carbon::now()->subHours(12),
            ],
        ];

        foreach ($samples as $data) {
            $data['requested_by_id'] = $admin->id;

            if (in_array($data['status'], [DeletionRequest::STATUS_APPROVED, DeletionRequest::STATUS_COMPLETED])) {
                $data['approved_by_id'] = $admin->id;
            }

            DeletionRequest::create($data);
            $this->info("Créé: {$data['entity_type']} #{$data['entity_id']} - {$data['status']}");
        }

        $this->info('Exemples créés avec succès!');
    }

    private function checkPermissions()
    {
        $this->info("\nVérification des permissions:");

        $permissions = [
            'view_deletion_requests',
            'create_deletion_requests',
            'approve_deletions',
            'execute_deletions',
            'view_backups',
            'restore_backups'
        ];

        foreach ($permissions as $permission) {
            $users = User::whereHas('roles.permissions', function($q) use ($permission) {
                $q->where('name', $permission);
            })->count();

            $this->line("- {$permission}: {$users} utilisateur(s)");
        }
    }
}
