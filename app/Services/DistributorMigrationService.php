<?php

namespace App\Services; // Ou App\Http\Controllers, etc.

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Connection; // Pour le type hinting

class DistributorMigrationService
{
    // Nom de la connexion à la base de données source configurée dans config/database.php
    protected string $sourceConnectionName = 'db_first';
    // Nom de la table source
    protected string $sourceTable = 'distributeurs';
    // Nom complet de la table destination (avec nom de la base)
    protected string $destinationTable = 'DB_second.distributeurs';

    // Tableau pour stocker les instructions INSERT générées
    protected array $insertStatements = [];

    /**
     * Point d'entrée principal pour générer les instructions INSERT.
     *
     * @return array Un tableau contenant les instructions SQL INSERT.
     * @throws \Exception Si la connexion source échoue.
     */
    public function generateMigrationInserts(): array
    {
        $this->insertStatements = []; // Réinitialiser pour chaque appel

        try {
            Log::info("Début de la génération des INSERTs depuis {$this->sourceConnectionName}.{$this->sourceTable}");

            // Établir la connexion à la base de données source
            $connection = DB::connection($this->sourceConnectionName);

            // 1. Trouver le(s) distributeur(s) racine(s)
            //    ATTENTION: Vérifiez si la racine est bien id_distrib_parent = 0 ou IS NULL
            $rootDistributors = $connection->table($this->sourceTable)
                ->where('id_distrib_parent', 0) // Ou ->whereNull('id_distrib_parent') si c'est le cas
                ->get();

            if ($rootDistributors->isEmpty()) {
                Log::warning("Aucun distributeur racine trouvé avec id_distrib_parent = 0 dans {$this->sourceConnectionName}.{$this->sourceTable}");
                return ["-- Aucun distributeur racine trouvé avec id_distrib_parent = 0."];
            }

             Log::info("Racines trouvées : " . $rootDistributors->pluck('id')->implode(', '));


            // 2. Pour chaque racine, générer son INSERT et démarrer la récursion pour ses enfants
            foreach ($rootDistributors as $root) {
                // Générer l'INSERT pour la racine elle-même
                $this->insertStatements[] = $this->formatInsertStatement((array) $root);

                // Démarrer la recherche récursive des enfants
                $this->findAndGenerateChildrenInserts($connection, $root->id);
            }

            Log::info(count($this->insertStatements) . " instructions INSERT générées.");
            return $this->insertStatements;

        } catch (\PDOException $e) {
            Log::error("Erreur de connexion à la base de données source '{$this->sourceConnectionName}': " . $e->getMessage());
            throw new \Exception("Impossible de se connecter à la base de données source '{$this->sourceConnectionName}'. Vérifiez la configuration.", 0, $e);
        } catch (\Exception $e) {
            Log::error("Erreur lors de la génération des instructions INSERT : " . $e->getMessage());
            throw $e; // Relancer l'exception
        }
    }

    /**
     * Fonction récursive pour trouver les enfants et générer leurs instructions INSERT.
     *
     * @param Connection $connection L'objet de connexion à la base de données source.
     * @param int $parentId L'ID (PK) du parent dont on cherche les enfants.
     */
    protected function findAndGenerateChildrenInserts(Connection $connection, int $parentId): void
    {
        // Utiliser cursor() pour gérer potentiellement de nombreux enfants sans saturer la mémoire
        $children = $connection->table($this->sourceTable)
            ->where('id_distrib_parent', $parentId)
            ->cursor(); // ->cursor() est préférable à ->get() pour les grands ensembles

        foreach ($children as $child) {
             // Générer l'INSERT pour cet enfant
            $this->insertStatements[] = $this->formatInsertStatement((array) $child);

            // Appel récursif pour les petits-enfants
            $this->findAndGenerateChildrenInserts($connection, $child->id);
        }
    }

    /**
     * Formate une ligne de données en une instruction SQL INSERT.
     *
     * @param array $data Un tableau associatif représentant une ligne de la table distributeurs.
     * @return string L'instruction SQL INSERT formatée.
     */
    protected function formatInsertStatement(array $data): string
    {
        // Liste des colonnes dans l'ordre attendu par INSERT INTO DB_second...
        $columns = [
            'id', 'etoiles_id', 'rang', 'distributeur_id', 'nom_distributeur',
            'pnom_distributeur', 'tel_distributeur', 'adress_distributeur',
            'id_distrib_parent', 'is_children', 'is_indivual_cumul_checked',
            'created_at', 'updated_at'
        ];

        $values = [];
        foreach ($columns as $col) {
            // Utiliser la valeur de $data si la clé existe, sinon null
            $value = $data[$col] ?? null;

            if ($value === null) {
                // Gérer les colonnes qui pourraient être NOT NULL et n'acceptent pas NULL SQL
                 // Spécifiquement pour id_distrib_parent, si on copie un root qui avait 0 et que la nouvelle table
                 // attend NULL pour les roots, il faudrait une transformation ici.
                 // Pour l'instant, on insère NULL tel quel.
                $values[] = 'NULL';
            } elseif (is_string($value)) {
                // Échappement basique mais suffisant pour la génération de script.
                // Pour une sécurité maximale lors de l'exécution directe, utiliser des requêtes préparées.
                $values[] = "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                 // Si une colonne était booléenne (pas le cas ici)
                 $values[] = $value ? 1 : 0;
            } else { // Assumer numérique (int, float, double)
                $values[] = $value;
            }
        }

        // Construire la requête
        // Utiliser des backticks (`) pour les noms de colonnes est une bonne pratique
        $colsString = '`' . implode('`, `', $columns) . '`';
        $valsString = implode(', ', $values);

        // Utiliser le nom de table complet pour la destination
        return "INSERT INTO {$this->destinationTable} ({$colsString}) VALUES ({$valsString});";
    }
}
