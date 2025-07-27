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
        {{-- Alertes --}}
        @if(count($dashboardData['alerts']) > 0)
            <div class="mb-6 space-y-2">
                @foreach($dashboardData['alerts'] as $alert)
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

        {{-- KPIs principaux --}}
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
                {{-- CORRECTION: Ajouter un conteneur avec hauteur fixe --}}
                <div style="position: relative; height: 300px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>

            {{-- Distribution des grades --}}
            <div class="bg-white p-6 rounded-lg shadow">
                <h2 class="text-lg font-medium text-gray-900 mb-4">Distribution des grades</h2>
                {{-- CORRECTION: Ajouter un conteneur avec hauteur fixe --}}
                <div style="position: relative; height: 300px;">
                    <canvas id="gradeChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Activité temps réel --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
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
                            @foreach($dashboardData['recent_activity']['recent_orders'] as $order)
                                <li class="py-3">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $order['distributeur'] }}
                                            </p>
                                            <p class="text-sm text-gray-500 truncate">
                                                {{ $order['product'] }}
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-sm font-medium text-gray-900">
                                                {{ number_format($order['amount'], 2) }} €
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $order['points'] }} PV
                                            </p>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
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
                            @foreach($dashboardData['recent_activity']['recent_advancements'] as $advancement)
                                <li class="py-3">
                                    <div class="flex items-center space-x-4">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                {{ $advancement['distributeur'] }}
                                            </p>
                                            <p class="text-xs text-gray-500">
                                                {{ $advancement['matricule'] }}
                                            </p>
                                        </div>
                                        <div class="flex items-center space-x-1">
                                            <span class="text-sm font-medium text-gray-600">
                                                {{ $advancement['from_grade'] }}⭐
                                            </span>
                                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                            </svg>
                                            <span class="text-sm font-medium text-green-600">
                                                {{ $advancement['to_grade'] }}⭐
                                            </span>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
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
                            @foreach($dashboardData['recent_activity']['recent_registrations'] as $registration)
                            <li class="py-3">
                                <div class="flex items-center space-x-4">
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            {{ $registration['name'] }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{-- CORRECTION ICI : Changer 'sponsor' par 'parrain' --}}
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
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // S'assurer que les canvas existent
    const salesCanvas = document.getElementById('salesChart');
    const gradeCanvas = document.getElementById('gradeChart');

    if (!salesCanvas || !gradeCanvas) {
        console.error('Canvas elements not found');
        return;
    }

    // Graphique évolution des ventes
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
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    },
                }
            }
        });
    }

    // Graphique distribution des grades
    const gradeCtx = gradeCanvas.getContext('2d');
    const gradeData = @json($dashboardData['charts']['grade_distribution'] ?? []);

    if (gradeData && gradeData.length > 0) {
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: gradeData.map(d => d.label || `Grade ${d.grade}`),
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
                    }
                }
            }
        });
    }
});
</script>
@endpush

@endsection
