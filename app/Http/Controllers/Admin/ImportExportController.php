<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Imports\DistributeursImport;
use App\Imports\AchatsImport;
use App\Imports\ProductsImport;
use App\Exports\DistributeursExport;
use App\Exports\AchatsExport;
use App\Exports\ProductsExport;
use App\Exports\BonusExport;
use App\Exports\LevelCurrentsExport;
use App\Exports\AvancementsExport;
use App\Models\ImportExportHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;

class ImportExportController extends Controller
{
    /**
     * Affiche la page d'import/export
     */
    public function index()
    {
        $history = ImportExportHistory::with('user')
            ->orderBy('created_at', 'desc')
            ->take(20)
            ->get();

        // Templates disponibles pour téléchargement
        $templates = [
            'distributeurs' => 'templates/import_distributeurs.xlsx',
            'achats' => 'templates/import_achats.xlsx',
            'produits' => 'templates/import_produits.xlsx'
        ];

        // Statistiques
        $stats = [
            'total_imports' => ImportExportHistory::where('type', 'import')->count(),
            'total_exports' => ImportExportHistory::where('type', 'export')->count(),
            'successful_operations' => ImportExportHistory::where('status', 'completed')->count(),
            'failed_operations' => ImportExportHistory::where('status', 'failed')->count()
        ];

        return view('admin.import-export.index', compact('history', 'templates', 'stats'));
    }

    /**
     * Import de données
     */
    public function import(Request $request)
    {
        $request->validate([
            'type' => 'required|in:distributeurs,achats,produits',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240', // 10MB max
            'mode' => 'required|in:create,update,create_update',
            'skip_errors' => 'boolean'
        ]);

        $file = $request->file('file');
        $type = $request->type;
        $mode = $request->mode;
        $skipErrors = $request->boolean('skip_errors');

        // Créer l'entrée d'historique
        $history = ImportExportHistory::create([
            'type' => 'import',
            'entity_type' => $type,
            'filename' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'status' => 'processing',
            'user_id' => Auth::id(),
            'options' => [
                'mode' => $mode,
                'skip_errors' => $skipErrors
            ]
        ]);

        try {
            // Stocker le fichier temporairement
            $path = $file->store('imports');

            // Sélectionner la classe d'import appropriée
            $importClass = match($type) {
                'distributeurs' => new DistributeursImport($mode, $skipErrors, $history->id),
                'achats' => new AchatsImport($mode, $skipErrors, $history->id),
                'produits' => new ProductsImport($mode, $skipErrors, $history->id),
            };

            // Effectuer l'import
            Excel::import($importClass, $path);

            // Nettoyer le fichier temporaire
            Storage::delete($path);

            // Mettre à jour l'historique
            $history->update([
                'status' => 'completed',
                'completed_at' => now(),
                'result' => $importClass->getResults()
            ]);

            return redirect()->route('admin.import-export.index')
                ->with('success', "Import terminé avec succès. {$importClass->getSuccessCount()} lignes importées.");

        } catch (\Exception $e) {
            Log::error('Erreur import: ' . $e->getMessage());

            $history->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage()
            ]);

            return back()->with('error', 'Erreur lors de l\'import: ' . $e->getMessage());
        }
    }

    /**
     * Export de données
     */
    public function export(Request $request)
    {
        $request->validate([
            'type' => 'required|in:distributeurs,achats,produits,bonus,level_currents,avancements',
            'format' => 'required|in:csv,xlsx,pdf',
            'period_start' => 'nullable|date_format:Y-m',
            'period_end' => 'nullable|date_format:Y-m|after_or_equal:period_start',
            'filters' => 'nullable|array',
            'include_headers' => 'boolean',
            'include_archived' => 'boolean',
            'compress' => 'boolean'
        ]);

        $type = $request->type;
        $format = $request->format;
        $options = [
            'period_start' => $request->period_start,
            'period_end' => $request->period_end,
            'filters' => $request->filters ?? [],
            'include_headers' => $request->boolean('include_headers', true),
            'include_archived' => $request->boolean('include_archived', false)
        ];

        // Créer l'entrée d'historique
        $history = ImportExportHistory::create([
            'type' => 'export',
            'entity_type' => $type,
            'filename' => $this->generateExportFilename($type, $format),
            'status' => 'processing',
            'user_id' => Auth::id(),
            'options' => $options
        ]);

        try {
            // Sélectionner la classe d'export appropriée
            $exportClass = match($type) {
                'distributeurs' => new DistributeursExport($options),
                'achats' => new AchatsExport($options),
                'produits' => new ProductsExport($options),
                'bonus' => new BonusExport($options),
                'level_currents' => new LevelCurrentsExport($options),
                'avancements' => new AvancementsExport($options),
            };

            // Nom du fichier
            $filename = $history->filename;

            // Si compression demandée
            if ($request->boolean('compress')) {
                $tempPath = 'exports/temp/' . $filename;
                Excel::store($exportClass, $tempPath, 'local');

                $zipPath = 'exports/' . str_replace('.' . $format, '.zip', $filename);
                $this->createZipArchive(Storage::path($tempPath), Storage::path($zipPath));

                Storage::delete($tempPath);

                $history->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'file_path' => $zipPath,
                    'file_size' => Storage::size($zipPath)
                ]);

                return Storage::download($zipPath);
            }

            // Export direct
            $history->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            return Excel::download($exportClass, $filename);

        } catch (\Exception $e) {
            Log::error('Erreur export: ' . $e->getMessage());

            $history->update([
                'status' => 'failed',
                'completed_at' => now(),
                'error_message' => $e->getMessage()
            ]);

            return back()->with('error', 'Erreur lors de l\'export: ' . $e->getMessage());
        }
    }

    /**
     * Télécharge un template d'import
     */
    public function downloadTemplate($type)
    {
        $templates = [
            'distributeurs' => 'import_distributeurs_template.xlsx',
            'achats' => 'import_achats_template.xlsx',
            'produits' => 'import_produits_template.xlsx'
        ];

        if (!isset($templates[$type])) {
            abort(404);
        }

        $path = storage_path('app/templates/' . $templates[$type]);

        if (!file_exists($path)) {
            // Générer le template à la volée si il n'existe pas
            $this->generateTemplate($type, $path);
        }

        return response()->download($path);
    }

    /**
     * Télécharge un fichier d'export précédent
     */
    public function download($id)
    {
        $history = ImportExportHistory::findOrFail($id);

        // Vérifier les permissions
        if ($history->user_id !== Auth::id() && !Auth::user()->hasRole('super_admin')) {
            abort(403);
        }

        if (!$history->file_path || !Storage::exists($history->file_path)) {
            return back()->with('error', 'Fichier introuvable.');
        }

        return Storage::download($history->file_path, $history->filename);
    }

    /**
     * Affiche les détails d'une opération
     */
    public function show($id)
    {
        $history = ImportExportHistory::with('user')->findOrFail($id);

        return view('admin.import-export.show', compact('history'));
    }

    /**
     * Annule une opération en cours
     */
    public function cancel($id)
    {
        $history = ImportExportHistory::findOrFail($id);

        if ($history->status !== 'processing') {
            return back()->with('error', 'Cette opération ne peut pas être annulée.');
        }

        $history->update([
            'status' => 'cancelled',
            'completed_at' => now()
        ]);

        // TODO: Implémenter la logique d'annulation des jobs en cours

        return back()->with('success', 'Opération annulée.');
    }

    /**
     * Valide un fichier d'import
     */
    public function validate(Request $request)
    {
        $request->validate([
            'type' => 'required|in:distributeurs,achats,produits',
            'file' => 'required|file|mimes:csv,xlsx,xls|max:10240'
        ]);

        $file = $request->file('file');
        $type = $request->type;

        try {
            $path = $file->store('temp');

            // Validation spécifique selon le type
            $validator = match($type) {
                'distributeurs' => new DistributeursImport(null, false, null),
                'achats' => new AchatsImport(null, false, null),
                'produits' => new ProductsImport(null, false, null),
            };

            $errors = $validator->validate($path);

            Storage::delete($path);

            if (empty($errors)) {
                return response()->json([
                    'valid' => true,
                    'message' => 'Le fichier est valide et prêt pour l\'import.'
                ]);
            }

            return response()->json([
                'valid' => false,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'valid' => false,
                'errors' => ['Erreur lors de la validation: ' . $e->getMessage()]
            ], 422);
        }
    }

    /**
     * Génère un nom de fichier pour l'export
     */
    protected function generateExportFilename($type, $format)
    {
        return sprintf(
            '%s_%s_%s.%s',
            $type,
            Auth::user()->id,
            Carbon::now()->format('Y-m-d_His'),
            $format
        );
    }

    /**
     * Crée une archive ZIP
     */
    protected function createZipArchive($sourcePath, $destinationPath)
    {
        $zip = new \ZipArchive();

        if ($zip->open($destinationPath, \ZipArchive::CREATE) !== true) {
            throw new \Exception('Impossible de créer l\'archive ZIP');
        }

        $zip->addFile($sourcePath, basename($sourcePath));
        $zip->close();
    }

    /**
     * Génère un template d'import
     */
    protected function generateTemplate($type, $path)
    {
        // Créer le répertoire si nécessaire
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Générer le template selon le type
        switch ($type) {
            case 'distributeurs':
                Excel::store(new DistributeursTemplateExport(), basename($path), 'templates');
                break;
            case 'achats':
                Excel::store(new AchatsTemplateExport(), basename($path), 'templates');
                break;
            case 'produits':
                Excel::store(new ProductsTemplateExport(), basename($path), 'templates');
                break;
        }
    }
}
