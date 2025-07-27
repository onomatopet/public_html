@extends('layouts.admin')

@section('title', 'Nouvel Achat')

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
                <span class="text-gray-700 font-medium">Nouvel Achat</span>
            </nav>
        </div>

        {{-- Titre principal --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Créer un nouvel achat</h1>
            <p class="mt-2 text-gray-600">Remplissez le formulaire ci-dessous pour enregistrer un nouvel achat dans le système.</p>
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
                        <h3 class="text-sm font-medium text-red-800">Erreurs de validation</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
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
        <form action="{{ route('admin.achats.store') }}" method="POST" id="achat-form">
            @csrf

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Section 1: Sélection du distributeur et période --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            1. Sélection du distributeur
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        {{-- Recherche distributeur --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Distributeur <span class="text-red-500">*</span>
                            </label>

                            {{-- Champ de recherche --}}
                            <div class="relative">
                                <input
                                    type="text"
                                    id="distributeur-search"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
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
                            <input type="hidden" name="distributeur_id" id="distributeur_id" value="{{ old('distributeur_id') }}" required>

                            @error('distributeur_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Période --}}
                        <div>
                            <label for="period" class="block text-sm font-medium text-gray-700 mb-2">
                                Période <span class="text-red-500">*</span>
                            </label>
                            <input type="month"
                                   name="period"
                                   id="period"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('period') border-red-500 @enderror"
                                   value="{{ old('period', date('Y-m')) }}"
                                   required>
                            @error('period')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Période de comptabilisation de l'achat</p>
                        </div>
                    </div>
                </div>

                {{-- Section 2: Détails du produit --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            2. Détails du produit
                        </h2>
                    </div>
                    <div class="p-6 space-y-4">
                        {{-- Sélection du produit --}}
                        <div>
                            <label for="products_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Produit <span class="text-red-500">*</span>
                            </label>
                            <select name="products_id" id="products_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('products_id') border-red-500 @enderror"
                                    required>
                                <option value="">-- Sélectionner un produit --</option>
                                @if(isset($products))
                                    @foreach($products as $product)
                                        <option value="{{ $product['id'] }}"
                                            data-price="{{ $product['price'] }}"
                                            data-pv="{{ $product['points'] }}"
                                            {{ old('products_id') == $product['id'] ? 'selected' : '' }}>
                                            {{ $product['name'] }} - {{ number_format($product['price'], 0, ',', ' ') }} FCFA
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            @error('products_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            {{-- Quantité --}}
                            <div>
                                <label for="qt" class="block text-sm font-medium text-gray-700 mb-2">
                                    Quantité <span class="text-red-500">*</span>
                                </label>
                                <input type="number"
                                       name="qt"
                                       id="qt"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('qt') border-red-500 @enderror"
                                       value="{{ old('qt', 1) }}"
                                       min="1"
                                       required>
                                @error('qt')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Date d'achat --}}
                            <div>
                                <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Date d'achat <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       name="purchase_date"
                                       id="purchase_date"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('purchase_date') border-red-500 @enderror"
                                       value="{{ old('purchase_date', date('Y-m-d')) }}"
                                       max="{{ date('Y-m-d') }}"
                                       required>
                                @error('purchase_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Date réelle de l'achat</p>
                            </div>
                        </div>

                        {{-- Champ caché pour online --}}
                        <input type="hidden" name="online" value="0">
                    </div>
                </div>
            </div>

            {{-- Section 3: Récapitulatif --}}
            <div class="bg-white shadow-lg rounded-lg overflow-hidden mt-6">
                <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        3. Récapitulatif
                    </h2>
                </div>
                    <div class="p-6">
                        <div class="bg-gray-50 rounded-lg p-6">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 mb-1">Prix unitaire</p>
                                    <p class="text-2xl font-bold text-gray-900">
                                        <span id="prix-unitaire">0</span> FCFA
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 mb-1">Montant total</p>
                                    <p class="text-2xl font-bold text-green-600">
                                        <span id="montant-total">0</span> FCFA
                                    </p>
                                </div>
                                <div class="text-center">
                                    <p class="text-sm text-gray-600 mb-1">Points valeur (PV)</p>
                                    <p class="text-2xl font-bold text-blue-600">
                                        <span id="pv-total">0</span> PV
                                    </p>
                                </div>
                            </div>
                        </div>

                        {{-- Notes optionnelles --}}
                        <div class="mt-6">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notes (optionnel)
                            </label>
                            <textarea name="notes"
                                      id="notes"
                                      rows="3"
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                      placeholder="Ajoutez des notes ou commentaires si nécessaire...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Boutons d'action --}}
                <div class="flex items-center justify-end space-x-4">
                    <a href="{{ route('admin.achats.index') }}"
                       class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        Annuler
                    </a>
                    <button type="submit"
                            class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        Enregistrer l'achat
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Meta CSRF Token --}}
<meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Éléments du DOM
    const searchInput = document.getElementById('distributeur-search');
    const searchResults = document.getElementById('search-results');
    const selectedDistributeur = document.getElementById('selected-distributeur');
    const distributeurIdInput = document.getElementById('distributeur_id');
    const productSelect = document.getElementById('products_id');
    const quantiteInput = document.getElementById('qt');
    let searchTimeout;

    // === GESTION DE LA RECHERCHE DE DISTRIBUTEUR ===
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();

            if (query.length < 2) {
                searchResults.style.display = 'none';
                return;
            }

            // Afficher un loader
            searchResults.innerHTML = `
                <div class="p-4 text-center">
                    <div class="inline-flex items-center">
                        <svg class="animate-spin h-5 w-5 mr-3 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-gray-600">Recherche en cours...</span>
                    </div>
                </div>
            `;
            searchResults.style.display = 'block';

            searchTimeout = setTimeout(() => {
                fetch(`{{ route('admin.distributeurs.search') }}?q=${encodeURIComponent(query)}`, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => {
                    if (!response.ok) throw new Error('Erreur réseau');
                    return response.json();
                })
                .then(data => {
                    searchResults.innerHTML = '';

                    const results = data.results || data;

                    if (!results || results.length === 0) {
                        searchResults.innerHTML = `
                            <div class="p-4 text-center text-gray-500">
                                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="mt-2">Aucun distributeur trouvé</p>
                            </div>
                        `;
                    } else {
                        results.forEach(distributeur => {
                            const div = document.createElement('div');
                            div.className = 'px-4 py-3 hover:bg-gray-50 cursor-pointer transition-colors duration-150 border-b border-gray-200 last:border-0';

                            const displayText = distributeur.text || `#${distributeur.distributeur_id} - ${distributeur.pnom_distributeur} ${distributeur.nom_distributeur}`;

                            div.innerHTML = `
                                <div class="font-medium text-gray-900">${displayText}</div>
                                ${distributeur.tel_distributeur ? `<div class="text-sm text-gray-600">Tél: ${distributeur.tel_distributeur}</div>` : ''}
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
        // Utiliser l'ID correct selon la structure du distributeur
        distributeurIdInput.value = distributeur.id || distributeur.distributeur_id;
        searchInput.value = '';
        searchResults.style.display = 'none';

        const displayText = distributeur.text || `#${distributeur.distributeur_id} - ${distributeur.pnom_distributeur} ${distributeur.nom_distributeur}`;

        selectedDistributeur.innerHTML = `
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="h-10 w-10 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <div>
                        <p class="text-sm text-blue-600 font-medium">Distributeur sélectionné</p>
                        <p class="text-gray-900 font-semibold">${displayText}</p>
                        ${distributeur.tel_distributeur ? `<p class="text-sm text-gray-600">Tél: ${distributeur.tel_distributeur}</p>` : ''}
                    </div>
                </div>
                <button type="button"
                        class="text-blue-600 hover:text-blue-800 transition-colors duration-200"
                        onclick="clearDistributeur()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        `;
        selectedDistributeur.style.display = 'block';
    }

    // Fonction pour effacer la sélection
    window.clearDistributeur = function() {
        distributeurIdInput.value = '';
        selectedDistributeur.innerHTML = '';
        selectedDistributeur.style.display = 'none';
        searchInput.value = '';
    };

    // Fermer les résultats si on clique ailleurs
    document.addEventListener('click', function(e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // === CALCUL DU MONTANT TOTAL ET PV ===
    function updateTotals() {
        if (!productSelect || !quantiteInput) return;

        const selectedOption = productSelect.options[productSelect.selectedIndex];
        if (!selectedOption || selectedOption.value === '') {
            document.getElementById('prix-unitaire').textContent = '0';
            document.getElementById('montant-total').textContent = '0';
            document.getElementById('pv-total').textContent = '0';
            return;
        }

        const prix = parseFloat(selectedOption.getAttribute('data-price')) || 0;
        const pv = parseFloat(selectedOption.getAttribute('data-pv')) || 0;
        const quantite = parseInt(quantiteInput.value) || 0;

        const montantTotal = prix * quantite;
        const pvTotal = pv * quantite;

        document.getElementById('prix-unitaire').textContent = prix.toLocaleString('fr-FR');
        document.getElementById('montant-total').textContent = montantTotal.toLocaleString('fr-FR');
        document.getElementById('pv-total').textContent = pvTotal.toLocaleString('fr-FR');
    }

    // Écouteurs d'événements pour le calcul
    if (productSelect) {
        productSelect.addEventListener('change', updateTotals);
    }

    if (quantiteInput) {
        quantiteInput.addEventListener('input', updateTotals);
    }

    // Calcul initial
    updateTotals();

    // === VALIDATION DU FORMULAIRE ===
    const form = document.getElementById('achat-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!distributeurIdInput.value) {
                e.preventDefault();
                alert('Veuillez sélectionner un distributeur');
                searchInput.focus();
                return false;
            }

            if (!productSelect.value) {
                e.preventDefault();
                alert('Veuillez sélectionner un produit');
                productSelect.focus();
                return false;
            }
        });
    }
});
</script>
@endpush
