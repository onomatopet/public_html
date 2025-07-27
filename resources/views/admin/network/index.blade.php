@extends('layouts.admin')

@section('title', 'Export Réseau Distributeur')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Export Réseau Distributeur</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Générez un rapport complet du réseau d'un distributeur pour une période donnée
                    </p>
                </div>
                <a href="{{ route('admin.distributeurs.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>

        {{-- Formulaire moderne --}}
        <form action="{{ route('admin.network.export') }}" method="POST" id="networkExportForm">
            @csrf
            <div class="space-y-6">
                {{-- Étape 1: Sélection du distributeur --}}
                <div class="bg-white shadow-lg rounded-lg">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4 rounded-t-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-white/20 p-3 rounded-lg">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-xl font-semibold text-white">Étape 1 : Sélectionner un distributeur</h2>
                                <p class="text-blue-100 text-sm">Recherchez et sélectionnez le distributeur principal</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        {{-- Champ de recherche moderne --}}
                        <div class="relative">
                            <label for="distributeur_search" class="block text-sm font-medium text-gray-700 mb-2">
                                Rechercher un distributeur :
                            </label>
                            <div class="relative">
                                <input type="text"
                                       id="distributeur_search"
                                       placeholder="Tapez un matricule, nom ou prénom..."
                                       class="w-full px-4 py-3 pl-12 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       autocomplete="off">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                </div>
                                {{-- Spinner de chargement --}}
                                <div id="search_spinner" class="absolute inset-y-0 right-0 pr-3 flex items-center hidden">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>

                            {{-- Résultats de recherche --}}
                            <div id="search_results" class="absolute z-10 w-full mt-2 bg-white rounded-lg shadow-lg border border-gray-200 hidden max-h-60 overflow-y-auto">
                                <!-- Les résultats seront insérés ici par JavaScript -->
                            </div>
                        </div>

                        {{-- Distributeur sélectionné --}}
                        <div id="selected_distributeur" class="mt-4 p-4 bg-blue-50 rounded-lg hidden">
                            <div class="flex items-center justify-between">
                                <div>
                                    <p class="text-sm text-blue-600 font-medium">Distributeur sélectionné :</p>
                                    <p id="selected_text" class="text-lg font-semibold text-blue-900"></p>
                                </div>
                                <button type="button" onclick="resetDistributeur()" class="text-blue-600 hover:text-blue-800">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Input caché pour l'ID du distributeur --}}
                        <input type="hidden" name="distributeur_id" id="distributeur_id" value="{{ old('distributeur_id') }}">

                        @error('distributeur_id')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Étape 2: Sélection de la période --}}
                <div class="bg-white shadow-lg rounded-lg">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 rounded-t-lg">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-white/20 p-3 rounded-lg">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h2 class="text-xl font-semibold text-white">Étape 2 : Choisir la période</h2>
                                <p class="text-green-100 text-sm">Saisissez la période au format AAAA-MM</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6">
                        {{-- Champ de recherche de période avec autocomplétion --}}
                        <div class="relative" x-data="periodSearch()">
                            <label for="period-input" class="block text-sm font-medium text-gray-700 mb-2">
                                Période (format: AAAA-MM) :
                            </label>

                            <div class="relative">
                                <input type="text"
                                       id="period-input"
                                       name="period"
                                       x-model="periodValue"
                                       @input="searchPeriods"
                                       @focus="showSuggestions = true"
                                       @keydown.escape="showSuggestions = false"
                                       @keydown.arrow-down.prevent="navigateDown"
                                       @keydown.arrow-up.prevent="navigateUp"
                                       @keydown.enter.prevent="selectHighlighted"
                                       placeholder="Ex: 2024-07"
                                       pattern="\d{4}-\d{2}"
                                       value="{{ old('period') }}"
                                       class="w-full px-4 py-3 pl-12 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 transition-colors duration-200"
                                       autocomplete="off"
                                       required>

                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                    </svg>
                                </div>

                                {{-- Spinner de chargement --}}
                                <div x-show="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg class="animate-spin h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                </div>
                            </div>

                            {{-- Suggestions de périodes --}}
                            <div x-show="showSuggestions && suggestions.length > 0"
                                 x-transition
                                 class="absolute z-10 w-full mt-2 bg-white rounded-lg shadow-lg border border-gray-200 max-h-60 overflow-y-auto">
                                <template x-for="(period, index) in suggestions" :key="period.value">
                                    <button type="button"
                                            @click="selectPeriod(period)"
                                            class="w-full px-4 py-2 text-left hover:bg-blue-50 focus:bg-blue-50 focus:outline-none transition-colors duration-150">
                                        <div class="font-medium text-gray-900" x-text="period.value"></div>
                                        <div class="text-sm text-gray-500" x-text="period.label"></div>
                                    </button>
                                </template>
                            </div>

                            {{-- Message d'aide --}}
                            <p class="mt-2 text-sm text-gray-500">
                                Saisissez une période existante ou commencez à taper pour voir les suggestions
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Options et soumission --}}
                <div class="bg-white shadow-lg rounded-lg">
                    <div class="p-6">
                        {{-- Options d'export --}}
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">Options d'export</h3>
                            <div class="space-y-3">
                                <label class="flex items-center cursor-pointer group">
                                    <input type="checkbox" name="include_inactive" value="1" checked
                                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="ml-3 text-sm text-gray-700 group-hover:text-gray-900">
                                        Inclure les distributeurs inactifs
                                    </span>
                                </label>
                                <label class="flex items-center cursor-pointer group">
                                    <input type="checkbox" name="include_summary" value="1" checked
                                           class="w-4 h-4 text-blue-600 bg-gray-100 border-gray-300 rounded focus:ring-blue-500">
                                    <span class="ml-3 text-sm text-gray-700 group-hover:text-gray-900">
                                        Inclure le résumé statistique
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Bouton de soumission moderne --}}
                        <button type="submit"
                                id="submit_button"
                                disabled
                                class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-semibold py-3 px-6 rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 disabled:from-gray-400 disabled:to-gray-500 disabled:cursor-not-allowed flex items-center justify-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <span id="button_text">Générer l'aperçu du réseau</span>
                        </button>

                        {{-- Info sur la limite --}}
                        <p class="mt-3 text-center text-xs text-gray-500">
                            Maximum 5 000 distributeurs par export • Temps de traitement : 5-30 secondes
                        </p>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Scripts identiques, mais j'ajoute les sections Blade --}}
@endsection

@push('styles')
<style>
    /* Style pour les options de période sélectionnées */
    .period-option input:checked + div {
        border-color: rgb(59 130 246);
        background-color: rgb(239 246 255);
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Animation pour les résultats de recherche */
    #search_results > div {
        animation: slideIn 0.2s ease-out;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Le script reste identique, seule la méthode du formulaire a changé
    let searchTimeout;
    let selectedDistributeur = null;

    // Éléments DOM
    const searchInput = document.getElementById('distributeur_search');
    const searchResults = document.getElementById('search_results');
    const searchSpinner = document.getElementById('search_spinner');
    const selectedDiv = document.getElementById('selected_distributeur');
    const selectedText = document.getElementById('selected_text');
    const distributeurIdInput = document.getElementById('distributeur_id');
    const submitButton = document.getElementById('submit_button');
    const buttonText = document.getElementById('button_text');

    // Fonction pour vérifier la validité du formulaire
    function checkFormValidity() {
        const hasDistributeur = distributeurIdInput.value && distributeurIdInput.value !== '';
        const periodInput = document.getElementById('period-input');
        const hasPeriod = periodInput && periodInput.value && periodInput.value.match(/^\d{4}-\d{2}$/);

        if (hasDistributeur && hasPeriod) {
            submitButton.disabled = false;
        } else {
            submitButton.disabled = true;
        }
    }

    // Recherche de distributeurs avec debounce
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            searchSpinner.classList.add('hidden');
            return;
        }

        // Afficher le spinner
        searchSpinner.classList.remove('hidden');

        searchTimeout = setTimeout(() => {
            fetch(`{{ route('admin.network.search.distributeurs') }}?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
                .then(data => {
                    searchSpinner.classList.add('hidden');

                    if (data.length === 0) {
                        searchResults.innerHTML = `
                            <div class="p-6 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2 text-sm text-gray-500">Aucun distributeur trouvé</p>
                            </div>
                        `;
                    } else {
                        searchResults.innerHTML = data.map(dist => `
                            <div class="px-4 py-3 hover:bg-blue-50 cursor-pointer transition-colors duration-150 flex items-center justify-between"
                                 onclick="selectDistributeur('${dist.distributeur_id}', '${dist.nom_distributeur} ${dist.pnom_distributeur}')">
                                <div>
                                    <div class="font-medium text-gray-900">
                                        ${dist.nom_distributeur} ${dist.pnom_distributeur}
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Matricule: #${dist.distributeur_id} • Grade: ${dist.grade_display || '★'.repeat(dist.etoiles_id || 0)}
                                    </div>
                                </div>
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        `).join('');
                    }

                    searchResults.classList.remove('hidden');
                })
                .catch(error => {
                    console.error('Erreur de recherche:', error);
                    searchSpinner.classList.add('hidden');
                    searchResults.innerHTML = `
                        <div class="p-4 text-center text-red-600">
                            <p>Erreur lors de la recherche</p>
                        </div>
                    `;
                    searchResults.classList.remove('hidden');
                });
        }, 300);
    });

    // Sélectionner un distributeur
    function selectDistributeur(id, name) {
        selectedDistributeur = { id, name };
        distributeurIdInput.value = id;
        searchInput.value = '';
        searchResults.classList.add('hidden');

        selectedText.textContent = `#${id} - ${name}`;
        selectedDiv.classList.remove('hidden');

        checkFormValidity();
    }

    // Réinitialiser la sélection
    function resetDistributeur() {
        selectedDistributeur = null;
        distributeurIdInput.value = '';
        selectedDiv.classList.add('hidden');
        searchInput.value = '';

        checkFormValidity();
    }

    // Fonction Alpine.js pour la recherche de périodes
    function periodSearch() {
        return {
            periodValue: '{{ old("period") }}',
            suggestions: [],
            showSuggestions: false,
            loading: false,
            searchTimeout: null,

            init() {
                // Observer les changements de valeur pour valider le formulaire
                this.$watch('periodValue', (value) => {
                    console.log('Period value changed:', value);
                    // Appeler la fonction globale
                    window.checkFormValidity();
                });
            },

            searchPeriods() {
                clearTimeout(this.searchTimeout);

                // Ne chercher que si on a au moins 3 caractères
                if (this.periodValue.length < 3) {
                    this.suggestions = [];
                    this.showSuggestions = false;
                    return;
                }

                this.searchTimeout = setTimeout(() => {
                    this.loading = true;
                    this.showSuggestions = true;

                    console.log('Searching periods for:', this.periodValue);

                    fetch(`{{ route("admin.network.search.periods") }}?q=${encodeURIComponent(this.periodValue)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Network response was not ok');
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Periods found:', data);
                            this.suggestions = [];

                            // Aplatir les résultats groupés par année
                            Object.entries(data).forEach(([year, periods]) => {
                                periods.forEach(period => {
                                    this.suggestions.push(period);
                                });
                            });

                            this.loading = false;
                        })
                        .catch(error => {
                            console.error('Erreur lors de la recherche des périodes:', error);
                            this.loading = false;
                            this.suggestions = [];
                        });
                }, 300);
            },

            selectPeriod(period) {
                console.log('Period selected:', period);
                this.periodValue = period.value;
                this.showSuggestions = false;
                // Forcer la mise à jour de la validation
                this.$nextTick(() => {
                    window.checkFormValidity();
                });
            }
        }
    }

    // Cacher les résultats quand on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.classList.add('hidden');
        }
    });

    // Empêcher la soumission si le formulaire n'est pas valide
    document.getElementById('networkExportForm').addEventListener('submit', function(e) {
        if (submitButton.disabled) {
            e.preventDefault();
            return false;
        }

        // Afficher un loader sur le bouton
        submitButton.disabled = true;
        buttonText.textContent = 'Génération en cours...';
        submitButton.innerHTML = `
            <svg class="animate-spin h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Génération en cours...</span>
        `;
    });

    // Vérifier la validité au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        checkFormValidity();
    });
</script>
@endpush
