{{-- resources/views/admin/dashboard/performance.blade.php --}}
@extends('layouts.admin')

@section('title', 'Performance - Tableau de Bord')

@section('content')
<div class="min-h-screen bg-gray-50">
    {{-- Header avec sélecteur de période --}}
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <h1 class="text-2xl font-semibold text-gray-900">Analyse de Performance</h1>

                <div class="flex items-center space-x-4">
                    <select id="period-selector"
                            onchange="window.location.href='{{ route('admin.dashboard.performance') }}?period=' + this.value"
                            class="rounded-md border-gray-300 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                        @foreach($availablePeriods as $p)
                            <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>
                                {{ \Carbon\Carbon::createFromFormat('Y-m', $p)->format('F Y') }}
                            </option>
                        @endforeach
                    </select>

                    <a href="{{ route('admin.dashboard.index') }}"
                       class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50">
                        Retour au Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- Distribution des grades --}}
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Distribution des Grades</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- Graphique --}}
                    <div>
                        <canvas id="gradeDistributionChart" height="300"></canvas>
                    </div>

                    {{-- Tableau --}}
                    <div>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grade</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Nombre</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">%</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @php
                                    $total = collect($performanceData['grade_distribution'])->sum('count');
                                @endphp
                                @foreach($performanceData['grade_distribution'] as $grade)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {{ $grade['label'] ?? "Grade {$grade['grade']}" }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900">
                                            {{ number_format($grade['count']) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500">
                                            {{ $total > 0 ? number_format(($grade['count'] / $total) * 100, 1) : 0 }}%
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Croissance du réseau --}}
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Croissance du Réseau</h2>
            </div>
            <div class="p-6">
                <canvas id="networkGrowthChart" height="300"></canvas>
            </div>
        </div>

        {{-- Statistiques des bonus --}}
        <div class="bg-white shadow rounded-lg mb-8">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Statistiques des Bonus</h2>
            </div>
            <div class="p-6">
                @if(isset($performanceData['bonus_statistics']))
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <p class="text-sm text-blue-600">Total distribué</p>
                            <p class="text-2xl font-bold text-blue-900">
                                {{ number_format($performanceData['bonus_statistics']['total'] ?? 0, 2) }} €
                            </p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4">
                            <p class="text-sm text-green-600">Bénéficiaires</p>
                            <p class="text-2xl font-bold text-green-900">
                                {{ number_format($performanceData['bonus_statistics']['beneficiaires'] ?? 0) }}
                            </p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4">
                            <p class="text-sm text-purple-600">Bonus moyen</p>
                            <p class="text-2xl font-bold text-purple-900">
                                {{ number_format($performanceData['bonus_statistics']['moyenne'] ?? 0, 2) }} €
                            </p>
                        </div>
                    </div>

                    @if(isset($performanceData['bonus_statistics']['par_type']) && count($performanceData['bonus_statistics']['par_type']) > 0)
                        <div>
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Répartition par type</h3>
                            <div class="space-y-2">
                                @foreach($performanceData['bonus_statistics']['par_type'] as $type)
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600">{{ $type['type'] ?? 'Type inconnu' }}</span>
                                        <span class="text-sm font-medium text-gray-900">
                                            {{ number_format($type['total'] ?? 0, 2) }} €
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @else
                    <p class="text-gray-500 text-center py-4">Aucune donnée de bonus disponible pour cette période</p>
                @endif
            </div>
        </div>

        {{-- Top performers --}}
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Top Performers</h2>
            </div>
            <div class="p-6">
                @if(isset($performanceData['top_performers']) && count($performanceData['top_performers']) > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rang</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Distributeur</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase">Grade</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Points Individuels</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Points Collectifs</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($performanceData['top_performers'] as $index => $performer)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            #{{ $index + 1 }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $performer->nom_distributeur }} {{ $performer->pnom_distributeur }}
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                #{{ $performer->distributeur_id }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                {{ $performer->etoiles }} ⭐
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ number_format($performer->cumul_individuel) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900">
                                            {{ number_format($performer->cumul_collectif) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500 text-center py-4">Aucune donnée disponible pour cette période</p>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique distribution des grades
    const gradeData = @json($performanceData['grade_distribution'] ?? []);

    if (gradeData.length > 0) {
        const gradeCtx = document.getElementById('gradeDistributionChart').getContext('2d');
        new Chart(gradeCtx, {
            type: 'doughnut',
            data: {
                labels: gradeData.map(d => d.label || `Grade ${d.grade}`),
                datasets: [{
                    data: gradeData.map(d => d.count),
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

    // Graphique croissance du réseau
    const growthData = @json($performanceData['network_growth'] ?? []);

    if (growthData.length > 0) {
        const growthCtx = document.getElementById('networkGrowthChart').getContext('2d');
        new Chart(growthCtx, {
            type: 'line',
            data: {
                labels: growthData.map(d => d.month),
                datasets: [{
                    label: 'Nouveaux distributeurs',
                    data: growthData.map(d => d.new_distributors),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>
@endpush
@endsection
