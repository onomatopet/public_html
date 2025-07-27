{{-- resources/views/admin/achats/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'Gestion des Achats')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- En-tête avec fil d'Ariane --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <nav class="flex items-center text-sm">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de Bord
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Achats</span>
            </nav>
        </div>

        {{-- Messages de session --}}
        @if(session('success'))
            <div class="mb-6">
                <div class="bg-green-50 border-l-4 border-green-400 p-4 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-green-800">{{ session('success') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6">
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- En-tête et actions --}}
        <div class="sm:flex sm:items-center mb-8">
            <div class="sm:flex-auto">
                <h1 class="text-3xl font-bold text-gray-900">Gestion des Achats</h1>
                <p class="mt-2 text-gray-600">
                    Liste complète des achats effectués par les distributeurs.
                    @if($searchTerm)
                        Résultats pour : <strong>"{{ $searchTerm }}"</strong>
                    @endif
                    @if($selectedPeriod)
                        | Période : <strong>{{ $selectedPeriod }}</strong>
                    @endif
                </p>
            </div>
            <div class="mt-4 sm:ml-16 sm:mt-0 sm:flex-none">
                <div class="flex space-x-3">
                    {{-- Bouton Session d'achats --}}
                    <a href="{{ route('admin.achats.session.start') }}"
                    class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Session d'achats
                    </a>

                    {{-- Bouton Nouvel achat --}}
                    <a href="{{ route('admin.achats.create') }}"
                    class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                        </svg>
                        Nouvel Achat
                    </a>
                </div>
            </div>
        </div>

        {{-- Alerte si une session est en cours --}}
        @if(session()->has('achats_session'))
            <div class="mb-6 bg-yellow-50 border-l-4 border-yellow-400 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            <strong class="font-medium">Session en cours !</strong>
                            Une session d'achats est active pour {{ session('achats_session.distributeur_info') }}.
                            <a href="{{ route('admin.achats.session.summary') }}" class="font-medium underline text-yellow-700 hover:text-yellow-600">
                                Continuer la session →
                            </a>
                        </p>
                    </div>
                    <div class="ml-auto pl-3">
                        <div class="-mx-1.5 -my-1.5">
                            <button type="button" onclick="this.parentElement.parentElement.parentElement.parentElement.remove()"
                                    class="inline-flex bg-yellow-50 rounded-md p-1.5 text-yellow-500 hover:bg-yellow-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-yellow-50 focus:ring-yellow-600">
                                <span class="sr-only">Fermer</span>
                                <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Filtres et recherche --}}
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('admin.achats.index') }}" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Recherche --}}
                    <div>
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                            Rechercher un achat
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                            <input type="text" name="search" id="search" value="{{ $searchTerm }}"
                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                   placeholder="ID achat, distributeur, produit...">
                        </div>
                    </div>

                    {{-- Filtre par période --}}
                    <div>
                        <label for="period_filter" class="block text-sm font-medium text-gray-700 mb-2">
                            Filtrer par période
                        </label>
                        <select name="period_filter" id="period_filter"
                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                            <option value="">Toutes les périodes</option>
                            @foreach($availablePeriods as $period)
                                <option value="{{ $period }}" {{ $selectedPeriod == $period ? 'selected' : '' }}>
                                    {{ $period }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filtrer
                    </button>
                    @if($searchTerm || $selectedPeriod)
                        <a href="{{ route('admin.achats.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-400 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                            Effacer les filtres
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Résumé des résultats --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">
                        @if($achats->total() > 0)
                            Affichage de {{ $achats->firstItem() }} à {{ $achats->lastItem() }} sur {{ $achats->total() }} achats
                        @else
                            Aucun achat trouvé
                        @endif
                    </p>
                    @if($searchTerm)
                        <p class="text-sm text-gray-600">Résultats pour : <span class="font-medium text-gray-900">"{{ $searchTerm }}"</span></p>
                    @endif
                </div>
                @if($statistics && $selectedPeriod)
                    <div class="text-right">
                        <p class="text-sm text-gray-600">Total CA période {{ $selectedPeriod }} :
                            <span class="font-semibold text-green-600">{{ number_format($statistics['total_montant'], 0, ',', ' ') }} FCFA</span>
                        </p>
                        <p class="text-sm text-gray-600">Total PV période :
                            <span class="font-semibold text-blue-600">{{ number_format($statistics['total_points'], 0, ',', ' ') }} PV</span>
                        </p>
                        <p class="text-sm text-gray-600">Nombre d'achats :
                            <span class="font-semibold text-gray-900">{{ $statistics['total_achats'] }}</span>
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <!-- Tableau des achats -->
        <div class="mt-8 flow-root">
            <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle sm:px-6 lg:px-8">
                    @if($achats->count() > 0)
                        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            Achat
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            Distributeur
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            Produit
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            Quantité & Points
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wide">
                                            Montant
                                        </th>
                                        <th scope="col" class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    @foreach($achats as $achat)
                                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-12 w-12">
                                                        <div class="h-12 w-12 rounded-lg bg-green-100 flex items-center justify-center">
                                                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                                                            </svg>
                                                        </div>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            Achat #{{ $achat->id }}
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                                                {{ $achat->period }}
                                                            </span>
                                                        </div>
                                                        <div class="text-xs text-gray-400 mt-1">
                                                            {{ $achat->created_at->format('d/m/Y H:i') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($achat->distributeur)
                                                    <div class="text-sm text-gray-900">
                                                        {{ $achat->distributeur->pnom_distributeur }} {{ $achat->distributeur->nom_distributeur }}
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        #{{ $achat->distributeur->distributeur_id }}
                                                    </div>
                                                @else
                                                    <div class="text-sm text-gray-500 italic">
                                                        Distributeur non trouvé
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    {{ $achat->product->nom_produit }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    Code: {{ $achat->product->code_product }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900">
                                                    Qté: {{ $achat->qt }}
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ number_format($achat->points_unitaire_achat, 2) }} PV/unité
                                                </div>
                                                <div class="text-sm font-medium text-blue-600">
                                                    Total: {{ number_format($achat->qt * $achat->points_unitaire_achat, 2) }} PV
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-green-600">
                                                    {{ number_format($achat->montant_total_ligne, 0, ',', ' ') }} FCFA
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    {{ number_format($achat->prix_unitaire_achat, 0, ',', ' ') }} FCFA/unité
                                                </div>
                                                @if($achat->online)
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800 mt-1">
                                                        En ligne
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-2">
                                                    <a href="{{ route('admin.achats.show', $achat) }}" class="inline-flex items-center p-1.5 border border-transparent text-xs font-medium rounded text-blue-600 hover:text-blue-900 focus:outline-none transition-colors duration-200" title="Voir les détails">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                        </svg>
                                                    </a>
                                                    <a href="{{ route('admin.achats.edit', $achat) }}" class="inline-flex items-center p-1.5 border border-transparent text-xs font-medium rounded text-yellow-600 hover:text-yellow-900 focus:outline-none transition-colors duration-200" title="Modifier">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                        </svg>
                                                    </a>
                                                    <form action="{{ route('admin.achats.destroy', $achat) }}" method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet achat ?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="inline-flex items-center p-1.5 border border-transparent text-xs font-medium rounded text-red-600 hover:text-red-900 focus:outline-none transition-colors duration-200" title="Supprimer">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        {{-- Pagination --}}
                        <div class="mt-6">
                            {{ $achats->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun achat trouvé</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                @if($searchTerm || $selectedPeriod)
                                    Aucun achat ne correspond à vos critères de recherche.
                                @else
                                    Commencez par enregistrer le premier achat.
                                @endif
                            </p>
                            @if(!$searchTerm && !$selectedPeriod)
                                <div class="mt-6">
                                    <a href="{{ route('admin.achats.create') }}" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                        </svg>
                                        Enregistrer le premier achat
                                    </a>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
