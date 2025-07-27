<?php

namespace App\Http\Controllers;

use App\Services\DistributorMigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MigrationController extends Controller
{
    protected $migrationService;

    public function __construct(DistributorMigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    // Méthode pour afficher la page avec le bouton/lien
    public function showMigrationPage()
    {
        return view('migration.distributors'); // Créez cette vue
    }

    // Méthode appelée pour générer le script SQL
    public function generateSqlScript(Request $request)
    {
        try {
            $sqlStatements = $this->migrationService->generateMigrationInserts();

            // Option 1: Afficher le SQL dans la vue (pour petits volumes)
            // return view('migration.sql_result', ['sqlStatements' => $sqlStatements]);

            // Option 2: Générer un fichier SQL à télécharger
            $fileName = 'distributor_migration_' . date('Ymd_His') . '.sql';
            $content = implode("\n", $sqlStatements);

            return response($content)
                ->header('Content-Type', 'text/plain')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        } catch (\Exception $e) {
            // Rediriger avec un message d'erreur
            Log::error("Erreur critique lors de la génération du script SQL: " . $e->getMessage());
            return redirect()->route('migration.show') // Rediriger vers la page initiale
                     ->with('error', 'Erreur lors de la génération du script SQL : ' . $e->getMessage());
        }
    }
}
