<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;

// Controllers Admin
use App\Http\Controllers\Admin\PeriodController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\BonusController;
use App\Http\Controllers\Admin\ProcessController;
use App\Http\Controllers\Admin\DistributeurController;
use App\Http\Controllers\Admin\AchatController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\AdminSnapshotController;
use App\Http\Controllers\Admin\DeletionRequestController;
use App\Http\Controllers\Admin\ModificationRequestController;
use App\Http\Controllers\Admin\AchatReturnController;
use App\Http\Controllers\Admin\NetworkExportController;
use App\Http\Controllers\Admin\WorkflowController;
use App\Http\Controllers\Admin\AchatSessionController;
use App\Http\Controllers\Admin\RolePermissionController;
use App\Http\Controllers\Admin\PeriodResetController;
use App\Http\Controllers\Admin\MLMCleaningController;

// Commentez ou supprimez ces lignes si les contrôleurs n'existent pas encore
// use App\Http\Controllers\Admin\ReportController;
// use App\Http\Controllers\Admin\SettingsController;
// use App\Http\Controllers\Admin\ImportExportController;
// use App\Http\Controllers\Admin\ActivityLogController;

// Controllers Distributor (à créer)
use App\Http\Controllers\Distributor\DistributorDashboardController;
use App\Http\Controllers\Distributor\DistributorProfileController;
use App\Http\Controllers\Distributor\DistributorNetworkController;
use App\Http\Controllers\Distributor\DistributorBonusController;
use App\Http\Controllers\Distributor\DistributorPurchaseController;

use App\Models\DeletionRequest;
use App\Models\Distributeur;
use App\Services\DeletionValidationService;
use Illuminate\Http\Request;

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| ROUTES PUBLIQUES
|--------------------------------------------------------------------------
*/

// Landing page
Route::get('/', function () {
    return view('landing');
})->name('home');

/*
|--------------------------------------------------------------------------
| ROUTES AUTHENTIFIÉES BASIQUES
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    // Dashboard principal avec redirection selon le rôle
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard', function () {
            $user = Auth::user();

            if (!$user instanceof \App\Models\User) {
                return redirect()->route('login');
            }

            // Maintenant PHP et Intelephense savent que $user est de type User
            if ($user->hasPermission('access_admin')) {
                return redirect()->route('admin.dashboard');
            }

            if ($user->distributeur) {
                return redirect()->route('distributor.dashboard');
            }

            return view('dashboard');
        })->middleware('verified')->name('dashboard');
    });

    // Profil utilisateur
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

/*
|--------------------------------------------------------------------------
| ROUTES ESPACE DISTRIBUTEUR
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])
    ->prefix('distributor')
    ->name('distributor.')
    ->group(function () {
        Route::get('/dashboard', [DistributorDashboardController::class, 'index'])->name('dashboard');
        Route::get('/profile', [DistributorProfileController::class, 'show'])->name('profile.show');
        Route::get('/network', [DistributorNetworkController::class, 'index'])->name('network.index');
        Route::get('/network/tree', [DistributorNetworkController::class, 'tree'])->name('network.tree');
        Route::get('/bonuses', [DistributorBonusController::class, 'index'])->name('bonuses.index');
        Route::get('/bonuses/{bonus}', [DistributorBonusController::class, 'show'])->name('bonuses.show');
        Route::get('/purchases', [DistributorPurchaseController::class, 'index'])->name('purchases.index');
        Route::get('/purchases/{purchase}', [DistributorPurchaseController::class, 'show'])->name('purchases.show');
    });

/*
|--------------------------------------------------------------------------
| ROUTES ADMIN AVEC PERMISSIONS
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified', 'check_admin_role'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Dashboard principal - simplifié
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // ===== DASHBOARD ET MONITORING =====
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('/', [DashboardController::class, 'index'])->name('index');
            Route::get('/performance', [DashboardController::class, 'performance'])->name('performance');
            Route::get('/realtime', [DashboardController::class, 'realtime'])->name('realtime');
            Route::get('/export', [DashboardController::class, 'export'])->name('export');
        });

        // ===== WORKFLOW DE GESTION DES PÉRIODES =====
        Route::prefix('workflow')->name('workflow.')->group(function () {
            Route::get('/', [WorkflowController::class, 'index'])->name('index');
            Route::get('/history/{period}', [WorkflowController::class, 'history'])->name('history');
            Route::get('/{period}', [WorkflowController::class, 'show'])->name('show');
            Route::post('/validate-purchases', [WorkflowController::class, 'validatePurchases'])->name('validate-purchases');
            Route::post('/aggregate-purchases', [WorkflowController::class, 'aggregatePurchases'])->name('aggregate-purchases');
            Route::post('/calculate-advancements', [WorkflowController::class, 'calculateAdvancements'])->name('calculate-advancements');
            Route::post('/create-snapshot', [WorkflowController::class, 'createSnapshot'])->name('create-snapshot');
            Route::post('/close-period', [WorkflowController::class, 'closePeriod'])->name('close-period');
            Route::get('/{period}/report', [WorkflowController::class, 'report'])->name('report');
        });

        // ===== GESTION DES DISTRIBUTEURS =====
        Route::prefix('distributeurs')->name('distributeurs.')->group(function () {
            Route::get('/', [DistributeurController::class, 'index'])->name('index');
            Route::get('/create', [DistributeurController::class, 'create'])->name('create');
            Route::post('/', [DistributeurController::class, 'store'])->name('store');
            Route::get('/search', [DistributeurController::class, 'search'])->name('search');
            Route::get('/{distributeur}', [DistributeurController::class, 'show'])->name('show');
            Route::get('/{distributeur}/edit', [DistributeurController::class, 'edit'])->name('edit');
            Route::put('/{distributeur}', [DistributeurController::class, 'update'])->name('update');
            Route::delete('/{distributeur}', [DistributeurController::class, 'destroy'])->name('destroy');
            Route::post('/{distributeur}/request-deletion', [DistributeurController::class, 'requestDeletion'])->name('request-deletion');
            Route::get('/{distributeur}/confirm-deletion', [DistributeurController::class, 'confirmDeletion'])->name('confirm-deletion');
            Route::get('/{distributeur}/network', [DistributeurController::class, 'network'])->name('network');
            Route::get('/{distributeur}/stats', [DistributeurController::class, 'stats'])->name('stats');
        });

        // ===== EXPORT RÉSEAU =====
        Route::prefix('network')->name('network.')->group(function () {
            Route::get('/', [NetworkExportController::class, 'index'])->name('index');
            Route::post('/export', [NetworkExportController::class, 'export'])->name('export');
            Route::get('/download/{filename}', [NetworkExportController::class, 'download'])->name('download');
            Route::get('/search/distributeurs', [NetworkExportController::class, 'searchDistributeurs'])->name('search.distributeurs');
            Route::get('/search/periods', [NetworkExportController::class, 'searchPeriods'])->name('search.periods');
            Route::post('/export/html', [NetworkExportController::class, 'exportHtml'])->name('export.html');
            Route::post('/export/pdf', [NetworkExportController::class, 'exportPdf'])->name('export.pdf');
            Route::post('/export/excel', [NetworkExportController::class, 'exportExcel'])->name('export.excel');
        });

        // ===== GESTION DES ACHATS =====
        Route::prefix('achats')->name('achats.')->group(function () {
            Route::get('/', [AchatController::class, 'index'])->name('index');
            Route::get('/create', [AchatController::class, 'create'])->name('create');
            Route::post('/', [AchatController::class, 'store'])->name('store');
            Route::get('/{achat}', [AchatController::class, 'show'])->name('show');
            Route::get('/{achat}/edit', [AchatController::class, 'edit'])->name('edit');
            Route::put('/{achat}', [AchatController::class, 'update'])->name('update');
            Route::delete('/{achat}', [AchatController::class, 'destroy'])->name('destroy');

            // Retours d'achats
            Route::post('/{achat}/return', [AchatReturnController::class, 'create'])->name('return.create');
            Route::post('/returns/{return}/approve', [AchatReturnController::class, 'approve'])->name('return.approve');
            Route::post('/returns/{return}/reject', [AchatReturnController::class, 'reject'])->name('return.reject');

            // Sessions d'achats
            Route::prefix('session')->name('session.')->group(function () {
                Route::get('/start', [AchatSessionController::class, 'start'])->name('start');
                Route::post('/init', [AchatSessionController::class, 'init'])->name('init');
                Route::get('/summary', [AchatSessionController::class, 'summary'])->name('summary');
                Route::post('/add-item', [AchatSessionController::class, 'addItem'])->name('add-item');
                Route::delete('/remove-item', [AchatSessionController::class, 'removeItem'])->name('remove-item');
                Route::post('/validate', [AchatSessionController::class, 'validate'])->name('validate');
                Route::post('/cancel', [AchatSessionController::class, 'cancel'])->name('cancel');
            });
        });

        // ===== GESTION DES PRODUITS =====
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('/create', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
            Route::get('/{product}', [ProductController::class, 'show'])->name('show');
            Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('/{product}', [ProductController::class, 'update'])->name('update');
            Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy');
        });

        // ===== GESTION DES BONUS =====
        Route::prefix('bonuses')->name('bonuses.')->group(function () {
            Route::get('/', [BonusController::class, 'index'])->name('index');
            Route::get('/create', [BonusController::class, 'create'])->name('create');
            Route::post('/', [BonusController::class, 'store'])->name('store');
            Route::get('/{bonus}', [BonusController::class, 'show'])->name('show');
            Route::get('/{bonus}/edit', [BonusController::class, 'edit'])->name('edit');
            Route::put('/{bonus}', [BonusController::class, 'update'])->name('update');
            Route::delete('/{bonus}', [BonusController::class, 'destroy'])->name('destroy');
            Route::get('/{bonus}/pdf', [BonusController::class, 'generatePdf'])->name('pdf');
            Route::get('/calculate/{period}', [BonusController::class, 'showCalculation'])->name('calculate.show');
            Route::post('/calculate/{period}', [BonusController::class, 'calculate'])->name('calculate');
        });

        // ===== GESTION DES PÉRIODES (VERSION CONSOLIDÉE) =====
        Route::prefix('periods')->name('periods.')->group(function () {
            Route::get('/', [PeriodController::class, 'index'])->name('index');
            Route::post('/start-validation', [PeriodController::class, 'startValidation'])->name('start-validation');
            Route::post('/close', [PeriodController::class, 'closePeriod'])->name('close');
            Route::post('/update-thresholds', [PeriodController::class, 'updateThresholds'])->name('update-thresholds');
            Route::post('/run-aggregation', [PeriodController::class, 'runAggregation'])->name('run-aggregation');

            // Routes pour la réinitialisation de période
            Route::prefix('reset')->name('reset.')->group(function () {
                Route::get('/confirm', [PeriodResetController::class, 'confirmReset'])->name('confirm');
                Route::post('/', [PeriodResetController::class, 'reset'])->name('reset');
                Route::get('/backups', [PeriodResetController::class, 'backups'])->name('backups');
                Route::post('/restore', [PeriodResetController::class, 'restore'])->name('restore');
            });
        });

        // ===== PROCESSUS MÉTIER =====
        Route::prefix('processes')->name('processes.')->group(function () {
            Route::get('/', [ProcessController::class, 'index'])->name('index');
            Route::post('/advancements', [ProcessController::class, 'processAdvancements'])->name('advancements');
            Route::post('/regularization', [ProcessController::class, 'regularizeGrades'])->name('regularization');
            Route::get('/history', [ProcessController::class, 'history'])->name('history');
        });

        // ===== SYSTÈME DE SNAPSHOTS =====
        Route::prefix('snapshots')->name('snapshots.')->group(function () {
            Route::get('/create', [AdminSnapshotController::class, 'create'])->name('create');
            Route::post('/', [AdminSnapshotController::class, 'store'])->name('store');
            Route::get('/', [AdminSnapshotController::class, 'index'])->name('index');
            Route::get('/{snapshot}', [AdminSnapshotController::class, 'show'])->name('show');
            Route::post('/{snapshot}/restore', [AdminSnapshotController::class, 'restore'])->name('restore');
            Route::delete('/{snapshot}', [AdminSnapshotController::class, 'destroy'])->name('destroy');
        });

        // ===== GESTION DES DEMANDES DE SUPPRESSION =====
        Route::prefix('deletion-requests')->name('deletion-requests.')->group(function () {
            Route::get('/', [DeletionRequestController::class, 'index'])->name('index');
            Route::get('/backups', [DeletionRequestController::class, 'backups'])->name('backups');
            Route::get('/export', [DeletionRequestController::class, 'export'])->name('export');
            Route::post('/restore-backup', [DeletionRequestController::class, 'restoreBackup'])->name('restore-backup');
            Route::get('/{deletionRequest}', [DeletionRequestController::class, 'show'])->name('show');
            Route::post('/{deletionRequest}/approve', [DeletionRequestController::class, 'approve'])->name('approve');
            Route::post('/{deletionRequest}/reject', [DeletionRequestController::class, 'reject'])->name('reject');
            Route::post('/{deletionRequest}/execute', [DeletionRequestController::class, 'execute'])->name('execute');
            Route::post('/{deletionRequest}/cancel', [DeletionRequestController::class, 'cancel'])->name('cancel');
        });

        // ===== GESTION DES DEMANDES DE MODIFICATION =====
        Route::prefix('modification-requests')->name('modification-requests.')->group(function () {
            Route::get('/', [ModificationRequestController::class, 'index'])->name('index');
            Route::get('/{modificationRequest}', [ModificationRequestController::class, 'show'])->name('show');
            Route::get('/create/parent-change/{distributeur}', [ModificationRequestController::class, 'createParentChange'])->name('create.parent-change');
            Route::post('/store/parent-change/{distributeur}', [ModificationRequestController::class, 'storeParentChange'])->name('store.parent-change');
            Route::get('/create/grade-change/{distributeur}', [ModificationRequestController::class, 'createGradeChange'])->name('create.grade-change');
            Route::post('/store/grade-change/{distributeur}', [ModificationRequestController::class, 'storeGradeChange'])->name('store.grade-change');
            Route::post('/{modificationRequest}/approve', [ModificationRequestController::class, 'approve'])->name('approve');
            Route::post('/{modificationRequest}/reject', [ModificationRequestController::class, 'reject'])->name('reject');
            Route::post('/{modificationRequest}/execute', [ModificationRequestController::class, 'execute'])->name('execute');
            Route::delete('/{modificationRequest}/cancel', [ModificationRequestController::class, 'cancel'])->name('cancel');
            Route::post('/validate', [ModificationRequestController::class, 'validateChange'])->name('validate');
        });

        // ===== GESTION DES BACKUPS =====
        Route::prefix('backups')->name('backups.')->group(function () {
            Route::get('/', [DeletionRequestController::class, 'backups'])->name('index');
            Route::post('/restore', [DeletionRequestController::class, 'restoreBackup'])->name('restore');
            Route::get('/download/{backup}', [DeletionRequestController::class, 'downloadBackup'])->name('download');
            Route::delete('/{backup}', [DeletionRequestController::class, 'deleteBackup'])->name('delete');
        });

        // ===== GESTION DES RÔLES ET PERMISSIONS =====
        Route::prefix('roles')->name('roles.')->group(function () {
            Route::get('/', [RolePermissionController::class, 'index'])->name('index');
            Route::get('/create', [RolePermissionController::class, 'create'])->name('create');
            Route::post('/', [RolePermissionController::class, 'store'])->name('store');
            Route::get('/{role}', [RolePermissionController::class, 'show'])->name('show');
            Route::get('/{role}/edit', [RolePermissionController::class, 'edit'])->name('edit');
            Route::put('/{role}', [RolePermissionController::class, 'update'])->name('update');
            Route::delete('/{role}', [RolePermissionController::class, 'destroy'])->name('destroy');
            Route::get('/users/manage', [RolePermissionController::class, 'users'])->name('users');
            Route::post('/users/{user}/roles', [RolePermissionController::class, 'updateUserRoles'])->name('users.update-roles');
            Route::post('/users/{user}/toggle-status', [RolePermissionController::class, 'toggleUserStatus'])->name('users.toggle-status');
        });

        // ===== ROUTES API POUR AJAX =====
        Route::prefix('api')->name('api.')->group(function () {
            Route::get('/distributeurs/search', [DistributeurController::class, 'apiSearch'])->name('distributeurs.search');
            Route::get('/products/{product}/info', [ProductController::class, 'apiGetInfo'])->name('products.info');

            Route::post('/distributeurs/{distributeur}/validate-deletion', function (Distributeur $distributeur) {
                $validationService = app(DeletionValidationService::class);
                return response()->json($validationService->validateDistributeurDeletion($distributeur));
            })->name('distributeurs.validate-deletion');

            Route::post('/distributeurs/{distributeur}/deletion-impact', function (Distributeur $distributeur) {
                $validationService = app(DeletionValidationService::class);
                $validation = $validationService->validateDistributeurDeletion($distributeur);
                return response()->json([
                    'impact' => $validation['related_data']['hierarchy_impact'] ?? [],
                    'impact_level' => $validation['impact_level'] ?? 'low',
                    'warnings' => $validation['warnings'] ?? [],
                    'blockers' => $validation['blockers'] ?? []
                ]);
            })->name('distributeurs.deletion-impact');

            Route::get('/dashboard/stats', [DashboardController::class, 'apiStats'])->name('dashboard.stats');
            Route::get('/dashboard/notifications', [DashboardController::class, 'apiNotifications'])->name('dashboard.notifications');
        });

        // ===== SYSTÈME DE NETTOYAGE MLM =====
        Route::prefix('mlm-cleaning')->name('mlm-cleaning.')->group(function () {
            // Dashboard principal
            Route::get('/', [MLMCleaningController::class, 'index'])->name('index');

            // Analyse
            Route::post('/analyze', [MLMCleaningController::class, 'analyze'])->name('analyze');

            // Preview et traitement
            Route::get('/preview/{session}', [MLMCleaningController::class, 'preview'])->name('preview');
            Route::post('/process/{session}', [MLMCleaningController::class, 'process'])->name('process');

            // Rapport et téléchargement
            Route::get('/report/{session}', [MLMCleaningController::class, 'report'])->name('report');
            Route::get('/report/{session}/download/{format}', [MLMCleaningController::class, 'downloadReport'])->name('report.download');

            // Rollback
            Route::post('/rollback/{session}', [MLMCleaningController::class, 'rollback'])->name('rollback');

            // API endpoints
            Route::get('/progress/{session}', [MLMCleaningController::class, 'progress'])->name('progress');
            Route::get('/anomaly/{anomaly}', [MLMCleaningController::class, 'anomalyDetails'])->name('anomaly.details');
            Route::post('/anomaly/{anomaly}/fix', [MLMCleaningController::class, 'fixAnomaly'])->name('anomaly.fix');
        });

        // ===== ROUTES FUTURES (À IMPLÉMENTER) =====
        /*
        // RAPPORTS
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/', [ReportController::class, 'index'])->name('index');
            Route::get('/sales', [ReportController::class, 'sales'])->name('sales');
            Route::get('/commissions', [ReportController::class, 'commissions'])->name('commissions');
            Route::get('/network-growth', [ReportController::class, 'networkGrowth'])->name('network-growth');
            Route::post('/export', [ReportController::class, 'export'])->name('export');
        });

        // PARAMÈTRES
        Route::prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingsController::class, 'index'])->name('index');
            Route::post('/update', [SettingsController::class, 'update'])->name('update');
            Route::post('/cache/clear', [SettingsController::class, 'clearCache'])->name('cache.clear');
        });

        // IMPORT/EXPORT
        Route::prefix('import-export')->name('import-export.')->group(function () {
            Route::get('/', [ImportExportController::class, 'index'])->name('index');
            Route::post('/import', [ImportExportController::class, 'import'])->name('import');
            Route::post('/export', [ImportExportController::class, 'export'])->name('export');
            Route::get('/download/{file}', [ImportExportController::class, 'download'])->name('download');
        });

        // LOGS D'ACTIVITÉ
        Route::prefix('logs')->name('logs.')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index'])->name('index');
            Route::get('/{log}', [ActivityLogController::class, 'show'])->name('show');
        });
        */

        // ===== ROUTES MANQUANTES DANS DELETIONS =====
        Route::prefix('deletions')->name('deletions.')->group(function () {
            Route::get('/', [DeletionRequestController::class, 'index'])->name('index');
            Route::get('/create', [DeletionRequestController::class, 'create'])->name('create');
            Route::post('/', [DeletionRequestController::class, 'store'])->name('store');
        });
    });

/*
|--------------------------------------------------------------------------
| FALLBACK ROUTES
|--------------------------------------------------------------------------
*/

// Redirection pour les URL non trouvées
Route::fallback(function () {
    return redirect()->route('home');
});
