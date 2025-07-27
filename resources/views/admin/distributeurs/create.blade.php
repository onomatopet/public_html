{{-- resources/views/admin/distributeurs/create.blade.php --}}

@extends('layouts.admin')

@section('title', 'Ajouter un Distributeur')

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
                <a href="{{ route('admin.distributeurs.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Distributeurs
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Nouveau Distributeur</span>
            </nav>
        </div>

        {{-- Titre principal --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Ajouter un nouveau distributeur</h1>
            <p class="mt-2 text-gray-600">Remplissez le formulaire ci-dessous pour créer un nouveau distributeur dans le système.</p>
        </div>

        {{-- Affichage des erreurs --}}
        @if ($errors->any())
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Des erreurs ont été détectées :</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc pl-5 space-y-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Formulaire principal --}}
        <form method="POST" action="{{ route('admin.distributeurs.store') }}" class="space-y-8">
            @csrf

            {{-- Sections côte à côte sur grand écran --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Section Informations personnelles --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Informations personnelles
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="space-y-6">
                            {{-- Prénom --}}
                            <div>
                                <label for="pnom_distributeur" class="block text-sm font-medium text-gray-700 mb-2">
                                    Prénom <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="pnom_distributeur"
                                    id="pnom_distributeur"
                                    value="{{ old('pnom_distributeur') }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('pnom_distributeur') border-red-500 @enderror"
                                    placeholder="Entrez le prénom"
                                    required
                                >
                                @error('pnom_distributeur')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Nom --}}
                            <div>
                                <label for="nom_distributeur" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nom <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="nom_distributeur"
                                    id="nom_distributeur"
                                    value="{{ old('nom_distributeur') }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('nom_distributeur') border-red-500 @enderror"
                                    placeholder="Entrez le nom"
                                    required
                                >
                                @error('nom_distributeur')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Matricule --}}
                            <div>
                                <label for="distributeur_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Matricule <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">#</span>
                                    </div>
                                    <input
                                        type="text"
                                        name="distributeur_id"
                                        id="distributeur_id"
                                        value="{{ old('distributeur_id') }}"
                                        class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('distributeur_id') border-red-500 @enderror"
                                        placeholder="Ex: 12345"
                                        required
                                    >
                                </div>
                                @error('distributeur_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Téléphone --}}
                            <div>
                                <label for="tel_distributeur" class="block text-sm font-medium text-gray-700 mb-2">
                                    Téléphone
                                </label>
                                <input
                                    type="tel"
                                    name="tel_distributeur"
                                    id="tel_distributeur"
                                    value="{{ old('tel_distributeur') }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('tel_distributeur') border-red-500 @enderror"
                                    placeholder="Ex: +242 06 XXX XX XX"
                                >
                                @error('tel_distributeur')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Adresse --}}
                            <div>
                                <label for="adress_distributeur" class="block text-sm font-medium text-gray-700 mb-2">
                                    Adresse
                                </label>
                                <textarea
                                    name="adress_distributeur"
                                    id="adress_distributeur"
                                    rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('adress_distributeur') border-red-500 @enderror"
                                    placeholder="Entrez l'adresse complète"
                                >{{ old('adress_distributeur') }}</textarea>
                                @error('adress_distributeur')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section Parrainage et Niveau --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Parrainage et Niveau
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                    {{-- Parent (Sponsor) avec recherche AJAX --}}
                    <div>
                        <label for="parent_search" class="block text-sm font-medium text-gray-700 mb-2">
                            Parent / Sponsor
                        </label>

                        {{-- Champ de recherche --}}
                        <div class="relative">
                            <input
                                type="text"
                                id="parent_search"
                                placeholder="Rechercher par matricule, nom ou prénom..."
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                autocomplete="off"
                            >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>
                        </div>

                        {{-- Champ caché pour l'ID du parent --}}
                        <input
                            type="hidden"
                            name="id_distrib_parent"
                            id="id_distrib_parent"
                            value="{{ old('id_distrib_parent') }}"
                        >

                        {{-- Affichage du parent sélectionné --}}
                        <div id="selected_parent" class="mt-2 p-3 bg-blue-50 rounded-lg hidden">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm font-medium text-blue-900">Parent sélectionné :</p>
                                    <p id="parent_display" class="text-sm text-blue-700"></p>
                                </div>
                                <button type="button" onclick="clearParent()" class="text-blue-600 hover:text-blue-800">
                                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Liste des résultats de recherche --}}
                        <div id="search_results" class="absolute z-10 w-full mt-1 bg-white rounded-lg shadow-lg hidden max-h-60 overflow-y-auto">
                            <!-- Les résultats seront ajoutés ici via JavaScript -->
                        </div>

                        @error('id_distrib_parent')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-2 text-sm text-gray-500">
                            Commencez à taper pour rechercher un distributeur parent. Laissez vide pour un distributeur racine.
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {{-- Niveau (Étoiles) --}}
                        <div>
                            <label for="etoiles_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Niveau (Étoiles) <span class="text-red-500">*</span>
                            </label>
                            <select
                                name="etoiles_id"
                                id="etoiles_id"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('etoiles_id') border-red-500 @enderror"
                                required
                            >
                                @for($i = 1; $i <= 10; $i++)
                                    <option value="{{ $i }}" {{ old('etoiles_id', 1) == $i ? 'selected' : '' }}>
                                        {{ $i }} étoile{{ $i > 1 ? 's' : '' }}
                                    </option>
                                @endfor
                            </select>
                            @error('etoiles_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Rang --}}
                        <div>
                            <label for="rang" class="block text-sm font-medium text-gray-700 mb-2">
                                Rang
                            </label>
                            <input
                                type="number"
                                name="rang"
                                id="rang"
                                value="{{ old('rang', 0) }}"
                                min="0"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('rang') border-red-500 @enderror"
                            >
                            @error('rang')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                        {{-- Statut de validation --}}
                        <div>
                            <label class="flex items-center">
                                <input
                                    type="checkbox"
                                    name="statut_validation_periode"
                                    value="1"
                                    {{ old('statut_validation_periode', 0) ? 'checked' : '' }}
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded transition duration-200"
                                >
                                <span class="ml-2 text-sm text-gray-700">
                                    Statut de validation période actif
                                </span>
                            </label>
                            @error('statut_validation_periode')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Section Performance initiale --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Performance initiale <span class="text-sm font-normal ml-2">(optionnel)</span>
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        {{-- Message d'information --}}
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 rounded">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        Si le distributeur a déjà un historique de performance, vous pouvez l'initialiser ici.
                                        Sinon, laissez ces champs à 0. Le distributeur sera automatiquement initialisé dans le système
                                        de suivi des performances pour la période courante ({{ date('Y-m') }}).
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-6">
                            {{-- Cumul individuel --}}
                            <div>
                                <label for="cumul_individuel" class="block text-sm font-medium text-gray-700 mb-2">
                                    Cumul individuel historique
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        name="cumul_individuel"
                                        id="cumul_individuel"
                                        value="{{ old('cumul_individuel', 0) }}"
                                        min="0"
                                        step="0.01"
                                        class="w-full pl-12 pr-20 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 @error('cumul_individuel') border-red-500 @enderror"
                                        placeholder="0.00"
                                    >
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Points</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Points personnels accumulés depuis le début</p>
                                @error('cumul_individuel')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Cumul collectif --}}
                            <div>
                                <label for="cumul_collectif" class="block text-sm font-medium text-gray-700 mb-2">
                                    Cumul collectif historique
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        name="cumul_collectif"
                                        id="cumul_collectif"
                                        value="{{ old('cumul_collectif', 0) }}"
                                        min="0"
                                        step="0.01"
                                        class="w-full pl-12 pr-20 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors duration-200 @error('cumul_collectif') border-red-500 @enderror"
                                        placeholder="0.00"
                                    >
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                        </svg>
                                    </div>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">Points</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-500">Points totaux (personnel + réseau) accumulés</p>
                                @error('cumul_collectif')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Note d'avertissement --}}
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            <strong>Important :</strong> Le cumul collectif doit être supérieur ou égal au cumul individuel.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Boutons d'action --}}
            <div class="flex items-center justify-end space-x-4 pt-4">
                <a href="{{ route('admin.distributeurs.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors duration-200">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    Créer le distributeur
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Script pour la recherche AJAX du parent --}}
@push('scripts')
<script>
    let searchTimeout;
    const searchInput = document.getElementById('parent_search');
    const searchResults = document.getElementById('search_results');
    const selectedParentDiv = document.getElementById('selected_parent');
    const parentDisplay = document.getElementById('parent_display');
    const parentIdInput = document.getElementById('id_distrib_parent');

    // Gestion de la recherche
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            return;
        }

        // Afficher un loader
        searchResults.innerHTML = `
            <div class="p-4 text-center">
                <svg class="animate-spin h-5 w-5 mx-auto text-gray-500" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="mt-2 text-sm text-gray-500">Recherche en cours...</p>
            </div>
        `;
        searchResults.classList.remove('hidden');

        // Délai avant la recherche pour éviter trop de requêtes
        searchTimeout = setTimeout(() => {
            searchDistributeurs(query);
        }, 300);
    });

    // Fonction de recherche AJAX
    function searchDistributeurs(query) {
        fetch(`{{ route('admin.distributeurs.search') }}?q=${encodeURIComponent(query)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            }
        })
        .then(response => {
            if (!response.ok) throw new Error('Network response was not ok');
            return response.json();
        })
        .then(data => {
            searchResults.innerHTML = '';

            if (!data.results || data.results.length === 0) {
                searchResults.innerHTML = `
                    <div class="p-4 text-center text-gray-500">
                        Aucun distributeur trouvé
                    </div>
                `;
            } else {
                data.results.forEach(distributeur => {
                    const item = document.createElement('div');
                    item.className = 'px-4 py-3 hover:bg-gray-50 cursor-pointer border-b last:border-b-0';
                    item.innerHTML = `
                        <div class="font-medium text-gray-900">
                            ${distributeur.text}
                        </div>
                        ${distributeur.tel_distributeur ? `<div class="text-sm text-gray-500">${distributeur.tel_distributeur}</div>` : ''}
                    `;
                    item.addEventListener('click', () => selectParent(distributeur));
                    searchResults.appendChild(item);
                });
            }
        })
        .catch(error => {
            console.error('Search error:', error);
            searchResults.innerHTML = `
                <div class="p-4 text-center text-red-500">
                    Erreur lors de la recherche
                </div>
            `;
        });
    }

    // Sélectionner un parent
    function selectParent(distributeur) {
        parentIdInput.value = distributeur.id;
        parentDisplay.textContent = distributeur.text;
        selectedParentDiv.classList.remove('hidden');
        searchInput.value = '';
        searchResults.classList.add('hidden');
        searchResults.innerHTML = '';
    }

    // Effacer la sélection
    function clearParent() {
        parentIdInput.value = '';
        selectedParentDiv.classList.add('hidden');
        searchInput.value = '';
        parentDisplay.textContent = '';
    }

    // Fermer les résultats quand on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    // Si un parent était sélectionné (old value), l'afficher
    @if(old('id_distrib_parent'))
        // Faire une requête pour récupérer les infos du parent
        fetch(`{{ route('admin.distributeurs.show', '') }}/${encodeURIComponent('{{ old('id_distrib_parent') }}')}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.distributeur) {
                selectParent({
                    id: data.distributeur.id,
                    text: `#${data.distributeur.distributeur_id} - ${data.distributeur.pnom_distributeur} ${data.distributeur.nom_distributeur}`
                });
            }
        })
        .catch(error => console.error('Error loading parent:', error));
    @endif

    // Validation côté client pour cohérence des cumuls
    document.getElementById('cumul_individuel').addEventListener('input', validateCumuls);
    document.getElementById('cumul_collectif').addEventListener('input', validateCumuls);

    function validateCumuls() {
        const cumulIndividuel = parseFloat(document.getElementById('cumul_individuel').value) || 0;
        const cumulCollectif = parseFloat(document.getElementById('cumul_collectif').value) || 0;
        const cumulCollectifInput = document.getElementById('cumul_collectif');

        if (cumulIndividuel > cumulCollectif) {
            cumulCollectifInput.classList.add('border-red-500');
            cumulCollectifInput.classList.remove('border-gray-300');

            // Ajouter un message d'erreur s'il n'existe pas déjà
            let errorMsg = cumulCollectifInput.parentElement.parentElement.querySelector('.cumul-error-msg');
            if (!errorMsg) {
                errorMsg = document.createElement('p');
                errorMsg.className = 'mt-1 text-sm text-red-600 cumul-error-msg';
                errorMsg.textContent = 'Le cumul collectif doit être supérieur ou égal au cumul individuel';
                cumulCollectifInput.parentElement.parentElement.appendChild(errorMsg);
            }
        } else {
            cumulCollectifInput.classList.remove('border-red-500');
            cumulCollectifInput.classList.add('border-gray-300');

            // Supprimer le message d'erreur s'il existe
            const errorMsg = cumulCollectifInput.parentElement.parentElement.querySelector('.cumul-error-msg');
            if (errorMsg) {
                errorMsg.remove();
            }
        }
    }

    // Validation avant soumission du formulaire
    document.querySelector('form').addEventListener('submit', function(e) {
        const cumulIndividuel = parseFloat(document.getElementById('cumul_individuel').value) || 0;
        const cumulCollectif = parseFloat(document.getElementById('cumul_collectif').value) || 0;

        if (cumulIndividuel > cumulCollectif) {
            e.preventDefault();
            alert('Le cumul collectif doit être supérieur ou égal au cumul individuel');
            document.getElementById('cumul_collectif').focus();
            return false;
        }
    });
</script>
@endpush

@endsection
