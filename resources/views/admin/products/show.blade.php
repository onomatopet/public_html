{{-- resources/views/admin/products/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Détails du produit')

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
                <span class="text-gray-700 font-medium">{{ $product->nom_produit }}</span>
            </nav>
        </div>

        {{-- Actions --}}
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">{{ $product->nom_produit }}</h1>
            <div class="flex space-x-3">
                <a href="{{ route('admin.products.edit', $product) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="inline-flex items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        Supprimer
                    </button>
                </form>
            </div>
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

        {{-- Contenu principal --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Informations principales --}}
            <div class="lg:col-span-2">
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                            </svg>
                            Détails du produit
                        </h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Code produit</dt>
                                <dd class="mt-1 text-lg font-medium text-gray-900">{{ $product->code_product }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nom du produit</dt>
                                <dd class="mt-1 text-lg font-medium text-gray-900">{{ $product->nom_produit }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Prix</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                        {{ number_format($product->prix_product, 0, ',', ' ') }} FCFA
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Points valeur</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                        {{ $product->pointValeur->numbers ?? 0 }} PV
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Catégorie</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    @if($product->category)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                            {{ $product->category->name }}
                                        </span>
                                    @else
                                        <span class="text-gray-500">Non catégorisé</span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date de création</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    @if($product->created_at)
                                        {{ $product->created_at->format('d/m/Y à H:i') }}
                                    @else
                                        <span class="text-gray-500">Non définie</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if($product->description)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <dt class="text-sm font-medium text-gray-500 mb-2">Description</dt>
                            <dd class="text-gray-700 leading-relaxed">{{ $product->description }}</dd>
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Statistiques et actions --}}
            <div class="space-y-6">
                {{-- Statistiques rapides --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Statistiques
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Nombre d'achats</span>
                                <span class="text-lg font-semibold text-gray-900">{{ $product->achats()->count() }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Quantité totale vendue</span>
                                <span class="text-lg font-semibold text-gray-900">{{ $product->achats()->sum('qt') }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Chiffre d'affaires</span>
                                <span class="text-lg font-semibold text-green-600">
                                    {{ number_format($product->achats()->sum('montant_total_ligne'), 0, ',', ' ') }} FCFA
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Actions rapides --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Actions rapides
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="{{ route('admin.products.edit', $product) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Modifier le produit
                        </a>

                        <a href="{{ route('admin.achats.index', ['search' => $product->code_product]) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                            </svg>
                            Voir les achats
                        </a>

                        <a href="{{ route('admin.products.index') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Retour à la liste
                        </a>
                    </div>
                </div>

                {{-- Informations supplémentaires --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-500 to-gray-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Informations système
                        </h3>
                    </div>
                    <div class="p-6">
                        <dl class="space-y-3">
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">ID Produit</dt>
                                <dd class="mt-1 text-sm text-gray-900">#{{ $product->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Créé le</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($product->created_at)
                                        {{ $product->created_at->format('d/m/Y à H:i') }}
                                    @else
                                        <span class="text-gray-500">Non défini</span>
                                    @endif
                                </dd>
                            </div>
                            @if($product->updated_at && $product->created_at && !$product->updated_at->equalTo($product->created_at))
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Modifié le</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $product->updated_at->format('d/m/Y à H:i') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
