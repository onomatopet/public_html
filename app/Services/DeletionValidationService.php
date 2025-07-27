<?php

namespace App\Services;

use App\Models\Distributeur;
use App\Models\Achat;
use App\Models\Bonus;
use App\Models\LevelCurrent;
use App\Models\AvancementHistory; // Correction : sans le "d"
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeletionValidationService
{
    /**
     * Valide la suppression d'un distributeur
     */
    public function validateDistributeurDeletion(Distributeur $distributeur): array
    {
        $blockers = [];
        $warnings = [];
        $relatedData = [];

        // 1. Vérifier les enfants directs
        $children = Distributeur::where('id_distrib_parent', $distributeur->id)->get();
        if ($children->isNotEmpty()) {
            $blockers[] = "Ce distributeur a {$children->count()} distributeur(s) enfant(s) qui doivent être réassignés avant suppression";
            $relatedData['children'] = $children->map(function ($child) {
                return [
                    'id' => $child->id,
                    'matricule' => $child->distributeur_id,
                    'nom' => $child->full_name
                ];
            })->toArray();
        }

        // 2. Vérifier les achats
        $achats = Achat::where('distributeur_id', $distributeur->id)->get();
        if ($achats->isNotEmpty()) {
            $warnings[] = "Ce distributeur a {$achats->count()} achat(s) qui seront supprimés";
            $relatedData['achats'] = $achats->map(function ($achat) {
                return [
                    'id' => $achat->id,
                    'period' => $achat->period,
                    'montant' => $achat->montant_total_ligne,
                    'points' => $achat->points_unitaire_achat * $achat->qt
                ];
            })->toArray();
        }

        // 3. Vérifier les bonus
        $bonuses = Bonus::where('distributeur_id', $distributeur->id)->get();
        if ($bonuses->isNotEmpty()) {
            $warnings[] = "Ce distributeur a {$bonuses->count()} bonus qui seront supprimés";
            $relatedData['bonuses'] = $bonuses->map(function ($bonus) {
                return [
                    'id' => $bonus->id,
                    'period' => $bonus->period,
                    'montant' => $bonus->montant,
                    'type' => $bonus->type_bonus
                ];
            })->toArray();
        }

        // 4. Vérifier les niveaux actuels
        $levelCurrents = LevelCurrent::where('distributeur_id', $distributeur->id)->get();
        if ($levelCurrents->isNotEmpty()) {
            $warnings[] = "Ce distributeur a {$levelCurrents->count()} enregistrement(s) de niveau qui seront supprimés";
            $relatedData['level_currents'] = $levelCurrents->count();
        }

        // 5. Vérifier l'historique d'avancements
        $avancements = AvancementHistory::where('distributeur_id', $distributeur->id)->get(); // Correction : sans le "d"
        if ($avancements->isNotEmpty()) {
            $warnings[] = "Ce distributeur a {$avancements->count()} historique(s) d'avancement qui seront supprimés";
            $relatedData['advancement_history'] = $avancements->count();
        }

        // 6. Analyser l'impact sur la hiérarchie
        $hierarchyImpact = $this->analyzeHierarchyImpact($distributeur);
        if ($hierarchyImpact['affected_count'] > 0) {
            $warnings[] = "La suppression affectera {$hierarchyImpact['affected_count']} distributeur(s) dans la hiérarchie";
            $relatedData['hierarchy_impact'] = $hierarchyImpact;
        }

        // 7. Vérifier si c'est un distributeur de haut niveau
        if ($distributeur->etoiles_id >= 5) {
            $warnings[] = "Ce distributeur a un grade élevé ({$distributeur->etoiles_id} étoiles)";
        }

        // Déterminer si la suppression est possible
        $canDelete = count($blockers) === 0;
        $requiresApproval = count($warnings) > 0 || $distributeur->etoiles_id >= 3;
        $impactLevel = $this->calculateImpactLevel($blockers, $warnings, $relatedData);

        return [
            'can_delete' => $canDelete,
            'requires_approval' => $requiresApproval,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'impact_level' => $impactLevel,
            'related_data' => $relatedData,
            'summary' => $this->generateSummary($distributeur, $blockers, $warnings)
        ];
    }

    /**
     * Analyse l'impact sur la hiérarchie
     */
    private function analyzeHierarchyImpact(Distributeur $distributeur): array
    {
        $affectedIds = collect();

        // Récupérer tous les descendants
        $descendants = $this->getAllDescendants($distributeur->id);
        $affectedIds = $affectedIds->merge($descendants);

        // Si le distributeur a un parent, ses frères seront aussi affectés
        if ($distributeur->id_distrib_parent) { // CORRECTION ICI
            $siblings = Distributeur::where('id_distrib_parent', $distributeur->id_distrib_parent) // CORRECTION ICI
                                   ->where('id', '!=', $distributeur->id)
                                   ->pluck('id');
            $affectedIds = $affectedIds->merge($siblings);

            // Le parent sera aussi affecté
            $affectedIds->push($distributeur->id_distrib_parent); // CORRECTION ICI
        }

        // Analyser les parents remontants
        $parent = $distributeur->parent;
        while ($parent) {
            $affectedIds->push($parent->id);
            $parent = $parent->id_distrib_parent ? $parent->parent : null; // CORRECTION ICI
        }

        return [
            'affected_count' => $affectedIds->unique()->count(),
            'descendants_count' => $descendants->count(),
            'affected_ids' => $affectedIds->unique()->values()->toArray()
        ];
    }

    /**
     * Récupère tous les descendants d'un distributeur
     */
    private function getAllDescendants(int $distributeurId): \Illuminate\Support\Collection
    {
        $descendants = collect();
        $children = Distributeur::where('id_distrib_parent', $distributeurId)->pluck('id'); // CORRECTION ICI

        foreach ($children as $childId) {
            $descendants->push($childId);
            $descendants = $descendants->merge($this->getAllDescendants($childId));
        }

        return $descendants;
    }

    /**
     * Calcule le niveau d'impact
     */
    private function calculateImpactLevel(array $blockers, array $warnings, array $relatedData): string
    {
        if (count($blockers) > 0) {
            return 'critical';
        }

        $score = 0;
        $score += count($warnings) * 2;
        $score += isset($relatedData['children']) ? count($relatedData['children']) * 3 : 0;
        $score += isset($relatedData['achats']) ? min(count($relatedData['achats']) / 10, 5) : 0;
        $score += isset($relatedData['bonuses']) ? min(count($relatedData['bonuses']) / 5, 5) : 0;

        if ($score >= 15) {
            return 'high';
        } elseif ($score >= 8) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Valide la suppression d'un achat
     */
    public function validateAchatDeletion(Achat $achat): array
    {
        $blockers = [];
        $warnings = [];
        $relatedData = [];

        // 1. Vérifier si l'achat est validé/traité
        if ($achat->validated || $achat->processed) {
            $blockers[] = "Cet achat a déjà été validé/traité et ne peut pas être supprimé directement";
        }

        // 2. Vérifier l'ancienneté
        $ageInDays = $achat->created_at->diffInDays(now());
        if ($ageInDays > 30) {
            $warnings[] = "Cet achat date de plus de 30 jours ({$ageInDays} jours)";
        }

        // 3. Vérifier si des bonus ont été calculés sur cet achat
        $relatedBonuses = Bonus::where('period', $achat->period)
                               ->where('distributeur_id', $achat->distributeur_id)
                               ->exists();
        if ($relatedBonuses) {
            $warnings[] = "Des bonus ont été calculés pour cette période. Recalcul nécessaire après suppression";
        }

        // 4. Vérifier l'impact sur les grades
        $levelHistory = LevelCurrent::where('distributeur_id', $achat->distributeur_id)
                                   ->where('period', $achat->period)
                                   ->first();
        if ($levelHistory) {
            $warnings[] = "La suppression pourrait affecter le grade du distributeur pour cette période";
        }

        // 5. Calculer l'impact financier
        $financialImpact = [
            'montant' => $achat->montant_total_ligne,
            'points' => $achat->points_unitaire_achat * $achat->qt,
            'period' => $achat->period
        ];
        $relatedData['financial_impact'] = $financialImpact;

        $canDelete = count($blockers) === 0;
        $requiresApproval = $achat->validated || count($warnings) > 0;
        $impactLevel = $this->calculateImpactLevel($blockers, $warnings, $relatedData);

        return [
            'can_delete' => $canDelete,
            'requires_approval' => $requiresApproval,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'impact_level' => $impactLevel,
            'related_data' => $relatedData,
            'summary' => "Suppression de l'achat #{$achat->id} - Montant: " . number_format($achat->montant_total_ligne, 0, ',', ' ') . " FCFA"
        ];
    }

    /**
     * Génère un résumé de la validation
     */
    private function generateSummary($entity, array $blockers, array $warnings): string
    {
        $summary = "";

        if ($entity instanceof Distributeur) {
            $summary = "Suppression du distributeur {$entity->full_name} (#{$entity->distributeur_id})";
        } elseif ($entity instanceof Achat) {
            $summary = "Suppression de l'achat #{$entity->id}";
        }

        if (count($blockers) > 0) {
            $summary .= " - BLOQUÉE : " . count($blockers) . " problème(s) bloquant(s)";
        } elseif (count($warnings) > 0) {
            $summary .= " - ATTENTION : " . count($warnings) . " avertissement(s)";
        } else {
            $summary .= " - Suppression simple sans impact majeur";
        }

        return $summary;
    }

    /**
     * Suggère des actions de nettoyage avant suppression
     */
    public function suggestCleanupActions(array $validationResult): array
    {
        $suggestions = [];

        if (!$validationResult['can_delete']) {
            foreach ($validationResult['blockers'] as $blocker) {
                if (str_contains($blocker, 'enfant')) {
                    $suggestions[] = [
                        'action' => 'reassign_children',
                        'description' => 'Réassigner tous les distributeurs enfants à un autre parent',
                        'priority' => 'high'
                    ];
                }
            }
        }

        foreach ($validationResult['warnings'] as $warning) {
            if (str_contains($warning, 'achat')) {
                $suggestions[] = [
                    'action' => 'archive_achats',
                    'description' => 'Archiver les achats avant suppression',
                    'priority' => 'medium'
                ];
            }
            if (str_contains($warning, 'bonus')) {
                $suggestions[] = [
                    'action' => 'recalculate_bonuses',
                    'description' => 'Recalculer les bonus après suppression',
                    'priority' => 'medium'
                ];
            }
        }

        return $suggestions;
    }
}
