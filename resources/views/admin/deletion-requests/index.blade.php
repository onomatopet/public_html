{{-- resources/views/admin/deletion-requests/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'Demandes de suppression')

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- En-tête avec fil d'Ariane --}}
        <div class="bg-white rounded-xl shadow-sm px-6 py-5 mb-8 border border-gray-100">
            <nav class="flex items-center text-sm">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de Bord
                </a>
                <span class="mx-3 text-gray-300">/</span>
                <span class="text-gray-700 font-medium">Demandes de suppression</span>
            </nav>
        </div>

        {{-- Titre et actions --}}
        <div class="mb-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-6 lg:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Demandes de suppression</h1>
                    <p class="text-lg text-gray-600">Gérez les demandes de suppression avec workflow d'approbation</p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('admin.deletion-requests.backups') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V2"/>
                        </svg>
                        Backups
                    </a>
                    <a href="{{ route('admin.deletion-requests.export') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 border border-transparent text-sm font-medium rounded-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                        Exporter
                    </a>
                </div>
            </div>
        </div>

        {{-- Statistiques --}}
        @if(isset($stats))
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-gray-100 rounded-lg p-3">
                        <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Total</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $stats['total'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-lg p-3">
                        <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">En attente</p>
                        <p class="text-2xl font-bold text-yellow-600">{{ $stats['pending'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Approuvées</p>
                        <p class="text-2xl font-bold text-green-600">{{ $stats['approved'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-lg p-3">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Exécutées</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $stats['completed'] ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm p-6 border border-gray-100 hover:shadow-md transition-shadow duration-200">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-100 rounded-lg p-3">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </div>
                    <div class="ml-5">
                        <p class="text-sm font-medium text-gray-500">Rejetées</p>
                        <p class="text-2xl font-bold text-red-600">{{ $stats['rejected'] ?? 0 }}</p>
                    </div>
                </div>
            </div>
        </div>
        @endif

        {{-- Filtres --}}
        <div class="bg-white rounded-xl shadow-sm p-6 mb-8 border border-gray-100">
            <form method="GET" action="{{ route('admin.deletion-requests.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    {{-- Filtre par statut --}}
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                        <select name="status" id="status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            <option value="">Tous les statuts</option>
                            <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>En attente</option>
                            <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approuvé</option>
                            <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejeté</option>
                            <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Exécuté</option>
                            <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Annulé</option>
                        </select>
                    </div>

                    {{-- Filtre par type --}}
                    <div>
                        <label for="entity_type" class="block text-sm font-medium text-gray-700 mb-2">Type d'entité</label>
                        <select name="entity_type" id="entity_type"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            <option value="">Tous les types</option>
                            <option value="distributeur" {{ request('entity_type') == 'distributeur' ? 'selected' : '' }}>Distributeur</option>
                            <option value="achat" {{ request('entity_type') == 'achat' ? 'selected' : '' }}>Achat</option>
                            <option value="product" {{ request('entity_type') == 'product' ? 'selected' : '' }}>Produit</option>
                        </select>
                    </div>

                    {{-- Recherche --}}
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Rechercher</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                               placeholder="ID, raison..."
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                    </div>

                    {{-- Boutons --}}
                    <div class="flex items-end gap-2">
                        <button type="submit"
                                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                            <svg class="w-5 h-5 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                            </svg>
                            Filtrer
                        </button>
                        <a href="{{ route('admin.deletion-requests.index') }}"
                           class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </a>
                    </div>
                </div>
            </form>
        </div>

        {{-- Tableau des demandes --}}
        <div class="bg-white rounded-xl shadow-sm overflow-hidden border border-gray-100">
            @if($deletionRequests->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    ID / Date
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Type / Entité
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Demandé par
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Raison
                                </th>
                                <th scope="col" class="px-6 py-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Statut
                                </th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($deletionRequests as $request)
                            <tr class="hover:bg-gray-50 transition-colors duration-150">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">#{{ $request->id }}</div>
                                        <div class="text-xs text-gray-500">{{ $request->created_at->format('d/m/Y H:i') }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            {{ ucfirst($request->entity_type) }}
                                        </span>
                                        <div class="text-sm text-gray-500 mt-1">#{{ $request->entity_id }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-8 w-8">
                                            <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                                <span class="text-xs font-medium text-gray-600">
                                                    {{ substr($request->requestedBy->name ?? 'N', 0, 2) }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-3">
                                            <div class="text-sm font-medium text-gray-900">
                                                {{ $request->requestedBy->name ?? 'N/A' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-sm text-gray-900 truncate max-w-xs" title="{{ $request->reason }}">
                                        {{ Str::limit($request->reason, 50) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                                        @if($request->status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($request->status === 'approved') bg-green-100 text-green-800
                                        @elseif($request->status === 'rejected') bg-red-100 text-red-800
                                        @elseif($request->status === 'completed') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($request->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <a href="{{ route('admin.deletion-requests.show', $request) }}"
                                       class="text-blue-600 hover:text-blue-900 transition-colors duration-200">
                                        Voir détails
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="bg-gray-50 px-4 py-3 border-t border-gray-200">
                    {{ $deletionRequests->withQueryString()->links() }}
                </div>
            @else
                {{-- Message vide --}}
                <div class="text-center py-16">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    <h3 class="mt-3 text-sm font-medium text-gray-900">Aucune demande de suppression</h3>
                    <p class="mt-1 text-sm text-gray-500">
                        @if(request()->anyFilled(['status', 'entity_type', 'search']))
                            Aucune demande ne correspond à vos critères de recherche.
                        @else
                            Il n'y a aucune demande de suppression pour le moment.
                        @endif
                    </p>
                    @if(request()->anyFilled(['status', 'entity_type', 'search']))
                        <div class="mt-6">
                            <a href="{{ route('admin.deletion-requests.index') }}"
                               class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Réinitialiser les filtres
                            </a>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
