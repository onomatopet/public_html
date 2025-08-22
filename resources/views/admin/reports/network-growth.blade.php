@extends('layouts.admin')

@section('title', 'Rapport de croissance du réseau')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Croissance du réseau</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Analyse de l'évolution et des performances du réseau
                    </p>
                </div>
                <div class="flex space-x-3">
                    <a href="{{ route('admin.reports.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Retour
                    </a>
                    <button type="button" onclick="exportReport()" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        Exporter
                    </button>
                </div>
            </div>
        </div>

        {{-- Filtres --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <form method="GET" action="{{ route('admin.reports.network-growth') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période début</label>
                    <input type="month" name="period_start" value="{{ $periodStart }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période fin</label>
                    <input type="month" name="period_end" value="{{ $periodEnd }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-md hover:bg-gray-700">
                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filtrer
                    </button>
                </div>
            </form>
        </div>

        {{-- KPIs principaux --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            @php
                $totalDistributeurs = \App\Models\Distributeur::count();
                $newThisMonth = \App\Models\Distributeur::whereMonth('created_at', now()->month)->count();
                $activeRate = $activityRate->last()->rate ?? 0;
                $avgGrade = \App\Models\Distributeur::avg('etoiles_id') ?? 0;
            @endphp

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-blue-100 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Total distributeurs</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($totalDistributeurs, 0, ',', ' ') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-green-100 rounded-lg">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Nouveaux ce mois</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">+{{ $newThisMonth }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-purple-100 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Taux d'activité</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($activeRate, 1, ',', ' ') }}%</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Grade moyen</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($avgGrade, 1, ',', ' ') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Graphiques principaux --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            {{-- Évolution du nombre de distributeurs --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Évolution du nombre de distributeurs</h3>
                <div id="evolutionChart" style="height: 350px;"></div>
            </div>

            {{-- Répartition par grade --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Répartition par grade</h3>
                <div id="gradesChart" style="height: 350px;"></div>
            </div>
        </div>

        {{-- Taux d'activité --}}
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Taux d'activité mensuel</h3>
            <div id="activityChart" style="height: 400px;"></div>
        </div>

        {{-- Tableau d'évolution des grades --}}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Évolution des avancements de grade</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                            @for($i = 0; $i <= 7; $i++)
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Grade {{ $i }}</th>
                            @endfor
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($gradesEvolution as $period => $grades)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $period }}</td>
                                @php $total = 0; @endphp
                                @for($i = 0; $i <= 7; $i++)
                                    @php
                                        $count = $grades->where('nouveau_grade', $i)->count();
                                        $total += $count;
                                    @endphp
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center {{ $count > 0 ? 'text-gray-900 font-medium' : 'text-gray-400' }}">
                                        {{ $count > 0 ? $count : '-' }}
                                    </td>
                                @endfor
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center font-bold text-gray-900">{{ $total }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- Modal d'export --}}
<div id="exportModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form action="{{ route('admin.reports.export') }}" method="POST">
                @csrf
                <input type="hidden" name="report_type" value="network-growth">
                <input type="hidden" name="period_start" value="{{ $periodStart }}">
                <input type="hidden" name="period_end" value="{{ $periodEnd }}">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Exporter le rapport de croissance
                    </h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Format d'export</label>
                            <select name="format" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="csv">CSV</option>
                                <option value="pdf">PDF</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Exporter
                    </button>
                    <button type="button" onclick="closeExportModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
<script>
// Données
var evolutionData = @json($distributeursEvolution);
var activityData = @json($activityRate);

// Graphique d'évolution
var evolutionOptions = {
    series: [{
        name: 'Nouveaux distributeurs',
        data: evolutionData.map(item => item.count)
    }],
    chart: {
        height: 350,
        type: 'area',
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth'
    },
    xaxis: {
        categories: evolutionData.map(item => item.period)
    },
    yaxis: {
        title: {
            text: 'Nombre de distributeurs'
        }
    },
    tooltip: {
        y: {
            formatter: function (val) {
                return val + " distributeurs";
            }
        }
    },
    fill: {
        type: 'gradient',
        gradient: {
            shadeIntensity: 1,
            opacityFrom: 0.7,
            opacityTo: 0.9,
            stops: [0, 90, 100]
        }
    },
    colors: ['#3B82F6']
};

var evolutionChart = new ApexCharts(document.querySelector("#evolutionChart"), evolutionOptions);
evolutionChart.render();

// Graphique des grades
var gradesData = @json(\App\Models\Distributeur::selectRaw('etoiles_id, COUNT(*) as count')->groupBy('etoiles_id')->get());
var gradesOptions = {
    series: gradesData.map(item => item.count),
    chart: {
        height: 350,
        type: 'donut',
    },
    labels: gradesData.map(item => 'Grade ' + item.etoiles_id),
    colors: ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#EC4899', '#6B7280', '#06B6D4'],
    dataLabels: {
        enabled: true,
        formatter: function (val, opts) {
            return opts.w.config.series[opts.seriesIndex];
        }
    },
    legend: {
        position: 'bottom'
    }
};

var gradesChart = new ApexCharts(document.querySelector("#gradesChart"), gradesOptions);
gradesChart.render();

// Graphique taux d'activité
var activityOptions = {
    series: [{
        name: 'Taux d\'activité',
        data: activityData.map(item => item.rate)
    }],
    chart: {
        height: 400,
        type: 'line',
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 3
    },
    xaxis: {
        categories: activityData.map(item => item.period)
    },
    yaxis: {
        title: {
            text: 'Taux d\'activité (%)'
        },
        min: 0,
        max: 100
    },
    tooltip: {
        y: {
            formatter: function (val) {
                return val.toFixed(1) + "%";
            }
        }
    },
    colors: ['#10B981']
};

var activityChart = new ApexCharts(document.querySelector("#activityChart"), activityOptions);
activityChart.render();

// Fonctions modal
function exportReport() {
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
}
</script>
@endsection
