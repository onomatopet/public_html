{{-- resources/views/admin/dashboard/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'Tableau de Bord')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Header avec sélecteur de période --}}
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <h1 class="text-2xl font-semibold text-gray-900">Tableau de Bord MLM</h1>

                <div class="flex items-center space-x-4">
                    <select id="period-selector"
                            onchange="window.location.href='{{ route('admin.dashboard.index') }}?period=' + this.value"
                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $p)->format('F Y') }}
                            </option>
                        @endforeach
                    </select>

                    <a href="{{ route('admin.dashboard.performance', ['period' => $period]) }}"
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Performance
                    </a>

                    <a href="{{ route('admin.dashboard.export', ['period' => $period]) }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Exporter PDF
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Alertes système (utilisation de $alerts du controller) --}}
        @if(count($alerts) > 0)
            <div class="mb-6 space-y-2">
                @foreach($alerts as $alert)
                    <div class="rounded-md p-4
                        @if($alert['type'] === 'error') bg-red-50 @elseif($alert['type'] === 'warning') bg-yellow-50 @else bg-blue-50 @endif">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                @if($alert['type'] === 'error')
                                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                    </svg>
                                @elseif($alert['type'] === 'warning')
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                @else
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                @endif
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium
                                    @if($alert['type'] === 'error') text-red-800 @elseif($alert['type'] === 'warning') text-yellow-800 @else text-blue-800 @endif">
                                    {{ $alert['title'] }}
                                </h3>
                                <div class="mt-1 text-sm
                                    @if($alert['type'] === 'error') text-red-700 @elseif($alert['type'] === 'warning') text-yellow-700 @else text-blue-700 @endif">
                                    {{ $alert['message'] }}
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Statistiques générales (utilisation de $stats du controller) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
            {{-- Total Distributeurs avec comparaison --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Distributeurs</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($stats['total_distributeurs']['value']) }}
                    </dd>
                    <dd class="mt-2 flex items-center text-sm">
                        @if($stats['total_distributeurs']['change'] > 0)
                            <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-green-600">+{{ abs($stats['total_distributeurs']['change']) }}%</span>
                        @elseif($stats['total_distributeurs']['change'] < 0)
                            <svg class="w-4 h-4 text-red-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-red-600">{{ $stats['total_distributeurs']['change'] }}%</span>
                        @else
                            <span class="text-gray-500">0%</span>
                        @endif
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                        @if($stats['total_distributeurs']['difference'] != 0)
                            <span class="text-gray-400 ml-2 text-xs" title="Mois précédent: {{ number_format($stats['total_distributeurs']['previous_value']) }}">
                                ({{ $stats['total_distributeurs']['difference'] > 0 ? '+' : '' }}{{ number_format($stats['total_distributeurs']['difference']) }})
                            </span>
                        @endif
                    </dd>
                </div>
            </div>

            {{-- Total Achats avec comparaison --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Total Achats</dt>
                    <dd class="mt-1 text-3xl font-semibold text-gray-900">
                        {{ number_format($stats['total_achats']['value']) }}
                    </dd>
                    <dd class="mt-2 flex items-center text-sm">
                        @if($stats['total_achats']['change'] > 0)
                            <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-green-600">+{{ abs($stats['total_achats']['change']) }}%</span>
                        @elseif($stats['total_achats']['change'] < 0)
                            <svg class="w-4 h-4 text-red-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-red-600">{{ $stats['total_achats']['change'] }}%</span>
                        @else
                            <span class="text-gray-500">0%</span>
                        @endif
                        <span class="text-gray-500 ml-1">vs mois dernier</span>
                        @if($stats['total_achats']['difference'] != 0)
                            <span class="text-gray-400 ml-2 text-xs" title="Mois précédent: {{ number_format($stats['total_achats']['previous_value']) }}">
                                ({{ $stats['total_achats']['difference'] > 0 ? '+' : '' }}{{ number_format($stats['total_achats']['difference']) }})
                            </span>
                        @endif
                    </dd>
                </div>
            </div>

            {{-- Modifications en attente --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Modifications en attente</dt>
                    <dd class="mt-1 text-3xl font-semibold text-orange-600">{{ $stats['pending_modifications'] }}</dd>
                    @if($stats['pending_modifications'] > 0)
                        <dd class="mt-2">
                            <a href="{{ route('admin.modifications.index') }}" class="text-sm text-orange-600 hover:text-orange-700">
                                Voir les demandes →
                            </a>
                        </dd>
                    @endif
                </div>
            </div>

            {{-- Suppressions en attente --}}
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <dt class="text-sm font-medium text-gray-500 truncate">Suppressions en attente</dt>
                    <dd class="mt-1 text-3xl font-semibold text-red-600">{{ $stats['pending_deletions'] }}</dd>
                    @if($stats['pending_deletions'] > 0)
                        <dd class="mt-2">
                            <a href="{{ route('admin.deletions.index') }}" class="text-sm text-red-600 hover:text-red-700">
                                Voir les demandes →
                            </a>
                        </dd>
                    @endif
                </div>
            </div>
        </div>

        {{-- KPIs principaux du DashboardService --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            @foreach($dashboardData['kpis'] as $key => $kpi)
                <div class="bg-white overflow-hidden shadow rounded-lg">
                    <div class="px-4 py-5 sm:p-6">
                        <dt class="text-sm font-medium text-gray-500 truncate">
                            @if($key === 'total_revenue') Chiffre d'affaires
                            @elseif($key === 'active_distributors') Distributeurs actifs
                            @elseif($key === 'average_basket') Panier moyen
                            @elseif($key === 'total_points') Points totaux
                            @endif
                        </dt>
                        <dd class="mt-1 text-3xl font-semibold text-gray-900">
                            {{ $kpi['formatted'] }}
                        </dd>
                        <dd class="mt-2 flex items-center text-sm">
                            @if($kpi['change'] > 0)
                                <svg class="w-4 h-4 text-green-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-green-600">{{ abs($kpi['change']) }}%</span>
                            @elseif($kpi['change'] < 0)
                                <svg class="w-4 h-4 text-red-500 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                </svg>
                                <span class="text-red-600">{{ abs($kpi['change']) }}%</span>
                            @else
                                <span class="text-gray-500">0%</span>
                            @endif
                            <span class="text-gray-500 ml-1">vs mois dernier</span>
                        </dd>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Graphiques --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            {{-- Evolution des ventes --}}
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Evolution des ventes</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            {{-- Distribution des grades --}}
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Distribution des grades</h2>
                <div style="position: relative; height: 300px;">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Evolution mensuelle (nouvelle section utilisant $monthlyRevenue) --}}
        @if(count($monthlyRevenue) > 0)
        <div class="bg-white p-6 rounded-lg shadow mb-8">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Evolution mensuelle {{ now()->year }}</h2>
            <div style="position: relative; height: 300px;">
                <canvas id="monthlyRevenueChart"></canvas>
            </div>
        </div>
        @endif

        {{-- Activité temps réel du DashboardService (3 colonnes) --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            {{-- Commandes récentes --}}
            <div class="bg-white rounded-lg shadow">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Commandes récentes
                    </h3>
                </div>
                <div class="px-4 py-3">
                    <div class="flow-root">
                        <ul class="-my-3 divide-y divide-gray-200">
                            @forelse($dashboardData['recent_activity']['recent_orders'] ?? [] as $order)
                                <li class="py-3">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $order['distributeur'] ?? 'N/A' }}
                                            </p>
                                            <p class="text-sm text-gray-500 truncate">
                                                {{ $order['product'] ?? 'N/A' }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ number_format($order['amount'] ?? 0) }} FCFA
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $order['points'] ?? 0 }} PV
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="py-3 text-sm text-gray-500 text-center">
                                    Aucune commande récente
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Avancements récents --}}
            <div class="bg-white rounded-lg shadow">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Avancements récents
                    </h3>
                </div>
                <div class="px-4 py-3">
                    <div class="flow-root">
                        <ul class="-my-3 divide-y divide-gray-200">
                            @forelse($dashboardData['recent_activity']['recent_advancements'] ?? [] as $advancement)
                                @php
                                    // Récupérer les informations du distributeur
                                    $distributeur = \App\Models\Distributeur::find($advancement['distributeur_id']);
                                @endphp
                                <li class="py-3">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                @if($distributeur)
                                                    {{ $distributeur->nom_distributeur }} {{ $distributeur->pnom_distributeur }}
                                                @else
                                                    Distributeur #{{ $advancement['distributeur_id'] }}
                                                @endif
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                @if($distributeur)
                                                    {{ $distributeur->distributeur_id }}
                                                @endif
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-1">
                                            <span class="text-sm font-medium text-gray-600">
                                                {{ $advancement['ancien_grade'] ?? 0 }}⭐
                                            </span>
                                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                            </svg>
                                            <span class="text-sm font-medium text-green-600">
                                                {{ $advancement['nouveau_grade'] ?? 0 }}⭐
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="py-3 text-sm text-gray-500 text-center">
                                    Aucun avancement récent
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>

            {{-- Nouvelles inscriptions --}}
            <div class="bg-white rounded-lg shadow">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Nouvelles inscriptions
                    </h3>
                </div>
                <div class="px-4 py-3">
                    <div class="flow-root">
                        <ul class="-my-3 divide-y divide-gray-200">
                            @forelse($dashboardData['recent_activity']['recent_registrations'] ?? [] as $registration)
                                <li class="py-3">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $registration['name'] ?? 'N/A' }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                Parrain : {{ $registration['parrain'] ?? 'Aucun' }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-xs text-gray-500">
                                                @if(isset($registration['created_at']))
                                                    {{ \Carbon\Carbon::parse($registration['created_at'])->format('d/m/Y') }}
                                                @else
                                                    N/A
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @empty
                                <li class="py-3 text-sm text-gray-500 text-center">
                                    Aucune inscription récente
                                </li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        {{-- Top Distributeurs (nouvelle section) --}}
        @if(count($topDistributeurs) > 0)
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Top 10 Distributeurs - {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y') }}
                </h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rang</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distributeur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matricule</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Grade</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Points Individuels</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Points Collectifs</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($topDistributeurs as $index => $distributeur)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $index + 1 }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">
                                    {{ $distributeur->nom_distributeur }} {{ $distributeur->pnom_distributeur }}
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $distributeur->distributeur_id }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    {{ $distributeur->grade_actuel }}⭐
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                {{ number_format($distributeur->cumul_individuel) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                {{ number_format($distributeur->cumul_collectif) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        {{-- Activité temps réel (utilisant $recentActivities du controller) --}}
        @if(count($recentActivities) > 0)
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="px-4 py-5 sm:px-6 border-b border-gray-200">
                <h3 class="text-lg leading-6 font-medium text-gray-900">
                    Activités récentes
                </h3>
            </div>
            <div class="px-4 py-3">
                <div class="flow-root">
                    <ul class="-mb-8">
                        @foreach($recentActivities->take(10) as $index => $activity)
                        <li>
                            <div class="relative pb-8">
                                @if(!$loop->last)
                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                @endif
                                <div class="relative flex space-x-3">
                                    <div>
                                        <span class="h-8 w-8 rounded-full flex items-center justify-center ring-8 ring-white
                                            @if($activity['color'] === 'green') bg-green-500
                                            @elseif($activity['color'] === 'blue') bg-blue-500
                                            @elseif($activity['color'] === 'yellow') bg-yellow-500
                                            @else bg-gray-500
                                            @endif">
                                            @if($activity['icon'] === 'user-plus')
                                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                                                </svg>
                                            @elseif($activity['icon'] === 'shopping-cart')
                                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                            @elseif($activity['icon'] === 'pencil')
                                                <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                </svg>
                                            @endif
                                        </span>
                                    </div>
                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                        <div>
                                            <p class="text-sm text-gray-900">{{ $activity['title'] }}</p>
                                            <p class="text-sm text-gray-500">{{ $activity['description'] }}</p>
                                        </div>
                                        <div class="text-right text-sm whitespace-nowrap text-gray-500">
                                            {{ $activity['created_at']->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // S'assurer que les canvas existent
    const salesCanvas = document.getElementById('salesChart');
    const gradeCanvas = document.getElementById('gradeChart');
    const monthlyRevenueCanvas = document.getElementById('monthlyRevenueChart');

    // Graphique évolution des ventes (DashboardService)
    if (salesCanvas) {
        const salesCtx = salesCanvas.getContext('2d');
        const salesData = @json($dashboardData['charts']['sales_evolution'] ?? []);

        if (salesData && salesData.length > 0) {
            new Chart(salesCtx, {
                type: 'line',
                data: {
                    labels: salesData.map(d => d.month || d.period),
                    datasets: [{
                        label: 'Chiffre d\'affaires',
                        data: salesData.map(d => parseFloat(d.revenue) || 0),
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        yAxisID: 'y',
                    }, {
                        label: 'Points',
                        data: salesData.map(d => parseFloat(d.points) || 0),
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        yAxisID: 'y1',
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' FCFA';
                                }
                            }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            grid: {
                                drawOnChartArea: false,
                            },
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' PV';
                                }
                            }
                        },
                    }
                }
            });
        }
    }

    // Graphique distribution des grades
    if (gradeCanvas) {
        const gradeCtx = gradeCanvas.getContext('2d');
        const gradeData = @json($dashboardData['charts']['grade_distribution'] ?? []);

        if (gradeData && gradeData.length > 0) {
            new Chart(gradeCtx, {
                type: 'doughnut',
                data: {
                    labels: gradeData.map(d => `Grade ${d.grade}⭐`),
                    datasets: [{
                        data: gradeData.map(d => parseInt(d.count) || 0),
                        backgroundColor: [
                            '#F87171', '#FB923C', '#FBBF24', '#FDE047',
                            '#A3E635', '#4ADE80', '#34D399', '#2DD4BF',
                            '#22D3EE', '#38BDF8'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((context.parsed / total) * 100).toFixed(1);
                                    return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        }
    }

    // Nouveau graphique pour l'évolution mensuelle (utilisant $monthlyRevenue)
    if (monthlyRevenueCanvas) {
        const monthlyCtx = monthlyRevenueCanvas.getContext('2d');
        const monthlyData = @json($monthlyRevenue);

        if (monthlyData && monthlyData.length > 0) {
            new Chart(monthlyCtx, {
                type: 'bar',
                data: {
                    labels: monthlyData.map(d => d.month),
                    datasets: [{
                        label: 'Points totaux',
                        data: monthlyData.map(d => d.total || 0),
                        backgroundColor: 'rgba(59, 130, 246, 0.8)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString('fr-FR') + ' PV';
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                afterLabel: function(context) {
                                    const dataIndex = context.dataIndex;
                                    const item = monthlyData[dataIndex];
                                    return [
                                        'Nombre d\'achats : ' + item.count_achats,
                                        'Distributeurs actifs : ' + item.count_distributeurs
                                    ];
                                }
                            }
                        }
                    }
                }
            });
        }
    }
});
</script>
@endpush

@endsection
