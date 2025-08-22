<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Session;

class CartService
{
    /**
     * Obtient le panier d'un distributeur
     */
    public function getCart(int $distributeurId): Collection
    {
        $cartKey = "cart_{$distributeurId}";
        $cart = Session::get($cartKey, []);

        // Convertir en collection avec les produits
        return collect($cart)->map(function ($item) {
            $product = Product::find($item['product_id']);

            if (!$product) {
                return null;
            }

            return [
                'id' => $item['id'] ?? uniqid(),
                'product' => $product,
                'quantity' => $item['quantity'],
                'unit_price' => $product->prix_product,
                'unit_points' => $product->point_product,
                'total_price' => $product->prix_product * $item['quantity'],
                'total_points' => $product->point_product * $item['quantity']
            ];
        })->filter()->values();
    }

    /**
     * Ajoute un article au panier
     */
    public function addItem(int $distributeurId, int $productId, int $quantity): void
    {
        $cartKey = "cart_{$distributeurId}";
        $cart = Session::get($cartKey, []);

        // Vérifier si le produit existe déjà
        $found = false;
        foreach ($cart as &$item) {
            if ($item['product_id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }

        // Si pas trouvé, ajouter nouveau
        if (!$found) {
            $cart[] = [
                'id' => uniqid(),
                'product_id' => $productId,
                'quantity' => $quantity
            ];
        }

        Session::put($cartKey, $cart);
    }

    /**
     * Met à jour la quantité d'un article
     */
    public function updateQuantity(int $distributeurId, string $itemId, int $quantity): void
    {
        $cartKey = "cart_{$distributeurId}";
        $cart = Session::get($cartKey, []);

        foreach ($cart as &$item) {
            if ($item['id'] == $itemId) {
                $item['quantity'] = $quantity;
                break;
            }
        }

        Session::put($cartKey, $cart);
    }

    /**
     * Retire un article du panier
     */
    public function removeItem(int $distributeurId, string $itemId): void
    {
        $cartKey = "cart_{$distributeurId}";
        $cart = Session::get($cartKey, []);

        $cart = array_filter($cart, function ($item) use ($itemId) {
            return $item['id'] != $itemId;
        });

        Session::put($cartKey, array_values($cart));
    }

    /**
     * Vide le panier
     */
    public function clearCart(int $distributeurId): void
    {
        $cartKey = "cart_{$distributeurId}";
        Session::forget($cartKey);
    }

    /**
     * Calcule les totaux du panier
     */
    public function calculateTotals(Collection $cart): array
    {
        $subtotal = $cart->sum('total_price');
        $totalPoints = $cart->sum('total_points');
        $itemCount = $cart->sum('quantity');

        // TVA (20% par défaut)
        $vatRate = 0.20;
        $vatAmount = $subtotal * $vatRate;
        $total = $subtotal + $vatAmount;

        return [
            'subtotal' => $subtotal,
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'total' => $total,
            'total_points' => $totalPoints,
            'item_count' => $itemCount
        ];
    }

    /**
     * Valide le panier avant commande
     */
    public function validateCart(Collection $cart): array
    {
        $errors = [];

        foreach ($cart as $item) {
            $product = $item['product'];

            // Vérifier la disponibilité
            if (!$product->is_active) {
                $errors[] = "Le produit {$product->nom_produit} n'est plus disponible";
            }

            // Vérifier le stock si applicable
            if (isset($product->stock_quantity) && $product->stock_quantity < $item['quantity']) {
                $errors[] = "Stock insuffisant pour {$product->nom_produit}";
            }

            // Vérifier la quantité minimum
            if (isset($product->min_quantity) && $item['quantity'] < $product->min_quantity) {
                $errors[] = "Quantité minimum non atteinte pour {$product->nom_produit}";
            }
        }

        return $errors;
    }
}
