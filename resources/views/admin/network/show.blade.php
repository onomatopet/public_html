{{-- resources/views/admin/network/show.blade.php --}}
@extends('layouts.admin')

@section('title', 'Structure du Réseau')

@section('content')
<div class="min-h-screen bg-gray-50 py-8 print:py-0 print:bg-white print:min-h-0">
    <div class="px-4 sm:px-6 lg:px-8 print:px-0 print:max-w-none">
        {{-- En-tête pour écran uniquement --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6 print:hidden">
            <nav class="flex items-center text-sm">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de Bord
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.network.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Export Réseau
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Visualisation</span>
            </nav>
        </div>

        {{-- Section impression uniquement --}}
        <div class="hidden print:block">
            {{-- En-tête ETERNAL --}}
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold">ETERNAL Details Network Structure</h1>
                <p class="text-base mt-1">eternalcongo.com - contact@eternalcongo.com</p>
                <p class="text-sm mt-2">Print time: {{ now()->format('d-m-Y') }}</p>
            </div>

            {{-- Info distributeur principal --}}
            @if(isset($mainDistributor))
            <div class="text-center mb-4">
                <h2 class="text-xl font-bold">
                    {{ strtoupper($mainDistributor->nom_distributeur) }} {{ strtoupper($mainDistributor->pnom_distributeur) }}
                    ({{ $mainDistributor->distributeur_id }})
                </h2>
                <p class="text-base">Période: {{ $period }} | Total réseau: {{ $totalCount ?? count($distributeurs) }} distributeurs</p>
            </div>
            @endif
        </div>

        {{-- En-tête du rapport pour écran --}}
        <div class="bg-white rounded-lg shadow-sm mb-6 print:hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Structure du Réseau</h1>
                        <p class="text-sm text-gray-600 mt-1">Visualisation détaillée de la hiérarchie</p>
                    </div>
                    <div class="flex space-x-3">
                        {{-- Bouton Imprimer --}}
                        <button onclick="openPrintView()"
                                class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Imprimer
                        </button>
                    </div>
                </div>

                {{-- Informations sur le distributeur principal (écran uniquement) --}}
                @if(isset($distributeurs) && count($distributeurs) > 0)
                    @php
                        $distributeurPrincipal = collect($distributeurs)->firstWhere('rang', 0);
                    @endphp
                    @if($distributeurPrincipal)
                        <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-medium text-blue-900">
                                        {{ $distributeurPrincipal['nom_distributeur'] }} {{ $distributeurPrincipal['pnom_distributeur'] }}
                                        ({{ $distributeurPrincipal['distributeur_id'] }})
                                    </h3>
                                    <p class="text-sm text-blue-700">
                                        Période: {{ request('period', now()->format('Y-m')) }} |
                                        Total réseau: {{ collect($distributeurs)->where('type', 'distributeur')->count() }} distributeurs
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>

        {{-- Tableau de la structure du réseau --}}
        <div class="bg-white shadow-lg rounded-lg overflow-hidden print:shadow-none print:rounded-none print:bg-transparent">
            <div class="overflow-x-auto print:overflow-visible">
                <table class="min-w-full divide-y divide-gray-200 print:border-collapse print:w-full">
                    <thead class="bg-gray-50 print:bg-transparent">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                ID
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                Nom & Prénom
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                Rang
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                Ind.<br class="print:hidden"/>PV
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                New<br class="print:hidden"/>PV
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                Total<br class="print:hidden"/>PV
                            </th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                Cumulative<br class="print:hidden"/>PV
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                ID<br class="print:hidden"/>references
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider print:px-4 print:py-2 print:text-black print:font-bold print:text-sm print:border print:border-black">
                                References Name
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200 print:bg-transparent">
                        @foreach($distributeurs as $item)
                            @if($item['type'] === 'distributeur')
                                <tr class="{{ $item['rang'] == 0 ? 'bg-yellow-50 print:bg-transparent' : ($item['rang'] == 1 ? 'bg-blue-50 print:bg-transparent' : 'bg-white print:bg-transparent') }}">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        {{ $item['distributeur_id'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        {{-- Indentation pour la hiérarchie --}}
                                        <span class="inline-block" style="margin-left: {{ $item['rang'] * 1.5 }}rem">
                                            {{ $item['rang'] }}
                                        </span>
                                        {{ strtoupper($item['nom_distributeur']) }} {{ strtoupper($item['pnom_distributeur']) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        {{ $item['etoiles'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        ${{ number_format($item['cumul_individuel'] ?? 0, 0, '.', '') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        ${{ number_format($item['new_cumul'] ?? 0, 0, '.', '') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        ${{ number_format($item['cumul_total'] ?? 0, 0, '.', '') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        ${{ number_format($item['cumul_collectif'] ?? 0, 0, '.', '') }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        {{ $item['id_distrib_parent'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 print:px-4 print:py-2 print:border print:border-gray-300">
                                        {{ strtoupper($item['nom_parent']) }} {{ strtoupper($item['pnom_parent']) }}
                                    </td>
                                </tr>
                            @elseif($item['type'] === 'sous_total')
                                {{-- Ligne de sous-total simple --}}
                                <tr class="border-t border-gray-400 print:border-gray-600">
                                    <td colspan="8" class="px-6 py-2 text-sm font-medium text-gray-700 bg-gray-100 print:bg-gray-200 print:px-4 print:py-1 print:border print:border-gray-600">
                                        Sous-total Pied {{ $item['pied_number'] }}: {{ $item['total_distributeurs'] }} distributeurs | PV Total: ${{ number_format($item['total_pv'], 0, '.', ' ') }}
                                    </td>
                                </tr>
                                {{-- Double ligne de séparation --}}
                                <tr class="border-t-2 border-gray-900 print:border-black">
                                    <td colspan="8" class="p-0"></td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                    <tfoot class="print:border-t-2 print:border-black">
                        <tr>
                            <td colspan="8" class="px-6 py-4 text-sm text-gray-700 print:px-4 print:py-2">
                                <div class="flex justify-between">
                                    <span>Total distributeurs: {{ collect($distributeurs)->where('type', 'distributeur')->count() }}</span>
                                    <span>Total PV cumulés: ${{ number_format(collect($distributeurs)->where('type', 'distributeur')->sum('cumul_collectif'), 0, '.', ' ') }}</span>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
/* Styles d'impression pour correspondre exactement au PDF */
@media print {
    /* Configuration de la page */
    @page {
        size: A4 portrait;
        margin: 15mm;
    }

    /* Reset global pour impression */
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    /* Styles du corps */
    body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        font-family: Arial, sans-serif !important;
        font-size: 12pt !important;
    }

    /* Forcer les sauts de page appropriés */
    table {
        page-break-inside: auto;
        width: 100% !important;
        border-collapse: collapse !important;
    }

    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }

    thead {
        display: table-header-group;
    }

    tfoot {
        display: table-footer-group;
    }

    /* Styles de tableau */
    table th,
    table td {
        padding: 8px 12px !important;
        border: 1px solid #ccc !important;
        font-size: 11pt !important;
    }

    table th {
        background-color: #f0f0f0 !important;
        font-weight: bold !important;
        text-align: left !important;
        border: 1px solid #000 !important;
    }

    /* Alignements spécifiques */
    th:nth-child(3),
    td:nth-child(3) {
        text-align: center !important;
    }

    th:nth-child(4),
    th:nth-child(5),
    th:nth-child(6),
    td:nth-child(4),
    td:nth-child(5),
    td:nth-child(6) {
        text-align: right !important;
    }

    /* En-têtes */
    h1, h2 {
        margin: 0 0 10px 0 !important;
        padding: 0 !important;
    }

    /* Visibilité des éléments */
    .print\:block {
        display: block !important;
    }

    .print\:hidden {
        display: none !important;
    }

    /* Suppression des ombres et bordures */
    .shadow-lg,
    .shadow-sm,
    .rounded-lg {
        box-shadow: none !important;
        border-radius: 0 !important;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Fonction pour ouvrir la vue imprimable
function openPrintView() {
    // Créer un formulaire temporaire
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("admin.network.export.html") }}';
    form.target = '_blank';

    // Ajouter le token CSRF
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    form.appendChild(csrfInput);

    // Ajouter les paramètres
    const distribInput = document.createElement('input');
    distribInput.type = 'hidden';
    distribInput.name = 'distributeur_id';
    distribInput.value = '{{ request("distributeur_id") }}';
    form.appendChild(distribInput);

    const periodInput = document.createElement('input');
    periodInput.type = 'hidden';
    periodInput.name = 'period';
    periodInput.value = '{{ request("period", $period) }}';
    form.appendChild(periodInput);

    // Ajouter le formulaire au body et le soumettre
    document.body.appendChild(form);
    form.submit();

    // Supprimer le formulaire après soumission
    setTimeout(() => {
        document.body.removeChild(form);
    }, 100);
}
</script>
@endpush
