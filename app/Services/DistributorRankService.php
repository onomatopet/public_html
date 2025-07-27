<?php

        namespace App\Services; // Ou votre namespace approprié

        use App\Models\Distributeur; // Assurez-vous que le chemin est correct
        use Illuminate\Support\Collection;
        use Illuminate\Support\Facades\Log;
        // use Illuminate\Support\Facades\DB; // Plus utilisé directement

        class DistributorRankService
        {
            /**
             * Map de la descendance chargée.
             * Structure: [parent_PRIMARY_KEY_ID => Collection [child_MATRICULE => childObject(id, id_distrib_parent, distributeur_id, etoiles_id)]]
             * @var Collection|null
             */
            private ?Collection $descendantsMap = null;

            /**
             * Map inversée pour retrouver rapidement l'ID primaire à partir d'un matricule.
             * Structure: [child_MATRICULE => child_PRIMARY_KEY_ID]
             * @var Collection|null
             */
            private ?Collection $matriculeToIdMap = null;

            /**
             * Map pour retrouver l'objet distributeur complet à partir de son ID primaire.
             * Structure: [child_PRIMARY_KEY_ID => childObject(id, id_distrib_parent, distributeur_id, etoiles_id)]
             * @var Collection|null
             */
            private ?Collection $distributorObjectMap = null;

            /**
             * L'ID primaire de la racine pour laquelle les maps ont été chargées.
             * @var int|null
             */
            private ?int $loadedRootId = null;


            /**
             * Orchestre une vérification de qualification multi-niveaux basée sur les MATRICULES.
             * Charge la descendance UNE SEULE FOIS au début.
             * 1. Compte les branches qualifiées au niveau N.
             * 2. Pour les branches échouées, compte celles qui se qualifient au niveau N-1.
             * 3. Liste les MATRICULES des enfants directs dont les branches ont échoué aux deux niveaux.
             *
             * @param int $rootParentId     L'ID PRIMAIRE du distributeur racine initial.
             * @param int $initialLevel     Le niveau requis pour la première passe (N).
             * @return array                Résultat avec comptes séparés et MATRICULES non qualifiés.
             *                              Ex: [
             *                                  'level_n_qualified_count' => 2,
             *                                  'level_n_minus_1_qualified_count' => 2,
             *                                  'finally_unqualified_root_matricules' => [D.matricule]
             *                              ]
             */
            public function checkMultiLevelQualificationSeparateCountsMatricule(int $rootParentId, int $initialLevel): array
            {
                $levelN = max(1, $initialLevel);
                $levelNMinus1 = max(1, $levelN - 1);

                Log::info("Début vérif multi-niveaux MATRICULE pour Parent ID: {$rootParentId}, N >= {$levelN}, N-1 >= {$levelNMinus1}");

                // --- CHARGEMENT UNIQUE DE LA DESCENDANCE ET DES MAPS ---
                try {
                     $this->loadAllDescendantsMatricule($rootParentId);
                } catch (\RuntimeException $e) {
                     Log::critical("Échec critique lors du chargement des descendants: " . $e->getMessage());
                     return [
                         'level_n_qualified_count' => 0,
                         'level_n_minus_1_qualified_count' => 0,
                         'finally_unqualified_root_matricules' => [],
                         'error' => $e->getMessage() // Signaler l'erreur (ex: doublon matricule)
                     ];
                }

                if ($this->descendantsMap === null || $this->matriculeToIdMap === null || $this->distributorObjectMap === null) {
                     Log::critical("Échec critique: Les maps n'ont pas pu être chargées pour la racine {$rootParentId}.");
                     return [
                         'level_n_qualified_count' => 0,
                         'level_n_minus_1_qualified_count' => 0,
                         'finally_unqualified_root_matricules' => [],
                         'error' => 'Failed to load descendant data properly.'
                     ];
                }
                // -------------------------------------------------------

                // --- Étape 1 : Vérification au niveau N ---
                // Utilise les maps globales pré-chargées
                $step1ResultsArray = $this->getBranchQualificationStatusMatricule($rootParentId, $levelN);
                $processedStep1 = $this->processQualificationResultsMatricule($step1ResultsArray);
                $levelNQualifiedCount = $processedStep1['qualified_count'];
                $step1UnqualifiedRootMatricules = $processedStep1['unqualified_root_matricules'];

                Log::info("Étape 1 (N >= {$levelN}): {$levelNQualifiedCount} qualifiée(s). " . count($step1UnqualifiedRootMatricules) . " à revérifier.");

                // --- Étape 2 : Revérification au niveau N-1 ---
                $levelNMinus1QualifiedCount = 0;
                $finallyUnqualifiedRootMatricules = [];

                Log::info("Étape 2: Revérification (N >= {$levelNMinus1})");

                if (empty($step1UnqualifiedRootMatricules)) {
                    Log::info("Aucune branche à revérifier.");
                } else {
                    Log::debug("Matricules à revérifier: " . implode(', ', $step1UnqualifiedRootMatricules));
                    foreach ($step1UnqualifiedRootMatricules as $childMatriculeToRecheck) {
                        Log::debug("Revérif branche Matricule: {$childMatriculeToRecheck} / Niveau {$levelNMinus1}");

                        $isQualifiedStep2 = false; // Flag pour cette branche à l'étape 2

                        // 1. Retrouver l'objet distributeur correspondant au matricule via les maps
                        $childPrimaryId = $this->matriculeToIdMap->get($childMatriculeToRecheck);
                        $childObject = $childPrimaryId ? $this->distributorObjectMap->get($childPrimaryId) : null;

                        if ($childObject) {
                            Log::debug("  -> Données trouvées pour Matricule {$childMatriculeToRecheck} (ID: {$childObject->id}, Etoiles: {$childObject->etoiles_id})");

                            // 2. Vérifier si CET enfant direct atteint le niveau N-1
                            if ($childObject->etoiles_id >= $levelNMinus1) {
                                Log::debug("  -> Branche Matricule {$childMatriculeToRecheck}: QUALIFIÉE DIRECTEMENT au niveau {$levelNMinus1}.");
                                $isQualifiedStep2 = true;
                            } else {
                                // 3. Si non, vérifier sa descendance (en passant son MATRICULE)
                                Log::debug("  -> Vérification descendance pour Matricule {$childMatriculeToRecheck} au niveau {$levelNMinus1}...");
                                // checkSubtree utilise les maps globales pré-chargées
                                if ($this->checkSubtreeForLevelOrHigherMatricule($childMatriculeToRecheck, $levelNMinus1)) {
                                     Log::debug("  -> Branche Matricule {$childMatriculeToRecheck}: QUALIFIÉE via DESCENDANCE au niveau {$levelNMinus1}.");
                                     $isQualifiedStep2 = true;
                                } else {
                                     Log::debug("  -> Branche Matricule {$childMatriculeToRecheck}: NON QUALIFIÉE même via descendance au niveau {$levelNMinus1}.");
                                    // $isQualifiedStep2 reste false
                                }
                            }
                        } else {
                            Log::error("Objet Distributeur introuvable pour matricule {$childMatriculeToRecheck} dans map interne lors de la revérification. Branche considérée non qualifiée.");
                            // $isQualifiedStep2 reste false
                        }

                        // 4. Mettre à jour les compteurs en fonction du résultat final pour cette branche
                        if ($isQualifiedStep2) {
                            $levelNMinus1QualifiedCount++;
                        } else {
                            $finallyUnqualifiedRootMatricules[] = $childMatriculeToRecheck;
                        }

                    } // Fin foreach
                }

                // --- Étape 3 : Consolidation et Retour ---
                Log::info("Fin vérif multi-niveaux MATRICULE. N: {$levelNQualifiedCount}. N-1: {$levelNMinus1QualifiedCount}. Échecs Finaux: " . count($finallyUnqualifiedRootMatricules));

                return [
                    'level_n_qualified_count'         => $levelNQualifiedCount,
                    'level_n_minus_1_qualified_count' => $levelNMinus1QualifiedCount,
                    'finally_unqualified_root_matricules'  => $finallyUnqualifiedRootMatricules,
                ];
            }


            /**
             * Vérifie les branches enfants SANS RECHARGER les données globales.
             * Suppose que loadAllDescendantsMatricule a déjà été appelé.
             * Retourne 1 si qualifié, MATRICULE enfant si non qualifié.
             * Rendue privée car elle n'est appelée qu'en interne maintenant.
             *
             * @param int $rootParentId     L'ID PRIMAIRE du distributeur racine de cette sous-vérification.
             * @param int $requiredLevel    Le niveau minimum requis (>=).
             * @return array                Tableau de 1s et MATRICULES enfants.
             */
            private function getBranchQualificationStatusMatricule(int $rootParentId, int $requiredLevel): array
            {
                $comparisonLevel = max(1, $requiredLevel);

                if ($this->descendantsMap === null || $this->matriculeToIdMap === null) {
                    Log::error("getBranchQualificationStatusMatricule appelé alors que les maps ne sont pas chargées (demandé pour {$rootParentId}).");
                    return [];
                }

                $directChildren = $this->descendantsMap->get($rootParentId);

                if (!$directChildren || $directChildren->isEmpty()) return [];

                $branchResults = [];
                foreach ($directChildren as $childMatricule => $child) {
                    $isBranchQualified = false;
                    if ($child->etoiles_id >= $comparisonLevel) {
                        $isBranchQualified = true;
                    } else {
                        // Appel récursif qui utilise les maps globales
                        if ($this->checkSubtreeForLevelOrHigherMatricule($childMatricule, $comparisonLevel)) {
                            $isBranchQualified = true;
                        }
                    }
                    $branchResults[] = $isBranchQualified ? 1 : $childMatricule;
                }
                return $branchResults;
            }


             /**
             * Sépare les résultats en compte qualifié (nombre de 1)
             * et liste des MATRICULES des racines des branches non qualifiées.
             * Rendue privée car elle n'est appelée qu'en interne maintenant.
             *
             * @param array $branchResults Tableau de 1s et MATRICULES.
             * @return array ['qualified_count' => int, 'unqualified_root_matricules' => array].
             */
            private function processQualificationResultsMatricule(array $branchResults): array
            {
                $qualifiedCount = count(array_filter($branchResults, fn($value) => $value === 1));
                $unqualifiedRootMatricules = array_values(array_filter($branchResults, fn($value) => $value !== 1));
                return [
                    'qualified_count' => $qualifiedCount,
                    'unqualified_root_matricules' => $unqualifiedRootMatricules,
                ];
             }


            /**
             * Fonction récursive (en mémoire) basée sur les MATRICULES pour vérifier si un nœud
             * ou l'un de ses descendants atteint OU DÉPASSE le niveau cible.
             * Rendue privée/protégée.
             *
             * @param int|string $parentMatricule LE MATRICULE du nœud de départ.
             * @param int $targetLevel      Le niveau minimum (>=).
             * @return bool                True si trouvé, False sinon.
             */
            private function checkSubtreeForLevelOrHigherMatricule(int|string $parentMatricule, int $targetLevel): bool
            {
                 if ($this->descendantsMap === null || $this->matriculeToIdMap === null) {
                     return false;
                 }
                 $parentId = $this->matriculeToIdMap->get($parentMatricule);
                 if (!$parentId) {
                      return false;
                 }
                 if (!$this->descendantsMap->has($parentId)) {
                    return false;
                 }
                 $children = $this->descendantsMap->get($parentId);
                 if ($children->isEmpty()){
                      return false;
                 }
                 foreach ($children as $childMatricule => $child) {
                     if ($child->etoiles_id >= $targetLevel) {
                         return true;
                     }
                     if ($this->checkSubtreeForLevelOrHigherMatricule($childMatricule, $targetLevel)) {
                         return true;
                     }
                 }
                 return false;
            }


            /**
             * Charge tous les descendants et les organise dans trois Maps :
             * 1. $descendantsMap : [parent_PRIMARY_KEY_ID => Collection [child_MATRICULE => childObject]]
             * 2. $matriculeToIdMap: [child_MATRICULE => child_PRIMARY_KEY_ID]
             * 3. $distributorObjectMap: [child_PRIMARY_KEY_ID => childObject(id, id_distrib_parent, distributeur_id, etoiles_id)]
             * Ne recharge que si l'ID racine change ou si les maps sont vides.
             * Rendue privée/protégée.
             *
             * @param int $rootParentId L'ID PRIMAIRE de la racine.
             * @throws \RuntimeException Si un doublon de matricule est détecté.
             */
            private function loadAllDescendantsMatricule(int $rootParentId): void
            {
                if ($this->descendantsMap !== null && $this->matriculeToIdMap !== null && $this->distributorObjectMap !== null && $this->loadedRootId === $rootParentId) {
                    return;
                }
                Log::info("(Matricule - NoDoctrine) Chargement descendance/maps pour Parent ID: {$rootParentId}");

                $startTime = microtime(true);

                $this->descendantsMap = collect();
                $this->matriculeToIdMap = collect();
                $this->distributorObjectMap = collect();
                $this->loadedRootId = $rootParentId;
                $idsToLoad = [$rootParentId];
                $loadedParentIds = [];
                $iteration = 0;
                $maxIterations = 100; // Augmentez si votre hiérarchie est très profonde

                while (!empty($idsToLoad) && $iteration < $maxIterations) {
                    $iteration++;
                    $currentBatchParentIds = array_diff($idsToLoad, $loadedParentIds);
                    $idsToLoad = [];
                    if (empty($currentBatchParentIds)) break;
                    $loadedParentIds = array_merge($loadedParentIds, $currentBatchParentIds);

                    $childrenBatch = Distributeur::whereIn('id_distrib_parent', $currentBatchParentIds)
                        ->select('id', 'id_distrib_parent', 'distributeur_id', 'etoiles_id')
                        ->get();

                    if ($childrenBatch->isEmpty()) continue;

                    foreach ($childrenBatch as $child) {
                         if (empty($child->distributeur_id)) {
                             Log::warning("(Load) Enfant ID {$child->id} a matricule vide/null. Ignoré.");
                             continue;
                         }
                         if ($this->matriculeToIdMap->has($child->distributeur_id) && $this->matriculeToIdMap->get($child->distributeur_id) !== $child->id) {
                              $msg = "DOUBLON DE MATRICULE DÉTECTÉ: {$child->distributeur_id} (IDs: {$child->id}, {$this->matriculeToIdMap->get($child->distributeur_id)})";
                              Log::critical($msg); // Critique car cela fausse les résultats
                              throw new \RuntimeException($msg);
                         }

                         $this->matriculeToIdMap->put($child->distributeur_id, $child->id);
                         $this->distributorObjectMap->put($child->id, $child);

                         if (!$this->descendantsMap->has($child->id_distrib_parent)) {
                             $this->descendantsMap->put($child->id_distrib_parent, collect());
                         }
                         $this->descendantsMap->get($child->id_distrib_parent)->put($child->distributeur_id, $child);

                         if (!in_array($child->id, $loadedParentIds)) {
                             $idsToLoad[] = $child->id;
                         }
                    }
                    $idsToLoad = array_unique($idsToLoad);
                }

                $endTime = microtime(true);
                $duration = round(($endTime - $startTime) * 1000);

                if ($iteration >= $maxIterations) {
                     Log::error("(Load) Limite {$maxIterations} itérations atteinte pour Parent ID {$rootParentId}. Les maps pourraient être incomplètes.");
                     // Optionnel: Vider les maps pour forcer une erreur plus tard si elles sont utilisées
                     // $this->descendantsMap = null;
                     // $this->matriculeToIdMap = null;
                     // $this->distributorObjectMap = null;
                } else {
                     Log::info("(Load) Descendance chargée en {$duration}ms. Map Desc: {$this->descendantsMap->count()}. Map Matricule->ID: {$this->matriculeToIdMap->count()}. Map ID->Objet: {$this->distributorObjectMap->count()}.");
                }
            }


            // --- Exemple d'utilisation (à placer dans un contrôleur ou une autre classe) ---

            public function demonstrateMultiLevelCheckMatriculeNoDoctrine(int $distributorPrimaryId, int $initialLevel)
            {
                // Correction: Instancier la classe elle-même si appelée depuis un contrôleur
                // Si cette méthode est DANS la classe EternalHelper, utiliser $this
                // $helper = new \App\Services\EternalHelperMatriculeBasedNoDoctrine();
                // $results = $helper->checkMultiLevelQualificationSeparateCountsMatricule($distributorPrimaryId, $initialLevel);
                // Si DANS la classe :
                $results = $this->checkMultiLevelQualificationSeparateCountsMatricule($distributorPrimaryId, $initialLevel);


                if (isset($results['error'])) {
                    echo "ERREUR : " . $results['error'];
                    return;
                }

                try {
                     $levelN = max(1, $initialLevel);
                     $levelNMinus1 = max(1, $levelN - 1);

                     echo "Vérification Multi-Niveau (Basée MATRICULE) pour Distributeur ID Primaire: {$distributorPrimaryId}<br>";
                     echo "Nombre de Branches Qualifiées au Niveau >= {$levelN} : {$results['level_n_qualified_count']}<br>";
                     echo "Nombre de Branches (parmi les précédentes échouées) Qualifiées au Niveau >= {$levelNMinus1} : {$results['level_n_minus_1_qualified_count']}<br>";

                     if (!empty($results['finally_unqualified_root_matricules'])) {
                         echo "Branches commençant par les MATRICULES Enfants suivants n'ont pas été qualifiées même au niveau {$levelNMinus1} : " . implode(', ', $results['finally_unqualified_root_matricules']);
                     } else {
                         echo "Toutes les branches initiales se sont qualifiées à l'un des deux niveaux.";
                     }

                     dd($results);

                 } catch (\RuntimeException $e) { // Attrape l'erreur de doublon de matricule
                      echo "ERREUR CRITIQUE : " . $e->getMessage();
                 } catch (\Exception $e) {
                      echo "ERREUR INATTENDUE : " . $e->getMessage();
                      Log::error("Erreur inattendue dans demonstrateMultiLevelCheck: " . $e->getMessage() . "\n" . $e->getTraceAsString());
                 }
            }

        } // Fin de la classe EternalHelperMatriculeBasedNoDoctrine
