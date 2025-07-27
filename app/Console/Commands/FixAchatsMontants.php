<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Achat;
use App\Models\Product;
use App\Models\Distributeur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixAchatsMontants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:achats-montants
                            {--limit=100 : Nombre d\'achats à traiter par batch}
                            {--max=0 : Nombre maximum d\'achats à traiter au total (0 = tous)}
                            {--dry-run : Simuler sans appliquer les modifications}
                            {--start-id=0 : ID de départ}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige les montants et prix manquants dans la table achats';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $maxTotal = (int) $this->option('max');
        $dryRun = $this->option('dry-run');
        $startId = (int) $this->option('start-id');

        $this->info('=== Correction des montants dans la table achats ===');
        if ($dryRun) {
            $this->warn('Mode simulation activé - Aucune modification ne sera appliquée');
        }

        // Compter le total d'achats à traiter
        $totalQuery = Achat::where('id', '>', $startId)
            ->where(function($query) {
                $query->where(function($q) {
                    $q->where('montant_total_ligne', 0)
                      ->orWhereNull('montant_total_ligne');
                })
                ->orWhere(function($q) {
                    $q->where('prix_unitaire_achat', 0)
                      ->orWhereNull('prix_unitaire_achat');
                })
                ->orWhere(function($q) {
                    $q->where('points_unitaire_achat', 0)
                      ->orWhereNull('points_unitaire_achat');
                });
            });

        $totalCount = $totalQuery->count();

        if ($totalCount === 0) {
            $this->info('Aucun achat à corriger trouvé.');
            return 0;
        }

        // Si max est défini, limiter le nombre total
        $targetCount = $maxTotal > 0 ? min($maxTotal, $totalCount) : $totalCount;

        $this->info("Total d'achats à corriger : {$totalCount}");
        if ($maxTotal > 0) {
            $this->info("Limite fixée à : {$targetCount} achats");
        }

        if (!$dryRun && !$this->confirm("Voulez-vous traiter {$targetCount} achats ?")) {
            return 0;
        }

        $bar = $this->output->createProgressBar($targetCount);
        $bar->start();

        $processed = 0;
        $updated = 0;
        $errors = 0;
        $errorDetails = [];

        // Traiter par batch
        $lastProcessedId = $startId;

        while ($processed < $targetCount) {
            // Calculer combien d'enregistrements traiter dans ce batch
            $batchLimit = min($limit, $targetCount - $processed);

            $achats = Achat::where('id', '>', $lastProcessedId)
                ->where(function($query) {
                    $query->where(function($q) {
                        $q->where('montant_total_ligne', 0)
                          ->orWhereNull('montant_total_ligne');
                    })
                    ->orWhere(function($q) {
                        $q->where('prix_unitaire_achat', 0)
                          ->orWhereNull('prix_unitaire_achat');
                    })
                    ->orWhere(function($q) {
                        $q->where('points_unitaire_achat', 0)
                          ->orWhereNull('points_unitaire_achat');
                    });
                })
                ->orderBy('id', 'asc')
                ->limit($batchLimit)
                ->get();

            if ($achats->isEmpty()) {
                break;
            }

            foreach ($achats as $achat) {
                try {
                    $result = $this->processAchat($achat, $dryRun);

                    if ($result['updated']) {
                        $updated++;
                    }

                    if ($result['error']) {
                        $errors++;
                        $errorDetails[] = $result['error'];
                    }

                } catch (\Exception $e) {
                    $errors++;
                    $errorDetails[] = "Achat ID {$achat->id}: " . $e->getMessage();
                    Log::error("Erreur fix achats montants", [
                        'achat_id' => $achat->id,
                        'error' => $e->getMessage()
                    ]);
                }

                $processed++;
                $bar->advance();

                // Mettre à jour le dernier ID traité
                $lastProcessedId = $achat->id;

                // Arrêter si on a atteint la limite
                if ($processed >= $targetCount) {
                    break;
                }
            }

            // Si on n'a pas traité le nombre d'enregistrements demandé, c'est qu'on a fini
            if ($achats->count() < $batchLimit) {
                break;
            }

            // Pause entre les batches pour éviter la surcharge
            usleep(100000); // 0.1 seconde
        }

        $bar->finish();
        $this->newLine(2);

        // Afficher le résumé
        $this->info('=== Résumé ===');
        $this->info("Achats traités : {$processed}");
        $this->info("Achats mis à jour : {$updated}");

        if ($errors > 0) {
            $this->error("Erreurs rencontrées : {$errors}");

            if ($this->option('verbose') && !empty($errorDetails)) {
                $this->error('Détails des erreurs :');
                foreach (array_slice($errorDetails, 0, 10) as $error) {
                    $this->error("- {$error}");
                }
                if (count($errorDetails) > 10) {
                    $this->error('... et ' . (count($errorDetails) - 10) . ' autres erreurs');
                }
            }
        }

        if ($processed < $totalCount && $maxTotal == 0) {
            $this->warn("\nAttention : Il reste " . ($totalCount - $processed) . " achats à traiter.");
            $this->info("Relancez la commande avec --start-id={$lastProcessedId} pour continuer.");
        }

        if ($dryRun) {
            $this->warn("\nSimulation terminée - Aucune modification appliquée");
            $this->info('Lancez la commande sans --dry-run pour appliquer les corrections');
        }

        return 0;
    }

    /**
     * Traiter un achat individuel
     */
    private function processAchat(Achat $achat, bool $dryRun): array
    {
        $result = [
            'updated' => false,
            'error' => null,
            'changes' => []
        ];

        try {
            // 1. Vérifier et corriger le distributeur_id si nécessaire
            $distributeurIdCorrected = false;

            // Si distributeur_id est un matricule (7 caractères)
            if (strlen($achat->distributeur_id) == 7) {
                $distributeur = Distributeur::where('distributeur_id', $achat->distributeur_id)->first();

                if ($distributeur) {
                    $result['changes']['distributeur_id'] = [
                        'old' => $achat->distributeur_id,
                        'new' => $distributeur->id
                    ];

                    if (!$dryRun) {
                        $achat->distributeur_id = $distributeur->id;
                    }

                    $distributeurIdCorrected = true;
                } else {
                    $result['error'] = "Achat ID {$achat->id}: Distributeur matricule {$achat->distributeur_id} introuvable";
                    return $result;
                }
            }

            // 2. Récupérer le produit avec sa valeur en points
            $product = Product::with('pointValeur')->find($achat->products_id);

            if (!$product) {
                $result['error'] = "Achat ID {$achat->id}: Produit ID {$achat->products_id} introuvable";
                return $result;
            }

            // 3. Calculer les valeurs manquantes
            $needsUpdate = false;

            // Prix unitaire
            if (empty($achat->prix_unitaire_achat) || $achat->prix_unitaire_achat == 0) {
                $prix_unitaire = $product->prix_product ?? 0;

                if ($prix_unitaire > 0) {
                    $result['changes']['prix_unitaire_achat'] = [
                        'old' => $achat->prix_unitaire_achat,
                        'new' => $prix_unitaire
                    ];

                    if (!$dryRun) {
                        $achat->prix_unitaire_achat = $prix_unitaire;
                    }
                    $needsUpdate = true;
                }
            } else {
                $prix_unitaire = $achat->prix_unitaire_achat;
            }

            // Points unitaires
            if (empty($achat->points_unitaire_achat) || $achat->points_unitaire_achat == 0) {
                $points_unitaire = optional($product->pointValeur)->numbers ?? 0;

                // Si on ne trouve pas de points, on met 1 par défaut pour éviter les divisions par zéro
                if ($points_unitaire == 0 && $product->pointvaleur_id) {
                    $points_unitaire = 1;
                    $this->warn("Produit {$product->id} a un pointvaleur_id mais pas de valeur en points");
                }

                if ($points_unitaire > 0) {
                    $result['changes']['points_unitaire_achat'] = [
                        'old' => $achat->points_unitaire_achat,
                        'new' => $points_unitaire
                    ];

                    if (!$dryRun) {
                        $achat->points_unitaire_achat = $points_unitaire;
                    }
                    $needsUpdate = true;
                }
            }

            // Montant total
            if (empty($achat->montant_total_ligne) || $achat->montant_total_ligne == 0) {
                // S'assurer qu'on a une quantité valide
                $quantite = max(1, $achat->qt ?? 1);
                $montant_total = $prix_unitaire * $quantite;

                if ($montant_total > 0) {
                    $result['changes']['montant_total_ligne'] = [
                        'old' => $achat->montant_total_ligne,
                        'new' => $montant_total
                    ];

                    if (!$dryRun) {
                        $achat->montant_total_ligne = $montant_total;
                    }
                    $needsUpdate = true;
                }
            }

            // 4. Sauvegarder si nécessaire
            if (($needsUpdate || $distributeurIdCorrected) && !$dryRun) {
                $achat->save();
                $result['updated'] = true;
            } elseif ($needsUpdate || $distributeurIdCorrected) {
                $result['updated'] = true; // Pour la simulation
            }

            // Log des changements en mode verbose
            if ($this->option('verbose') && !empty($result['changes'])) {
                $this->info("\nAchat ID {$achat->id} - Changements :");
                foreach ($result['changes'] as $field => $change) {
                    $this->line("  {$field}: {$change['old']} → {$change['new']}");
                }
            }

        } catch (\Exception $e) {
            $result['error'] = "Achat ID {$achat->id}: " . $e->getMessage();
            Log::error("Erreur traitement achat", [
                'achat_id' => $achat->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }
}
