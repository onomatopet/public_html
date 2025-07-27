{{-- resources/views/admin/achats/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Détails de l\'achat')

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
                <span class="text-gray-700 font-medium">Achat #{{ $achat->id }}</span>
            </nav>
        </div>

        {{-- Actions --}}
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">Détails de l'achat #{{ $achat->id }}</h1>
            <div class="flex space-x-3">
                <a href="{{ route('admin.achats.edit', $achat) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                <form action="{{ route('admin.achats.destroy', $achat) }}" method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer cet achat ?')">
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
            <div class="lg:col-span-2 space-y-6">
                {{-- Détails de l'achat --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                            </svg>
                            Détails de l'achat
                        </h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">ID Achat</dt>
                                <dd class="mt-1 text-lg font-medium text-gray-900">#{{ $achat->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Période</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-purple-100 text-purple-800">
                                        {{ $achat->period }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Quantité</dt>
                                <dd class="mt-1 text-lg font-medium text-gray-900">{{ $achat->qt }} unité{{ $achat->qt > 1 ? 's' : '' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type d'achat</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    @if($achat->online)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 01-9 9m9-9H3m9 9v9a9 9 0 01-9-9m9-9c0 5-4 9-9 9s-9-4-9-9"/>
                                            </svg>
                                            En ligne
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                                            </svg>
                                            Physique
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date de création</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    {{ $achat->created_at ? $achat->created_at->format('d/m/Y à H:i') : 'Non définie' }}
                                </dd>
                            </div>
                            @if($achat->updated_at && $achat->created_at && $achat->updated_at != $achat->created_at)
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Dernière modification</dt>
                                <dd class="mt-1 text-lg text-gray-900">{{ $achat->updated_at->format('d/m/Y à H:i') }}</dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- Informations distributeur --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Distributeur
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0 h-16 w-16">
                                <div class="h-16 w-16 rounded-full bg-blue-100 flex items-center justify-center">
                                    <svg class="h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900">
                                    {{ $achat->distributeur->pnom_distributeur }} {{ $achat->distributeur->nom_distributeur }}
                                </h3>
                                <p class="text-sm text-gray-500">ID: {{ $achat->distributeur->distributeur_id }}</p>
                                @if($achat->distributeur->tel_distributeur)
                                    <p class="text-sm text-gray-500">Tél: {{ $achat->distributeur->tel_distributeur }}</p>
                                @endif
                            </div>
                            <div>
                                <a href="{{ route('admin.distributeurs.show', $achat->distributeur) }}"
                                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                                    Voir le profil
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Informations produit --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                            </svg>
                            Produit acheté
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="flex items-center space-x-4">
                            <div class="flex-shrink-0 h-16 w-16">
                                <div class="h-16 w-16 rounded-lg bg-purple-100 flex items-center justify-center">
                                    <svg class="h-8 w-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/>
                                    </svg>
                                </div>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-medium text-gray-900">{{ $achat->product->nom_produit }}</h3>
                                <p class="text-sm text-gray-500">Code: {{ $achat->product->code_product }}</p>
                                @if($achat->product->category)
                                    <p class="text-sm text-gray-500">Catégorie: {{ $achat->product->category->name }}</p>
                                @endif
                            </div>
                            <div>
                                <a href="{{ route('admin.products.show', $achat->product) }}"
                                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                                    Voir le produit
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar avec calculs et statistiques --}}
            <div class="space-y-6">
                {{-- Résumé financier --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            Résumé financier
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Prix unitaire</span>
                                <span class="text-lg font-semibold text-gray-900">{{ number_format($achat->prix_unitaire_achat, 0, ',', ' ') }} FCFA</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Quantité</span>
                                <span class="text-lg font-semibold text-gray-900">{{ $achat->qt }}</span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-base font-medium text-gray-900">Montant total</span>
                                    <span class="text-2xl font-bold text-green-600">
                                        {{ number_format($achat->montant_total_ligne, 0, ',', ' ') }} FCFA
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Points et commissions --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Points & Commissions
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Points par unité</span>
                                <span class="text-lg font-semibold text-blue-600">{{ number_format($achat->points_unitaire_achat, 2) }} PV</span>
                            </div>
                            <div class="border-t pt-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-base font-medium text-gray-900">Total points générés</span>
                                    <span class="text-2xl font-bold text-blue-600">
                                        {{ number_format($achat->qt * $achat->points_unitaire_achat, 2) }} PV
                                    </span>
                                </div>
                            </div>
                            <div class="bg-blue-50 rounded-lg p-3 mt-4">
                                <p class="text-sm text-blue-800">
                                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Ces points servent au calcul des bonus et commissions du distributeur.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Statistiques du distributeur pour cette période --}}
                @if($distributeurStats)
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Période {{ $achat->period }}
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Nombre d'achats</span>
                                <span class="text-lg font-semibold text-gray-900">{{ $distributeurStats->total_achats }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Total points</span>
                                <span class="text-lg font-semibold text-blue-600">{{ number_format($distributeurStats->total_points, 2) }} PV</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">CA total</span>
                                <span class="text-lg font-semibold text-green-600">{{ number_format($distributeurStats->total_montant, 0, ',', ' ') }} FCFA</span>
                            </div>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Actions rapides --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-gray-500 to-gray-600 px-6 py-4">
                        <h3 class="text-lg font-semibold text-white flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Actions rapides
                        </h3>
                    </div>
                    <div class="p-6 space-y-3">
                        <a href="{{ route('admin.achats.edit', $achat) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Modifier l'achat
                        </a>

                        <a href="{{ route('admin.distributeurs.show', $achat->distributeur) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Voir le distributeur
                        </a>

                        <a href="{{ route('admin.achats.index', ['period_filter' => $achat->period]) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded-lg hover:bg-purple-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                            Achats période {{ $achat->period }}
                        </a>

                        <a href="{{ route('admin.achats.index') }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Retour à la liste
                        </a>
                    </div>
                </div>

                {{-- Informations système --}}
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
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">ID Achat</dt>
                                <dd class="mt-1 text-sm text-gray-900">#{{ $achat->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Créé le</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $achat->created_at ? $achat->created_at->format('d/m/Y à H:i') : 'Non défini' }}
                                </dd>
                            </div>
                            @if($achat->updated_at && $achat->created_at && $achat->updated_at != $achat->created_at)
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Modifié le</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $achat->updated_at->format('d/m/Y à H:i') }}</dd>
                            </div>
                            @endif
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Distributeur ID</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $achat->distributeur_id }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Produit ID</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $achat->products_id }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
