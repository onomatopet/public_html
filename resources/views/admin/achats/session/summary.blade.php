@extends('layouts.admin')

@section('title', 'Session d\'Achats en Cours')

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
                <span class="text-gray-700 font-medium">Session en cours</span>
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
            <h1 class="text-3xl font-bold text-gray-900">Session d'Achats en Cours</h1>
            <p class="mt-2 text-lg text-gray-600">Ajoutez des produits et validez les achats pour {{ $session['distributeur_info'] }}</p>
        </div>

        {{-- Informations de la session --}}
        <div class="mb-8 bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 rounded-lg p-6 shadow-sm">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-blue-100 rounded-full p-3">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-blue-600 font-medium">Distributeur</p>
                        <p class="text-gray-900 font-semibold">{{ $session['distributeur_info'] }}</p>
                    </div>
                </div>

                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-blue-100 rounded-full p-3">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-blue-600 font-medium">Date des achats</p>
                        <p class="text-gray-900 font-semibold">{{ \Carbon\Carbon::parse($session['date'])->format('d/m/Y') }}</p>
                    </div>
                </div>

                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-blue-100 rounded-full p-3">
                            <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm text-blue-600 font-medium">Période</p>
                        <p class="text-gray-900 font-semibold">{{ $session['period'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Section principale (2/3) --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Panier actuel --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Panier en cours
                        </h2>
                    </div>

                    @if(empty($session['items']))
                        <div class="p-8 text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            <p class="mt-4 text-gray-500">Aucun produit dans le panier</p>
                            <p class="text-sm text-gray-400 mt-2">Utilisez le formulaire ci-dessous pour ajouter des produits</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full">
                                <thead class="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Produit
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Quantité
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Prix unitaire
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Montant
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Points
                                        </th>
                                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($session['items'] as $index => $item)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $item['product_name'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-center">
                                                {{ $item['quantity'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 text-right">
                                                {{ number_format($item['prix_unitaire'], 0, ',', ' ') }} F
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 text-right">
                                                {{ number_format($item['montant_total'], 0, ',', ' ') }} F
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600 text-right">
                                                {{ $item['points_total'] }} PV
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                <button type="button"
                                                        onclick="removeItem({{ $index }})"
                                                        class="text-red-600 hover:text-red-900 transition-colors duration-200">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                                <tfoot class="bg-gray-50">
                                    <tr>
                                        <th colspan="3" class="px-6 py-4 text-left text-sm font-bold text-gray-900 uppercase">
                                            Total général
                                        </th>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900 text-right">
                                            {{ number_format($session['totaux']['montant'], 0, ',', ' ') }} F
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-blue-600 text-right">
                                            {{ $session['totaux']['points'] }} PV
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
                        <div class="flex flex-col sm:flex-row gap-3">
                            <form action="{{ route('admin.achats.session.validate') }}" method="POST" class="inline-block" id="validate-form">
                                @csrf
                                <button type="submit"
                                        class="w-full sm:w-auto px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed {{ empty($session['items']) ? 'opacity-50 cursor-not-allowed' : '' }}"
                                        {{ empty($session['items']) ? 'disabled' : '' }}>
                                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                    Valider les achats
                                </button>
                            </form>

                            <form action="{{ route('admin.achats.session.cancel') }}" method="POST" class="inline-block">
                                @csrf
                                <button type="submit"
                                        onclick="return confirm('Êtes-vous sûr de vouloir annuler cette session ? Tous les produits seront perdus.')"
                                        class="w-full sm:w-auto px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition-colors duration-200 shadow-sm">
                                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                    Annuler la session
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                {{-- Formulaire d'ajout de produit --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                            </svg>
                            Ajouter un produit
                        </h2>
                    </div>
                    <div class="p-6">
                        <form action="{{ route('admin.achats.session.add-item') }}" method="POST" id="add-product-form">
                            @csrf
                            <div class="space-y-4">
                                {{-- Sélection du produit avec select moderne --}}
                                <div>
                                    <label for="product_id" class="block text-sm font-medium text-gray-700 mb-2">
                                        Produit <span class="text-red-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <select name="product_id"
                                                id="product_id"
                                                class="w-full px-4 py-3 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 appearance-none bg-white"
                                                required>
                                            <option value="">-- Sélectionnez un produit --</option>
                                            @foreach($products as $product)
                                                <option value="{{ $product->id }}"
                                                        data-price="{{ $product->prix_product }}"
                                                        data-points="{{ $product->pointValeur ? $product->pointValeur->numbers : 0 }}">
                                                    {{ $product->nom_produit }} - {{ $product->code_product }}
                                                    ({{ number_format($product->prix_product, 0, ',', ' ') }} F)
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>

                                {{-- Quantité --}}
                                <div>
                                    <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                        Quantité <span class="text-red-500">*</span>
                                    </label>
                                    <input type="number"
                                           name="quantity"
                                           id="quantity"
                                           min="1"
                                           value="1"
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                           required>
                                </div>

                                {{-- Aperçu --}}
                                <div id="product-preview" class="hidden">
                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                        <h4 class="text-sm font-medium text-gray-700 mb-2">Aperçu :</h4>
                                        <div class="space-y-1 text-sm">
                                            <p class="text-gray-900">Montant : <span class="font-bold" id="preview-amount">0</span> F</p>
                                            <p class="text-gray-900">Points : <span class="font-bold text-blue-600" id="preview-points">0</span> PV</p>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit"
                                        class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 shadow-sm">
                                    <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Ajouter au panier
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Section latérale (1/3) --}}
            <div class="lg:col-span-1">
                {{-- Résumé --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden sticky top-6">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white">Résumé de la session</h3>
                    </div>
                    <div class="p-6">
                        <dl class="space-y-4">
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Articles</dt>
                                <dd class="text-lg font-semibold text-gray-900" id="summary-items">
                                    {{ $session['totaux']['nb_items'] }}
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Montant total</dt>
                                <dd class="text-lg font-semibold text-gray-900" id="summary-amount">
                                    {{ number_format($session['totaux']['montant'], 0, ',', ' ') }} F
                                </dd>
                            </div>
                            <div class="flex items-center justify-between">
                                <dt class="text-gray-600">Points totaux</dt>
                                <dd class="text-lg font-semibold text-blue-600" id="summary-points">
                                    {{ $session['totaux']['points'] }} PV
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <div class="text-center">
                                <p class="text-sm text-gray-500 mb-2">Session créée le</p>
                                <p class="text-sm font-medium text-gray-900">
                                    {{ \Carbon\Carbon::parse($session['created_at'])->format('d/m/Y à H:i') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    /* Style pour le select moderne */
    select#product_id {
        background-image: none;
    }

    /* Animation pour l'aperçu */
    #product-preview {
        transition: all 0.3s ease-in-out;
    }

    #product-preview.hidden {
        opacity: 0;
        transform: translateY(-10px);
    }

    /* Style pour le select au focus */
    select#product_id:focus {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }

    /* Style personnalisé pour les options */
    select#product_id option {
        padding: 10px;
    }

    select#product_id option:checked {
        background-color: rgb(59 130 246);
        color: white;
    }
</style>
@endpush

@push('scripts')
<script>
    // Gestion de l'aperçu du produit
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const previewDiv = document.getElementById('product-preview');
    const previewAmount = document.getElementById('preview-amount');
    const previewPoints = document.getElementById('preview-points');

    function updatePreview() {
        const selectedOption = productSelect.options[productSelect.selectedIndex];
        const quantity = parseInt(quantityInput.value) || 0;

        if (productSelect.value && quantity > 0) {
            const price = parseFloat(selectedOption.dataset.price) || 0;
            const points = parseInt(selectedOption.dataset.points) || 0;

            const totalAmount = price * quantity;
            const totalPoints = points * quantity;

            previewAmount.textContent = new Intl.NumberFormat('fr-FR').format(totalAmount);
            previewPoints.textContent = totalPoints;

            previewDiv.classList.remove('hidden');
        } else {
            previewDiv.classList.add('hidden');
        }
    }

    productSelect.addEventListener('change', updatePreview);
    quantityInput.addEventListener('input', updatePreview);

    // Gestion de la suppression d'un item
    function removeItem(index) {
        if (confirm('Êtes-vous sûr de vouloir retirer ce produit ?')) {
            fetch('{{ route("admin.achats.session.remove-item") }}', {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ index: index })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.error || 'Erreur lors de la suppression');
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la suppression du produit');
            });
        }
    }

    // Gestion du formulaire d'ajout avec AJAX
    document.getElementById('add-product-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitButton = this.querySelector('button[type="submit"]');
        const originalText = submitButton.innerHTML;

        // Désactiver le bouton et afficher un loader
        submitButton.disabled = true;
        submitButton.innerHTML = `
            <svg class="animate-spin h-5 w-5 mr-2 inline" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Ajout en cours...
        `;

        fetch(this.action, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            },
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Réinitialiser le formulaire
                this.reset();
                updatePreview();

                // Recharger la page pour voir les changements
                window.location.reload();
            } else {
                alert(data.error || 'Erreur lors de l\'ajout du produit');
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur lors de l\'ajout du produit');
        })
        .finally(() => {
            // Réactiver le bouton
            submitButton.disabled = false;
            submitButton.innerHTML = originalText;
        });
    });

    // Confirmation avant validation
    document.getElementById('validate-form')?.addEventListener('submit', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir valider ces achats ? Cette action est irréversible.')) {
            e.preventDefault();
        }
    });
</script>
@endpush
