@extends('layouts.admin')

@section('title', 'Tableau de bord')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="px-4 sm:px-6 lg:px-8 py-8">
        {{-- En-tête --}}
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Tableau de bord administrateur</h1>
            <p class="mt-1 text-sm text-gray-600">
                Bienvenue {{ Auth::user()->name }}, voici un aperçu de votre système MLM.
            </p>
        </div>

        {{-- Widgets de statistiques --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            {{-- Widget Demandes de suppression --}}
            @if(auth()->user()->hasPermission('view_deletion_requests'))
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            @php
                                $pendingDeletions = \App\Models\DeletionRequest::pending()->count();
                                $totalDeletions = \App\Models\DeletionRequest::whereMonth('created_at', now()->month)->count();
                            @endphp
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Suppressions en attente
                                </dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        {{ $pendingDeletions }}
                                    </div>
                                    <div class="ml-2 flex items-baseline text-sm text-gray-600">
                                        / {{ $totalDeletions }} ce mois
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('admin.deletion-requests.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Voir toutes les demandes →
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- Widget Backups disponibles --}}
            @if(auth()->user()->hasPermission('view_backups'))
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            @php
                                $totalBackups = DB::table('deletion_backups')->whereNull('restored_at')->count();
                                $recentBackups = DB::table('deletion_backups')->where('created_at', '>=', now()->subDays(7))->count();
                            @endphp
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Backups disponibles
                                </dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        {{ $totalBackups }}
                                    </div>
                                    <div class="ml-2 flex items-baseline text-sm text-gray-600">
                                        {{ $recentBackups }} récents
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('admin.deletion-requests.backups') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Gérer les backups →
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- Widget Modifications en attente --}}
            @if(auth()->user()->hasPermission('view_modification_requests'))
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            @php
                                $pendingMods = \App\Models\ModificationRequest::pending()->count();
                                $highRiskMods = \App\Models\ModificationRequest::pending()->highRisk()->count();
                            @endphp
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Modifications en attente
                                </dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        {{ $pendingMods }}
                                    </div>
                                    @if($highRiskMods > 0)
                                    <div class="ml-2 flex items-baseline text-sm text-red-600">
                                        {{ $highRiskMods }} risque élevé
                                    </div>
                                    @endif
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('admin.modification-requests.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Voir les demandes →
                        </a>
                    </div>
                </div>
            </div>
            @endif

            {{-- Widget Activité récente --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            @php
                                $todayActions = \App\Models\DeletionRequest::whereDate('created_at', today())->count() +
                                               \App\Models\ModificationRequest::whereDate('created_at', today())->count();
                            @endphp
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">
                                    Actions aujourd'hui
                                </dt>
                                <dd class="flex items-baseline">
                                    <div class="text-2xl font-semibold text-gray-900">
                                        {{ $todayActions }}
                                    </div>
                                </dd>
                            </dl>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('admin.distributeurs.index') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            Gérer les distributeurs →
                        </a>
                    </div>
                </div>
            </div>
        </div>

        {{-- Statistiques principales --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            {{-- Statistiques distributeurs --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Distributeurs</h3>
                @php
                    $totalDistributeurs = \App\Models\Distributeur::count();
                    $distributeursActifs = \App\Models\Distributeur::where('statut_validation_periode', true)->count();
                    $nouveauxCeMois = \App\Models\Distributeur::whereMonth('created_at', now()->month)->count();
                @endphp
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Total</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($totalDistributeurs) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Actifs</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($distributeursActifs) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Nouveaux ce mois</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($nouveauxCeMois) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Statistiques achats --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Achats</h3>
                @php
                    $currentPeriod = date('Y-m');
                    $achatsTotal = \App\Models\Achat::where('period', $currentPeriod)->sum('montant_total_ligne');
                    $achatsCount = \App\Models\Achat::where('period', $currentPeriod)->count();
                    $achatsOnline = \App\Models\Achat::where('period', $currentPeriod)->where('online', true)->count();
                @endphp
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Montant total ({{ $currentPeriod }})</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($achatsTotal, 0, ',', ' ') }} FCFA</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Nombre d'achats</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($achatsCount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Achats en ligne</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($achatsOnline) }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Statistiques bonus --}}
            <div class="bg-white shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Bonus</h3>
                @php
                    $currentPeriod = date('Y-m');
                    $bonusTotal = \App\Models\Bonus::where('period', $currentPeriod)->sum('montant');
                    $bonusCount = \App\Models\Bonus::where('period', $currentPeriod)->count();
                    $avgBonus = $bonusCount > 0 ? $bonusTotal / $bonusCount : 0;
                @endphp
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Total distribué</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($bonusTotal, 0, ',', ' ') }} FCFA</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Distributeurs payés</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($bonusCount) }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-600">Bonus moyen</dt>
                        <dd class="text-sm font-medium text-gray-900">{{ number_format($avgBonus, 0, ',', ' ') }} FCFA</dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Actions rapides --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Actions rapides</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <a href="{{ route('admin.distributeurs.create') }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Nouveau distributeur
                    </a>
                    <a href="{{ route('admin.achats.create') }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Nouvel achat
                    </a>
                    <a href="{{ route('admin.processes.index') }}" class="inline-flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-purple-600 hover:bg-purple-700">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Processus
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
