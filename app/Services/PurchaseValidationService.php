<?php
// app/Services/PurchaseValidationService.php

namespace App\Services;

use App\Models\Achat;
use App\Models\Distributeur;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseValidationService
{
    /**
     * Valide tous les achats d'une période
     *
     * @param string $period
     * @return array ['success' => bool, 'validated' => int, 'rejected' => int, 'message' => string]
     */
    public function validatePeriodPurchases(string $period): array
    {
        $validated = 0;
        $rejected = 0;
        $errors = [];

        try {
            DB::beginTransaction();

            // Récupérer tous les achats non validés de la période
            $purchases = Achat::where('period', $period)
                ->whereNull('validated_at')
                ->orWhere('status', 'pending')
                ->get();

            if ($purchases->isEmpty()) {
                return [
                    'success' => true,
                    'validated' => 0,
                    'rejected' => 0,
                    'message' => 'Aucun achat à valider pour cette période.'
                ];
            }

            foreach ($purchases as $purchase) {
                $validationResult = $this->validateSinglePurchase($purchase);

                if ($validationResult['valid']) {
                    $purchase->update([
                        'status' => 'validated',
                        'validated_at' => now(),
                        'validation_errors' => null
                    ]);
                    $validated++;
                } else {
                    $purchase->update([
                        'status' => 'rejected',
                        'validated_at' => now(),
                        'validation_errors' => json_encode($validationResult['errors'])
                    ]);
                    $rejected++;
                    $errors[] = "Achat #{$purchase->id}: " . implode(', ', $validationResult['errors']);
                }
            }

            DB::commit();

            $message = "Validation terminée: {$validated} validés, {$rejected} rejetés.";
            if (!empty($errors) && $rejected <= 10) {
                $message .= "\nErreurs: " . implode("\n", array_slice($errors, 0, 5));
            }

            return [
                'success' => true,
                'validated' => $validated,
                'rejected' => $rejected,
                'message' => $message,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur lors de la validation des achats', [
                'period' => $period,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'validated' => $validated,
                'rejected' => $rejected,
                'message' => 'Erreur lors de la validation: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Valide un achat individuel
     *
     * @param Achat $purchase
     * @return array ['valid' => bool, 'errors' => array]
     */
    protected function validateSinglePurchase(Achat $purchase): array
    {
        $errors = [];

        // Vérifier l'existence du distributeur
        if (!Distributeur::where('id', $purchase->distributeur_id)->exists()) {
            $errors[] = "Distributeur ID {$purchase->distributeur_id} introuvable";
        }

        // Vérifier l'existence du produit
        if (!Product::where('id', $purchase->products_id)->exists()) {
            $errors[] = "Produit ID {$purchase->products_id} introuvable";
        }

        // Vérifier les quantités
        if ($purchase->qt <= 0) {
            $errors[] = "Quantité invalide: {$purchase->qt}";
        }

        // Vérifier les montants
        if ($purchase->montant_total_ligne <= 0) {
            $errors[] = "Montant total invalide: {$purchase->montant_total_ligne}";
        }

        // Vérifier la cohérence des calculs
        $expectedTotal = $purchase->prix_unitaire_achat * $purchase->qt;
        if (abs($expectedTotal - $purchase->montant_total_ligne) > 0.01) {
            $errors[] = "Incohérence de calcul: {$expectedTotal} != {$purchase->montant_total_ligne}";
        }

        // Vérifier les points
        if ($purchase->points_unitaire_achat < 0) {
            $errors[] = "Points négatifs: {$purchase->points_unitaire_achat}";
        }

        // Vérifier la date d'achat
        if ($purchase->purchase_date) {
            $purchaseMonth = \Carbon\Carbon::parse($purchase->purchase_date)->format('Y-m');
            if ($purchaseMonth !== $purchase->period) {
                $errors[] = "Date d'achat ({$purchase->purchase_date}) ne correspond pas à la période ({$purchase->period})";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Obtient les statistiques de validation pour une période
     *
     * @param string $period
     * @return array ['total' => int, 'validated' => int, 'pending' => int, 'rejected' => int]
     */
    public function getValidationStats(string $period): array
    {
        $stats = Achat::where('period', $period)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'validated' THEN 1 ELSE 0 END) as validated,
                SUM(CASE WHEN status = 'pending' OR status IS NULL THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
            ")
            ->first();

        return [
            'total' => $stats->total ?? 0,
            'validated' => $stats->validated ?? 0,
            'pending' => $stats->pending ?? 0,
            'rejected' => $stats->rejected ?? 0,
            'progress' => $stats->total > 0 ? round(($stats->validated / $stats->total) * 100, 2) : 0
        ];
    }

    /**
     * Récupère les achats rejetés avec leurs erreurs
     *
     * @param string $period
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getRejectedPurchases(string $period, int $limit = 50)
    {
        return Achat::where('period', $period)
            ->where('status', 'rejected')
            ->with(['distributeur', 'product'])
            ->orderBy('validated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($purchase) {
                return [
                    'id' => $purchase->id,
                    'distributeur' => $purchase->distributeur->full_name ?? 'N/A',
                    'product' => $purchase->product->nom_produit ?? 'N/A',
                    'montant' => $purchase->montant_total_ligne,
                    'errors' => json_decode($purchase->validation_errors, true) ?? [],
                    'rejected_at' => $purchase->validated_at
                ];
            });
    }
}
