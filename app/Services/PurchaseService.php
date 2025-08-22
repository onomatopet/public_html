<?php

namespace App\Services;

use App\Models\Achat;
use App\Models\AchatSession;
use App\Models\Distributeur;
use App\Models\SystemPeriod;
use App\Models\ActivityLog;
use App\Notifications\OrderConfirmation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Barryvdh\DomPDF\Facade\Pdf;

class PurchaseService
{
    /**
     * Crée une commande à partir du panier
     */
    public function createOrder(Distributeur $distributeur, Collection $cart, array $orderData): Collection
    {
        $currentPeriod = SystemPeriod::getCurrentPeriod();

        // Créer une session d'achat
        $session = AchatSession::create([
            'session_code' => $this->generateSessionCode(),
            'distributeur_id' => $distributeur->id,
            'period' => $currentPeriod->period,
            'total_amount' => 0,
            'total_points' => 0,
            'status' => 'pending',
            'payment_method' => $orderData['payment_method'],
            'shipping_address' => $orderData['shipping_address'],
            'shipping_phone' => $orderData['shipping_phone'],
            'notes' => $orderData['notes'] ?? null
        ]);

        $orders = collect();
        $totalAmount = 0;
        $totalPoints = 0;

        // Créer un achat pour chaque article
        foreach ($cart as $item) {
            $product = $item['product'];

            $achat = Achat::create([
                'period' => $currentPeriod->period,
                'distributeur_id' => $distributeur->id,
                'products_id' => $product->id,
                'purchase_date' => now()->format('Y-m-d'),
                'qt' => $item['quantity'],
                'prix_unitaire_achat' => $product->prix_product,
                'montant_total_ligne' => $item['total_price'],
                'points_unitaire_achat' => $product->point_product,
                'points_total_ligne' => $item['total_points'],
                'status' => 'pending',
                'session_id' => $session->id,
                'created_by' => $distributeur->user_id
            ]);

            $orders->push($achat);
            $totalAmount += $item['total_price'];
            $totalPoints += $item['total_points'];
        }

        // Mettre à jour la session
        $session->update([
            'total_amount' => $totalAmount,
            'total_points' => $totalPoints
        ]);

        // Logger l'activité
        ActivityLog::log(
            'create',
            "Nouvelle commande créée : {$session->session_code}",
            $session,
            [
                'items_count' => $orders->count(),
                'total_amount' => $totalAmount,
                'total_points' => $totalPoints
            ]
        );

        return $orders;
    }

    /**
     * Génère un code de session unique
     */
    protected function generateSessionCode(): string
    {
        do {
            $code = 'CMD-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        } while (AchatSession::where('session_code', $code)->exists());

        return $code;
    }

    /**
     * Envoie la confirmation de commande
     */
    public function sendOrderConfirmation($order): void
    {
        // Si c'est une collection, prendre la session du premier
        if ($order instanceof Collection) {
            $session = $order->first()->session;
            $user = $order->first()->distributeur->user;
        } else {
            $session = $order->session;
            $user = $order->distributeur->user;
        }

        // Envoyer la notification
        if ($user && $user->email) {
            $user->notify(new OrderConfirmation($session));
        }
    }

    /**
     * Génère une facture PDF
     */
    public function generateInvoice($purchase)
    {
        $data = [
            'purchase' => $purchase,
            'distributeur' => $purchase->distributeur,
            'product' => $purchase->product,
            'invoice_number' => $this->generateInvoiceNumber($purchase),
            'invoice_date' => now()
        ];

        return Pdf::loadView('distributor.purchases.invoice', $data);
    }

    /**
     * Génère un numéro de facture
     */
    protected function generateInvoiceNumber($purchase): string
    {
        return 'FAC-' . $purchase->period . '-' . str_pad($purchase->id, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Vérifie si un achat peut être retourné
     */
    public function canReturn(Achat $purchase): bool
    {
        // Politique de retour : 14 jours
        $returnDeadline = $purchase->created_at->addDays(14);

        if (now()->gt($returnDeadline)) {
            return false;
        }

        // Vérifier le statut
        if (!in_array($purchase->status, ['validated', 'delivered'])) {
            return false;
        }

        // Vérifier s'il n'y a pas déjà un retour en cours
        if ($purchase->returns()->where('status', 'pending')->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Crée une demande de retour
     */
    public function createReturnRequest(Achat $purchase, array $data)
    {
        return DB::transaction(function () use ($purchase, $data) {
            $return = $purchase->returns()->create([
                'reason' => $data['reason'],
                'status' => 'pending',
                'items' => $data['items'] ?? null,
                'created_by' => auth()->id()
            ]);

            // Mettre à jour le statut de l'achat
            $purchase->update(['status' => 'return_pending']);

            // Logger l'activité
            ActivityLog::log(
                'create',
                "Demande de retour créée pour l'achat #{$purchase->id}",
                $return,
                ['reason' => $data['reason']]
            );

            return $return;
        });
    }

    /**
     * Valide une commande
     */
    public function validateOrder(AchatSession $session): bool
    {
        return DB::transaction(function () use ($session) {
            // Valider tous les achats de la session
            $session->achats()->update([
                'status' => 'validated',
                'validated_at' => now()
            ]);

            // Mettre à jour la session
            $session->update(['status' => 'validated']);

            // Appliquer les points au distributeur
            $this->applyPointsToDistributor($session);

            return true;
        });
    }

    /**
     * Applique les points au distributeur
     */
    protected function applyPointsToDistributor(AchatSession $session): void
    {
        $distributeur = $session->distributeur;
        $period = $session->period;
        $totalPoints = $session->total_points;

        // Mettre à jour ou créer le level_current
        $levelCurrent = \App\Models\LevelCurrent::updateOrCreate(
            [
                'distributeur_id' => $distributeur->id,
                'period' => $period
            ],
            [
                'pv' => DB::raw("pv + {$totalPoints}"),
                'new_cumul' => DB::raw("new_cumul + {$totalPoints}")
            ]
        );

        // Propager dans la hiérarchie si nécessaire
        if ($distributeur->parent) {
            $this->propagatePointsToParent($distributeur->parent, $totalPoints, $period);
        }
    }

    /**
     * Propage les points au parent
     */
    protected function propagatePointsToParent(Distributeur $parent, int $points, string $period): void
    {
        \App\Models\LevelCurrent::updateOrCreate(
            [
                'distributeur_id' => $parent->id,
                'period' => $period
            ],
            [
                'pg' => DB::raw("pg + {$points}")
            ]
        );

        // Continuer la propagation si nécessaire
        if ($parent->parent) {
            $this->propagatePointsToParent($parent->parent, $points, $period);
        }
    }

    /**
     * Annule une commande
     */
    public function cancelOrder(AchatSession $session, string $reason): bool
    {
        if (!in_array($session->status, ['pending', 'validated'])) {
            return false;
        }

        return DB::transaction(function () use ($session, $reason) {
            // Annuler tous les achats
            $session->achats()->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancellation_reason' => $reason
            ]);

            // Mettre à jour la session
            $session->update([
                'status' => 'cancelled',
                'cancelled_at' => now()
            ]);

            // Si la commande était validée, retirer les points
            if ($session->status === 'validated') {
                $this->removePointsFromDistributor($session);
            }

            // Logger
            ActivityLog::log(
                'update',
                "Commande {$session->session_code} annulée",
                $session,
                ['reason' => $reason]
            );

            return true;
        });
    }

    /**
     * Retire les points d'un distributeur
     */
    protected function removePointsFromDistributor(AchatSession $session): void
    {
        $distributeur = $session->distributeur;
        $period = $session->period;
        $totalPoints = $session->total_points;

        $levelCurrent = \App\Models\LevelCurrent::where('distributeur_id', $distributeur->id)
            ->where('period', $period)
            ->first();

        if ($levelCurrent) {
            $levelCurrent->update([
                'pv' => max(0, $levelCurrent->pv - $totalPoints),
                'new_cumul' => max(0, $levelCurrent->new_cumul - $totalPoints)
            ]);
        }

        // Retirer aussi des parents
        if ($distributeur->parent) {
            $this->removePointsFromParent($distributeur->parent, $totalPoints, $period);
        }
    }

    /**
     * Retire les points du parent
     */
    protected function removePointsFromParent(Distributeur $parent, int $points, string $period): void
    {
        $levelCurrent = \App\Models\LevelCurrent::where('distributeur_id', $parent->id)
            ->where('period', $period)
            ->first();

        if ($levelCurrent) {
            $levelCurrent->update([
                'pg' => max(0, $levelCurrent->pg - $points)
            ]);
        }

        if ($parent->parent) {
            $this->removePointsFromParent($parent->parent, $points, $period);
        }
    }

    /**
     * Récupère les statistiques d'achat d'un distributeur
     */
    public function getDistributorPurchaseStats(int $distributeurId, string $period = null): array
    {
        $query = Achat::where('distributeur_id', $distributeurId);

        if ($period) {
            $query->where('period', $period);
        }

        return [
            'total_orders' => $query->count(),
            'total_amount' => $query->sum('montant_total_ligne'),
            'total_points' => $query->sum('points_total_ligne'),
            'average_order' => $query->avg('montant_total_ligne') ?? 0,
            'last_order_date' => $query->max('purchase_date')
        ];
    }
}
