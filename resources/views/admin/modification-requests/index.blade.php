@extends('layouts.admin')

@section('title', 'Demandes de modification')

@section('content')
<div class="p-6">
    {{-- En-tête avec statistiques --}}
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-4">Demandes de modification</h1>

        {{-- Cartes de statistiques --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-yellow-100 rounded-lg">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">En attente</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['pending'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-red-100 rounded-lg">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Haut risque</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['high_risk'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 p-3 bg-orange-100 rounded-lg">
                        <svg class="h-6 w-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Expire bientôt</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $stats['expiring_soon'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="p-6">
            <form method="GET" action="{{ route('admin.modification-requests.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" id="status" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les statuts</option>
                        <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>En attente</option>
                        <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approuvé</option>
                        <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejeté</option>
                        <option value="executed" {{ request('status') == 'executed' ? 'selected' : '' }}>Exécuté</option>
                        <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Annulé</option>
                    </select>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" id="type" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les types</option>
                        <option value="parent_change" {{ request('type') == 'parent_change' ? 'selected' : '' }}>Changement de parent</option>
                        <option value="grade_change" {{ request('type') == 'grade_change' ? 'selected' : '' }}>Changement de grade</option>
                        <option value="cumul_adjustment" {{ request('type') == 'cumul_adjustment' ? 'selected' : '' }}>Ajustement de cumuls</option>
                    </select>
                </div>

                <div>
                    <label for="risk_level" class="block text-sm font-medium text-gray-700 mb-1">Niveau de risque</label>
                    <select name="risk_level" id="risk_level" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Tous les niveaux</option>
                        <option value="low" {{ request('risk_level') == 'low' ? 'selected' : '' }}>Faible</option>
                        <option value="medium" {{ request('risk_level') == 'medium' ? 'selected' : '' }}>Moyen</option>
                        <option value="high" {{ request('risk_level') == 'high' ? 'selected' : '' }}>Élevé</option>
                    </select>
                </div>

                <div class="flex items-end">
                    <button type="submit" class="w-full px-4 py-2 bg-gray-600 text-white rounded-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Tableau des demandes --}}
    <div class="bg-white shadow rounded-lg overflow-hidden">
        @if($modifications->isEmpty())
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">Aucune demande de modification</h3>
                <p class="mt-1 text-sm text-gray-500">
                    @if(request()->anyFilled(['status', 'type', 'risk_level']))
                        Aucune demande ne correspond à vos critères de recherche.
                    @else
                        Il n'y a aucune demande de modification en cours.
                    @endif
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entité</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Demandeur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risque</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($modifications as $modification)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    #{{ $modification->id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $typeConfig = [
                                            'parent_change' => ['label' => 'Changement parent', 'color' => 'blue'],
                                            'grade_change' => ['label' => 'Changement grade', 'color' => 'purple'],
                                            'cumul_adjustment' => ['label' => 'Ajustement cumuls', 'color' => 'green'],
                                        ];
                                        $config = $typeConfig[$modification->modification_type] ?? ['label' => $modification->modification_type, 'color' => 'gray'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $config['color'] }}-100 text-{{ $config['color'] }}-800">
                                        {{ $config['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $modification->entity_type }}: {{ $modification->entity_id }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $modification->requestedBy->name ?? 'N/A' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusConfig = [
                                            'pending' => ['label' => 'En attente', 'color' => 'yellow'],
                                            'approved' => ['label' => 'Approuvé', 'color' => 'green'],
                                            'rejected' => ['label' => 'Rejeté', 'color' => 'red'],
                                            'executed' => ['label' => 'Exécuté', 'color' => 'blue'],
                                            'cancelled' => ['label' => 'Annulé', 'color' => 'gray'],
                                        ];
                                        $sConfig = $statusConfig[$modification->status] ?? ['label' => $modification->status, 'color' => 'gray'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $sConfig['color'] }}-100 text-{{ $sConfig['color'] }}-800">
                                        {{ $sConfig['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $riskConfig = [
                                            'low' => ['label' => 'Faible', 'color' => 'green'],
                                            'medium' => ['label' => 'Moyen', 'color' => 'yellow'],
                                            'high' => ['label' => 'Élevé', 'color' => 'red'],
                                        ];
                                        $rConfig = $riskConfig[$modification->risk_level] ?? ['label' => $modification->risk_level, 'color' => 'gray'];
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $rConfig['color'] }}-100 text-{{ $rConfig['color'] }}-800">
                                        {{ $rConfig['label'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $modification->created_at->format('d/m/Y H:i') }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <a href="{{ route('admin.modification-requests.show', $modification) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        Voir
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                {{ $modifications->withQueryString()->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
