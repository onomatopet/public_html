@extends('layouts.admin')

@section('title', 'Nouvelle Session d\'Achats')

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
                <a href="{{ route('admin.achats.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Achats
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Nouvelle Session</span>
            </nav>
        </div>

        {{-- Messages de session --}}
        @if(session('error'))
            <div class="mb-6">
                <div class="bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.414 1.414a1 1 0 101.414 1.414L10 11.414l1.414 1.414a1 1 0 001.414-1.414L11.414 10l1.414-1.414a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Titre et description --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Nouvelle Session d'Achats</h1>
            <p class="mt-2 text-lg text-gray-600">Sélectionnez un distributeur et une date pour démarrer une session de saisie des achats</p>
        </div>

        {{-- Affichage de la période actuelle (amélioré avec style cohérent) --}}
        @if($currentPeriod)
            <div class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-blue-100 rounded-full p-3">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-semibold text-blue-900">Période actuelle</h3>
                        @php
                            $periodDate = \Carbon\Carbon::createFromFormat('Y-m', $currentPeriod->period);
                            $dateDebut = $periodDate->copy()->startOfMonth();
                            $dateFin = $periodDate->copy()->endOfMonth();
                        @endphp
                        <p class="text-blue-700">{{ $currentPeriod->period }} (du {{ $dateDebut->format('d/m/Y') }} au {{ $dateFin->format('d/m/Y') }})</p>
                        <p class="text-sm text-blue-600 mt-1">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $currentPeriod->status === 'open' ? 'green' : 'yellow' }}-100 text-{{ $currentPeriod->status === 'open' ? 'green' : 'yellow' }}-800">
                                {{ ucfirst($currentPeriod->status) }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Formulaire principal (style cohérent avec create.blade.php) --}}
        <form action="{{ route('admin.achats.session.init') }}" method="POST" id="session-form">
            @csrf

            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Informations de la session
                    </h2>
                </div>

                <div class="p-6 space-y-6">
                    {{-- Sélection du distributeur --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Distributeur <span class="text-red-500">*</span>
                        </label>

                        {{-- Champ de recherche --}}
                        <div class="relative">
                            <input
                                type="text"
                                id="distributeur-search"
                                class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                placeholder="Rechercher par nom, prénom, matricule ou téléphone..."
                                autocomplete="off"
                            >
                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                </svg>
                            </div>

                            {{-- Résultats de recherche --}}
                            <div id="search-results" class="absolute top-full left-0 right-0 mt-1 bg-white rounded-lg shadow-lg border border-gray-200 max-h-80 overflow-y-auto z-50" style="display: none;">
                                <!-- Les résultats seront affichés ici -->
                            </div>
                        </div>

                        {{-- Distributeur sélectionné --}}
                        <div id="selected-distributeur" class="mt-3" style="display: none;">
                            <!-- Le distributeur sélectionné sera affiché ici -->
                        </div>

                        {{-- Input caché pour l'ID du distributeur --}}
                        <input type="hidden" name="distributeur_id" id="distributeur_id" value="{{ old('distributeur_id', session('last_distributeur_id')) }}" required>

                        @error('distributeur_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Date des achats --}}
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-2">
                            Date des achats <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input
                                type="date"
                                name="date"
                                id="date"
                                class="w-full px-4 py-2 pl-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('date') border-red-500 @enderror"
                                value="{{ old('date', date('Y-m-d')) }}"
                                max="{{ date('Y-m-d') }}"
                                required
                            >
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                            </div>
                        </div>
                        @error('date')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-sm text-gray-500">
                            Sélectionnez la date à laquelle les achats ont été effectués.
                            @if($currentPeriod)
                                <span class="font-medium text-gray-700">Période en cours : {{ $currentPeriod->period }}</span>
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <a href="{{ route('admin.achats.index') }}"
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200 shadow-sm">
                        Annuler
                    </a>
                    <button type="submit"
                            id="submit-button"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                        </svg>
                        Démarrer la session
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Animation pour les résultats de recherche */
    #search-results > div {
        animation: slideIn 0.15s ease-out;
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

    /* Style pour le distributeur sélectionné */
    #selected-distributeur > div {
        animation: fadeIn 0.2s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    /* Style pour le hover sur les résultats */
    #search-results > div:hover {
        background-color: rgb(249 250 251);
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Script de recherche autocomplete (même que dans create.blade.php mais avec la bonne route)
    const searchInput = document.getElementById('distributeur-search');
    const searchResults = document.getElementById('search-results');
    const selectedDistributeur = document.getElementById('selected-distributeur');
    const distributeurIdInput = document.getElementById('distributeur_id');
    const submitButton = document.getElementById('submit-button');
    let searchTimeout;

    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();

            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                searchResults.innerHTML = `
                    <div class="p-4 text-center">
                        <svg class="animate-spin h-8 w-8 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>
                `;
                searchResults.style.display = 'block';

                // Utiliser la route correcte qui existe dans le projet
                fetch(`{{ route('admin.distributeurs.search') }}?q=${encodeURIComponent(query)}`)
                .then(response => response.json())
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
                            const div = document.createElement('div');
                            div.className = 'px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 last:border-b-0 transition-colors duration-150';
                            div.innerHTML = `
                                <div class="font-medium text-gray-900">${distributeur.text}</div>
                                ${distributeur.tel_distributeur ?
                                    `<div class="text-sm text-gray-600">Tél: ${distributeur.tel_distributeur}</div>` : ''}
                            `;

                            div.addEventListener('click', function() {
                                selectDistributeur(distributeur);
                            });

                            searchResults.appendChild(div);
                        });
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    searchResults.innerHTML = `
                        <div class="p-4 text-center">
                            <div class="text-red-600">
                                <svg class="mx-auto h-12 w-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2 text-sm">Erreur lors de la recherche</p>
                            </div>
                        </div>
                    `;
                });
            }, 300);
        });
    }

    // Fonction pour sélectionner un distributeur
    function selectDistributeur(distributeur) {
        distributeurIdInput.value = distributeur.id;
        searchInput.value = '';
        searchResults.style.display = 'none';

        selectedDistributeur.innerHTML = `
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between transition-all duration-200 shadow-sm">
                <div class="flex items-center">
                    <div class="bg-blue-100 rounded-full p-2 mr-3">
                        <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-blue-600 font-medium">Distributeur sélectionné</p>
                        <p class="text-gray-900 font-semibold">${distributeur.text}</p>
                        ${distributeur.tel_distributeur ?
                            `<p class="text-sm text-gray-600">Tél: ${distributeur.tel_distributeur}</p>` : ''}
                    </div>
                </div>
                <button type="button" onclick="resetDistributeur()" class="text-gray-400 hover:text-gray-600 transition-colors duration-150">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        selectedDistributeur.style.display = 'block';

        // Activer le bouton submit
        validateForm();
    }

    // Fonction pour réinitialiser la sélection
    window.resetDistributeur = function() {
        distributeurIdInput.value = '';
        selectedDistributeur.style.display = 'none';
        searchInput.value = '';
        searchInput.focus();
        validateForm();
    }

    // Validation du formulaire
    function validateForm() {
        const hasDistributeur = distributeurIdInput.value && distributeurIdInput.value !== '';
        const hasDate = document.getElementById('date').value !== '';

        submitButton.disabled = !(hasDistributeur && hasDate);
    }

    // Valider le formulaire quand la date change
    document.getElementById('date').addEventListener('change', validateForm);

    // Fermer les résultats si on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // Si un distributeur était déjà sélectionné (last_distributeur_id)
    @if(old('distributeur_id', session('last_distributeur_id')))
        // Charger les infos du distributeur pré-sélectionné
        fetch(`{{ route('admin.distributeurs.search') }}?q=id:{{ old('distributeur_id', session('last_distributeur_id')) }}`)
            .then(response => response.json())
            .then(data => {
                if (data.results && data.results.length > 0) {
                    selectDistributeur(data.results[0]);
                }
            })
            .catch(error => {
                console.error('Erreur lors du chargement du distributeur pré-sélectionné:', error);
            });
    @endif

    // Validation initiale
    validateForm();
});
</script>
@endpush
