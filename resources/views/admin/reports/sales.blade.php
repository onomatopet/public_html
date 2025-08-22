@extends('layouts.admin')

@section('title', 'Rapport des ventes')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Rapport des ventes</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Analyse détaillée des performances commerciales
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
            <form method="GET" action="{{ route('admin.reports.sales') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période début</label>
                    <input type="month" name="period_start" value="{{ $periodStart }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Période fin</label>
                    <input type="month" name="period_end" value="{{ $periodEnd }}" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Grouper par</label>
                    <select name="group_by" class="w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 text-sm">
                        <option value="month" {{ $groupBy == 'month' ? 'selected' : '' }}>Mois</option>
                        <option value="week" {{ $groupBy == 'week' ? 'selected' : '' }}>Semaine</option>
                        <option value="day" {{ $groupBy == 'day' ? 'selected' : '' }}>Jour</option>
                        <option value="product" {{ $groupBy == 'product' ? 'selected' : '' }}>Produit</option>
                        <option value="distributeur" {{ $groupBy == 'distributeur' ? 'selected' : '' }}>Distributeur</option>
                    </select>
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

        {{-- Statistiques principales --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            @php
                $totalSales = $salesData->sum('total_amount');
                $totalQuantity = $salesData->sum('total_quantity');
                $avgTicket = $salesData->count() > 0 ? $totalSales / $salesData->count() : 0;
                $growthRate = 0; // À calculer selon la logique métier
            @endphp

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-blue-100 rounded-lg">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Total des ventes</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($totalSales, 2, ',', ' ') }} €</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-green-100 rounded-lg">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Quantité vendue</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($totalQuantity, 0, ',', ' ') }}</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-purple-100 rounded-lg">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Panier moyen</dt>
                            <dd class="mt-1 text-2xl font-semibold text-gray-900">{{ number_format($avgTicket, 2, ',', ' ') }} €</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500">Croissance</dt>
                            <dd class="mt-1 text-2xl font-semibold {{ $growthRate >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $growthRate >= 0 ? '+' : '' }}{{ number_format($growthRate, 1, ',', ' ') }}%
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        {{-- Graphique des ventes --}}
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Évolution des ventes</h3>
            <div id="salesChart" style="height: 400px;"></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Top produits --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Top 10 produits</h3>
                <div class="space-y-3">
                    @forelse($topProducts as $index => $product)
                        <div class="flex items-center justify-between p-3 {{ $index % 2 == 0 ? 'bg-gray-50' : '' }} rounded">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ $product->product->nom_produit ?? 'N/A' }}</p>
                                <p class="text-sm text-gray-500">{{ $product->product->code_product ?? '' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-900">{{ number_format($product->total_amount, 2, ',', ' ') }} €</p>
                                <p class="text-sm text-gray-500">{{ $product->total_quantity }} unités</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-4">Aucune donnée disponible</p>
                    @endforelse
                </div>
            </div>

            {{-- Top distributeurs --}}
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Top 10 distributeurs</h3>
                <div class="space-y-3">
                    @forelse($topDistributeurs as $index => $distrib)
                        <div class="flex items-center justify-between p-3 {{ $index % 2 == 0 ? 'bg-gray-50' : '' }} rounded">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">{{ $distrib->distributeur->nom_distributeur ?? '' }} {{ $distrib->distributeur->pnom_distributeur ?? '' }}</p>
                                <p class="text-sm text-gray-500">{{ $distrib->distributeur->distributeur_id ?? '' }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold text-gray-900">{{ number_format($distrib->total_amount, 2, ',', ' ') }} €</p>
                                <p class="text-sm text-gray-500">{{ $distrib->nb_achats }} achats</p>
                            </div>
                        </div>
                    @empty
                        <p class="text-gray-500 text-center py-4">Aucune donnée disponible</p>
                    @endforelse
                </div>
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
                <input type="hidden" name="report_type" value="sales">
                <input type="hidden" name="period_start" value="{{ $periodStart }}">
                <input type="hidden" name="period_end" value="{{ $periodEnd }}">
                <input type="hidden" name="group_by" value="{{ $groupBy }}">

                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                        Exporter le rapport des ventes
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
// Graphique des ventes
var salesData = @json($salesData);
var chartData = salesData.map(item => ({
    x: item.period || item.date,
    y: parseFloat(item.total_amount)
}));

var options = {
    series: [{
        name: 'Ventes',
        data: chartData
    }],
    chart: {
        height: 400,
        type: 'area',
        toolbar: {
            show: false
        }
    },
    dataLabels: {
        enabled: false
    },
    stroke: {
        curve: 'smooth',
        width: 2
    },
    xaxis: {
        type: 'category'
    },
    yaxis: {
        title: {
            text: 'Montant (€)'
        },
        labels: {
            formatter: function (value) {
                return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(value);
            }
        }
    },
    tooltip: {
        y: {
            formatter: function(value) {
                return new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(value);
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

var chart = new ApexCharts(document.querySelector("#salesChart"), options);
chart.render();

// Fonctions modal
function exportReport() {
    document.getElementById('exportModal').classList.remove('hidden');
}

function closeExportModal() {
    document.getElementById('exportModal').classList.add('hidden');
}
</script>
@endsection
