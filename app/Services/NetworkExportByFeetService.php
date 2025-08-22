<?php
// app/Services/NetworkExportByFeetService.php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\LevelCurrent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NetworkExportByFeetService
{
    /**
     * Export le réseau organisé par pieds
     * Chaque pied (enfant direct) est traité complètement avant de passer au suivant
     */
    public function exportNetworkByFeet(string $distributeurId, string $period): array
    {
        Log::info("Début export réseau par pieds", [
            'distributeur_id' => $distributeurId,
            'period' => $period
        ]);

        // 1. Récupérer le distributeur principal
        $mainDistributor = Distributeur::where('distributeur_id', $distributeurId)->first();
        if (!$mainDistributor) {
            Log::error("Distributeur principal non trouvé: {$distributeurId}");
            return [];
        }

        // 2. Récupérer les enfants directs (les pieds)
        $directChildren = Distributeur::where('id_distrib_parent', $mainDistributor->id)
            ->orderBy('created_at') // Ordre chronologique d'inscription
            ->get();

        Log::info("Nombre de pieds trouvés: " . $directChildren->count());

        // 3. Déterminer la table à utiliser (level_currents ou level_current_histories)
        $tableName = $this->getTableName($period);

        // 4. Construire le réseau par pied
        $networkByFeet = [];
        $footNumber = 1;

        foreach ($directChildren as $directChild) {
            Log::info("Traitement du pied {$footNumber}: {$directChild->distributeur_id}");

            // Récupérer toute la descendance de ce pied
            $footNetwork = $this->getFootNetwork($directChild, $period, $tableName);

            if (!empty($footNetwork)) {
                $networkByFeet[] = [
                    'foot_number' => $footNumber,
                    'foot_leader' => [
                        'id' => $directChild->id,
                        'matricule' => $directChild->distributeur_id,
                        'nom' => $directChild->nom_distributeur . ' ' . $directChild->pnom_distributeur,
                        'grade' => $directChild->etoiles_id ?? 1
                    ],
                    'total_members' => count($footNetwork),
                    'members' => $footNetwork
                ];
                $footNumber++;
            }
        }

        // 5. Ajouter le distributeur principal en tête
        $mainData = $this->getDistributorData($mainDistributor, $period, $tableName, 0);
        
        $result = [
            'main_distributor' => $mainData,
            'total_feet' => count($networkByFeet),
            'total_network' => array_sum(array_column($networkByFeet, 'total_members')),
            'feet' => $networkByFeet,
            'period' => $period
        ];

        Log::info("Export terminé", [
            'total_feet' => $result['total_feet'],
            'total_network' => $result['total_network']
        ]);

        return $result;
    }

    /**
     * Récupère tout le réseau d'un pied (enfant direct et sa descendance)
     */
    protected function getFootNetwork(Distributeur $footLeader, string $period, string $tableName): array
    {
        $network = [];
        $processedIds = [];
        $queue = [
            [
                'distributeur' => $footLeader,
                'level' => 1, // Le chef de pied est au niveau 1
                'parent_matricule' => null
            ]
        ];

        while (!empty($queue)) {
            $current = array_shift($queue);
            $currentDistributor = $current['distributeur'];
            $currentLevel = $current['level'];

            // Éviter les doublons
            if (in_array($currentDistributor->id, $processedIds)) {
                continue;
            }
            $processedIds[] = $currentDistributor->id;

            // Récupérer les données de performance
            $distributorData = $this->getDistributorData(
                $currentDistributor, 
                $period, 
                $tableName, 
                $currentLevel
            );

            // Ajouter le parent matricule
            $distributorData['parent_matricule'] = $current['parent_matricule'];

            $network[] = $distributorData;

            // Ajouter les enfants à la queue
            $children = Distributeur::where('id_distrib_parent', $currentDistributor->id)
                ->orderBy('created_at')
                ->get();

            foreach ($children as $child) {
                $queue[] = [
                    'distributeur' => $child,
                    'level' => $currentLevel + 1,
                    'parent_matricule' => $currentDistributor->distributeur_id
                ];
            }
        }

        return $network;
    }

    /**
     * Récupère les données d'un distributeur pour une période
     */
    protected function getDistributorData(Distributeur $distributeur, string $period, string $tableName, int $level): array
    {
        // Récupérer les données de performance
        $performanceData = DB::table($tableName)
            ->where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        return [
            'niveau' => $level,
            'rang' => $level, // Alias pour compatibilité
            'distributeur_id' => $distributeur->distributeur_id,
            'nom_distributeur' => $distributeur->nom_distributeur ?? 'N/A',
            'pnom_distributeur' => $distributeur->pnom_distributeur ?? 'N/A',
            'etoiles' => $performanceData->etoiles ?? $distributeur->etoiles_id ?? 1,
            'new_cumul' => $performanceData->new_cumul ?? 0,
            'cumul_total' => $performanceData->cumul_total ?? 0,
            'cumul_collectif' => $performanceData->cumul_collectif ?? 0,
            'cumul_individuel' => $performanceData->cumul_individuel ?? 0,
            'tel_distributeur' => $distributeur->tel_distributeur ?? '',
            'adress_distributeur' => $distributeur->adress_distributeur ?? '',
            'created_at' => $distributeur->created_at->format('Y-m-d')
        ];
    }

    /**
     * Détermine la table à utiliser selon la période
     */
    protected function getTableName(string $period): string
    {
        // Vérifier si la période existe dans level_currents
        $existsInCurrent = DB::table('level_currents')
            ->where('period', $period)
            ->exists();

        if ($existsInCurrent) {
            return 'level_currents';
        }

        // Sinon, chercher dans les archives
        $existsInArchive = DB::table('level_current_histories')
            ->where('period', $period)
            ->exists();

        if ($existsInArchive) {
            return 'level_current_histories';
        }

        // Par défaut, utiliser level_currents
        return 'level_currents';
    }

    /**
     * Génère le rapport formaté pour l'affichage
     */
    public function formatForDisplay(array $networkData): array
    {
        $formatted = [];
        
        // Ajouter le distributeur principal
        $formatted[] = array_merge($networkData['main_distributor'], [
            'is_main' => true,
            'is_foot_leader' => false,
            'foot_number' => null
        ]);

        // Traiter chaque pied
        foreach ($networkData['feet'] as $foot) {
            // Ajouter un séparateur visuel pour chaque pied
            $formatted[] = [
                'is_separator' => true,
                'foot_number' => $foot['foot_number'],
                'foot_leader_name' => $foot['foot_leader']['nom'],
                'total_members' => $foot['total_members']
            ];

            // Ajouter tous les membres du pied
            foreach ($foot['members'] as $member) {
                $formatted[] = array_merge($member, [
                    'is_main' => false,
                    'is_foot_leader' => $member['niveau'] === 1,
                    'foot_number' => $foot['foot_number']
                ]);
            }
        }

        return $formatted;
    }

    /**
     * Export vers Excel avec organisation par pieds
     * Retourne une instance de l'export, pas le fichier Excel lui-même
     */
    public function getExcelExport(array $networkData): NetworkByFeetExport
    {
        return new NetworkByFeetExport($networkData);
    }

    /**
     * Génère un résumé statistique par pied
     */
    public function getStatsByFeet(array $networkData): array
    {
        $stats = [];

        foreach ($networkData['feet'] as $foot) {
            $members = collect($foot['members']);
            
            $stats[] = [
                'foot_number' => $foot['foot_number'],
                'foot_leader' => $foot['foot_leader']['nom'],
                'total_members' => $foot['total_members'],
                'total_pv' => $members->sum('new_cumul'),
                'cumul_collectif' => $members->sum('cumul_collectif'),
                'grades' => $members->groupBy('etoiles')->map->count(),
                'max_depth' => $members->max('niveau')
            ];
        }

        return [
            'by_feet' => $stats,
            'totals' => [
                'feet' => count($stats),
                'members' => array_sum(array_column($stats, 'total_members')),
                'pv' => array_sum(array_column($stats, 'total_pv')),
                'cumul_collectif' => array_sum(array_column($stats, 'cumul_collectif'))
            ]
        ];
    }
}