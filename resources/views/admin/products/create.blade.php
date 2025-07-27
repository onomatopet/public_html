{{-- resources/views/admin/products/create.blade.php --}}

@extends('layouts.admin')

@section('title', 'Ajouter un Produit')

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
                <a href="{{ route('admin.products.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Produits
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Nouveau Produit</span>
            </nav>
        </div>

        {{-- Titre principal --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Ajouter un nouveau produit</h1>
            <p class="mt-2 text-gray-600">Remplissez le formulaire ci-dessous pour créer un nouveau produit dans le catalogue.</p>
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
        <form method="POST" action="{{ route('admin.products.store') }}" class="space-y-8">
            @csrf

            {{-- Sections côte à côte sur grand écran --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Section Informations produit --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                            </svg>
                            Informations produit
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="space-y-6">
                            {{-- Code produit --}}
                            <div>
                                <label for="code_product" class="block text-sm font-medium text-gray-700 mb-2">
                                    Code produit <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="code_product"
                                    id="code_product"
                                    value="{{ old('code_product') }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('code_product') border-red-500 @enderror"
                                    placeholder="Ex: PROD-001"
                                    required>
                                @error('code_product')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Nom du produit --}}
                            <div>
                                <label for="nom_produit" class="block text-sm font-medium text-gray-700 mb-2">
                                    Nom du produit <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    name="nom_produit"
                                    id="nom_produit"
                                    value="{{ old('nom_produit') }}"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('nom_produit') border-red-500 @enderror"
                                    placeholder="Entrez le nom du produit"
                                    required>
                                @error('nom_produit')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Description --}}
                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                    Description
                                </label>
                                <textarea
                                    name="description"
                                    id="description"
                                    rows="4"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('description') border-red-500 @enderror"
                                    placeholder="Description détaillée du produit...">{{ old('description') }}</textarea>
                                @error('description')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Section Prix et classification --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden h-fit">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            Prix et classification
                        </h2>
                    </div>
                    <div class="p-6 space-y-6">
                        <div class="space-y-6">
                            {{-- Prix --}}
                            <div>
                                <label for="prix_product" class="block text-sm font-medium text-gray-700 mb-2">
                                    Prix du produit (FCFA) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        name="prix_product"
                                        id="prix_product"
                                        value="{{ old('prix_product') }}"
                                        step="0.01"
                                        min="0"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('prix_product') border-red-500 @enderror"
                                        placeholder="0.00"
                                        required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 text-sm">FCFA</span>
                                    </div>
                                </div>
                                @error('prix_product')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Catégorie --}}
                            <div>
                                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Catégorie
                                </label>
                                <select
                                    name="category_id"
                                    id="category_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('category_id') border-red-500 @enderror">
                                    <option value="">Choisir une catégorie (optionnel)</option>
                                    @foreach($categories as $id => $name)
                                        <option value="{{ $id }}" {{ old('category_id') == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            {{-- Points valeur --}}
                            <div>
                                <label for="pointvaleur_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Valeur en points (PV) <span class="text-red-500">*</span>
                                </label>
                                <select
                                    name="pointvaleur_id"
                                    id="pointvaleur_id"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200 @error('pointvaleur_id') border-red-500 @enderror"
                                    required>
                                    <option value="">Sélectionner les points</option>
                                    @foreach($pointValeurs as $id => $points)
                                        <option value="{{ $id }}" {{ old('pointvaleur_id') == $id ? 'selected' : '' }}>
                                            {{ $points }} PV
                                        </option>
                                    @endforeach
                                </select>
                                @error('pointvaleur_id')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-2 text-sm text-gray-500">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Les points valeur sont utilisés pour le calcul des commissions
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Boutons d'action --}}
            <div class="flex items-center justify-end space-x-4 pt-6 border-t border-gray-200">
                <a href="{{ route('admin.products.index') }}" class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    Annuler
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    Créer le produit
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
