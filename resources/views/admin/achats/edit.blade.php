{{-- resources/views/admin/achats/edit.blade.php --}}

@extends('layouts.admin')

@section('title', 'Modifier un Achat')

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
                <a href="{{ route('admin.achats.show', $achat) }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Achat #{{ $achat->id }}
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Modifier</span>
            </nav>
        </div>

        {{-- Titre principal --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Modifier l'achat #{{ $achat->id }}</h1>
            <p class="mt-2 text-gray-600">Modifiez les informations de l'achat effectué par {{ $achat->distributeur->pnom_distributeur }} {{ $achat->distributeur->nom_distributeur }}.</p>
        </div>

        {{-- Messages d'erreur --}}
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
        <form method="POST" action="{{ route('admin.achats.update', $achat) }}" class="space-y-8">
            @csrf
            @method('PUT')

            {{-- CHAMPS CACHÉS IMPORTANTS --}}
            <input type="hidden" name="distributeur_id" value="{{ $achat->distributeur_id }}">
            <input type="hidden" name="products_id" value="{{ $achat->products_id }}">

            {{-- Sections côte à côte sur grand écran --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Section Informations de base --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Informations de base
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        {{-- Période --}}
                        <div>
                            <label for="period" class="block text-sm font-medium text-gray-700 mb-2">
                                Période <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="month"
                                name="period"
                                id="period"
                                value="{{ old('period', $achat->period) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('period') border-red-500 @enderror"
                                required>
                            @error('period')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Format: YYYY-MM (ex: 2025-07)</p>
                        </div>

                        {{-- Date d'achat (NOUVEAU CHAMP) --}}
                        <div>
                            <label for="purchase_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Date d'achat <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="purchase_date"
                                id="purchase_date"
                                value="{{ old('purchase_date', isset($achat->purchase_date) ? $achat->purchase_date->format('Y-m-d') : $achat->created_at->format('Y-m-d')) }}"
                                max="{{ date('Y-m-d') }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('purchase_date') border-red-500 @enderror"
                                required>
                            @error('purchase_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            <p class="mt-1 text-xs text-gray-500">Date réelle de l'achat</p>
                        </div>

                        {{-- Distributeur (non modifiable) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Distributeur
                            </label>
                            <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                {{ $achat->distributeur->distributeur_id }} - {{ $achat->distributeur->pnom_distributeur }} {{ $achat->distributeur->nom_distributeur }}
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Le distributeur ne peut pas être modifié</p>
                        </div>

                        {{-- Achat en ligne --}}
                        <div>
                            <div class="flex items-center">
                                <input
                                    type="checkbox"
                                    name="online"
                                    id="online"
                                    value="1"
                                    {{ old('online', $achat->online) ? 'checked' : '' }}
                                    class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="online" class="ml-2 block text-sm text-gray-900">
                                    Achat effectué en ligne
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Cochez si l'achat a été effectué via la plateforme en ligne</p>
                        </div>

                        {{-- Informations de suivi --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-900 mb-2">Informations de suivi</h3>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p>Créé le : {{ $achat->created_at ? $achat->created_at->format('d/m/Y à H:i') : 'Non défini' }}</p>
                                @if($achat->updated_at && $achat->created_at && $achat->updated_at != $achat->created_at)
                                    <p>Modifié le : {{ $achat->updated_at->format('d/m/Y à H:i') }}</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section Produit et calculs --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                            </svg>
                            Produit et quantité
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        {{-- Produit actuel (non modifiable) --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Produit
                            </label>
                            <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                {{ $achat->product->nom_produit }} ({{ $achat->product->code_product }})
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Le produit ne peut pas être modifié</p>
                        </div>

                        {{-- Quantité --}}
                        <div>
                            <label for="qt" class="block text-sm font-medium text-gray-700 mb-2">
                                Quantité <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="number"
                                name="qt"
                                id="qt"
                                min="1"
                                value="{{ old('qt', $achat->qt) }}"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('qt') border-red-500 @enderror"
                                required>
                            @error('qt')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Prix et points (non modifiables) --}}
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Prix unitaire
                                </label>
                                <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                    {{ number_format($achat->prix_unitaire_achat, 0, ',', ' ') }} FCFA
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Points par unité
                                </label>
                                <div class="w-full px-4 py-2 bg-gray-50 border border-gray-300 rounded-lg text-gray-700">
                                    {{ number_format($achat->points_unitaire_achat, 2) }} PV
                                </div>
                            </div>
                        </div>

                        {{-- Aperçu des calculs --}}
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">Aperçu des calculs</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Prix unitaire :</span>
                                    <span class="font-medium">{{ number_format($achat->prix_unitaire_achat, 0, ',', ' ') }} FCFA</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Points par unité :</span>
                                    <span class="font-medium text-blue-600">{{ number_format($achat->points_unitaire_achat, 2) }} PV</span>
                                </div>
                                <div class="border-t pt-2 mt-2">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Total points :</span>
                                        <span class="font-medium text-blue-600" id="preview_points">{{ number_format($achat->qt * $achat->points_unitaire_achat, 2) }} PV</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600">Montant total :</span>
                                        <span class="font-semibold text-green-600 text-lg" id="preview_total">{{ number_format($achat->qt * $achat->prix_unitaire_achat, 0, ',', ' ') }} FCFA</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Boutons d'action --}}
            <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.achats.show', $achat) }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-2 bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Script pour le calcul en temps réel --}}
@push('scripts')
<script>
    // Calcul automatique lors du changement de quantité
    const quantityInput = document.getElementById('qt');
    const previewPoints = document.getElementById('preview_points');
    const previewTotal = document.getElementById('preview_total');

    const prixUnitaire = {{ $achat->prix_unitaire_achat }};
    const pointsUnitaire = {{ $achat->points_unitaire_achat }};

    function updateCalculation() {
        const quantity = parseInt(quantityInput.value) || 0;
        const total = prixUnitaire * quantity;
        const totalPoints = pointsUnitaire * quantity;

        previewTotal.textContent = new Intl.NumberFormat('fr-FR').format(total) + ' FCFA';
        previewPoints.textContent = totalPoints.toFixed(2) + ' PV';
    }

    quantityInput.addEventListener('input', updateCalculation);

    // Calcul initial
    updateCalculation();
</script>
@endpush
@endsection
