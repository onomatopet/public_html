{{-- resources/views/admin/distributeurs/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Détails du distributeur')

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
                <span class="text-gray-700 font-medium">{{ $distributeur->distributeur_id }}</span>
            </nav>
        </div>

        {{-- Actions --}}
        <div class="mb-6 flex justify-between items-center">
            <h1 class="text-3xl font-bold text-gray-900">{{ $distributeur->pnom_distributeur }} {{ $distributeur->nom_distributeur }}</h1>
            <div class="flex space-x-3">
                <a href="{{ route('admin.distributeurs.edit', $distributeur) }}"
                   class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Modifier
                </a>
                <form action="{{ route('admin.distributeurs.destroy', $distributeur) }}" method="POST" class="inline" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce distributeur ?')">
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
                {{-- Informations personnelles --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                            Informations personnelles
                        </h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Matricule</dt>
                                <dd class="mt-1 text-lg font-medium text-blue-600">#{{ $distributeur->distributeur_id }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Nom complet</dt>
                                <dd class="mt-1 text-lg font-medium text-gray-900">{{ $distributeur->pnom_distributeur }} {{ $distributeur->nom_distributeur }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Téléphone</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    {{ $distributeur->tel_distributeur ?? 'Non renseigné' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Email</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    {{ $distributeur->email_distributeur ?? 'Non renseigné' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Adresse</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    {{ $distributeur->adress_distributeur ?? 'Non renseignée' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Date d'inscription</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    @if($distributeur->created_at)
                                        {{ $distributeur->created_at->format('d/m/Y') }}
                                    @else
                                        <span class="text-gray-500">Non définie</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Hiérarchie MLM --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                            Hiérarchie MLM
                        </h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Distributeur parent</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    @if($distributeur->parent)
                                        <a href="{{ route('admin.distributeurs.show', $distributeur->parent) }}" class="text-blue-600 hover:text-blue-800 font-medium">
                                            {{ $distributeur->parent->pnom_distributeur }} {{ $distributeur->parent->nom_distributeur }}
                                            <span class="text-sm text-gray-500">(#{{ $distributeur->parent->distributeur_id }})</span>
                                        </a>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            Distributeur racine
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Niveau d'étoiles</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    <div class="flex items-center">
                                        @for($i = 1; $i <= ($distributeur->etoiles_id ?? 1); $i++)
                                            <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                            </svg>
                                        @endfor
                                        <span class="ml-2 text-sm text-gray-600">{{ $distributeur->etoiles_id ?? 1 }} étoile{{ ($distributeur->etoiles_id ?? 1) > 1 ? 's' : '' }}</span>
                                    </div>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Enfants directs</dt>
                                <dd class="mt-1 text-lg font-semibold text-gray-900">{{ $statistics['total_children'] ?? 0 }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Niveau hiérarchique</dt>
                                <dd class="mt-1 text-lg text-gray-900">
                                    {{ $distributeur->parent ? 'Niveau 2+' : 'Niveau 1 (Racine)' }}
                                </dd>
                            </div>
                        </dl>

                        {{-- Liste des enfants directs si il y en a --}}
                        @if($distributeur->children->count() > 0)
                        <div class="mt-6 pt-6 border-t border-gray-200">
                            <dt class="text-sm font-medium text-gray-500 mb-3">Enfants directs ({{ $distributeur->children->count() }})</dt>
                            <div class="grid grid-cols-1 gap-2">
                                @foreach($distributeur->children->take(5) as $child)
                                <div class="flex items-center justify-between p-2 bg-gray-50 rounded-lg">
                                    <div>
                                        <a href="{{ route('admin.distributeurs.show', $child) }}" class="text-sm font-medium text-blue-600 hover:text-blue-800">
                                            {{ $child->pnom_distributeur }} {{ $child->nom_distributeur }}
                                        </a>
                                        <p class="text-xs text-gray-500">#{{ $child->distributeur_id }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs text-gray-500">{{ $child->etoiles_id ?? 1 }} ⭐</p>
                                    </div>
                                </div>
                                @endforeach
                                @if($distributeur->children->count() > 5)
                                <div class="text-center py-2">
                                    <span class="text-sm text-gray-500">... et {{ $distributeur->children->count() - 5 }} autres</span>
                                </div>
                                @endif
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Derniers achats --}}
                @if($distributeur->achats->count() > 0)
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                        <h2 class="text-xl font-semibold text-white flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                            </svg>
                            Derniers achats
                        </h2>
                    </div>
                    <div class="p-6">
                        <div class="space-y-3">
                            @foreach($distributeur->achats->take(5) as $achat)
                            <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                <div class="flex-1">
                                    <div class="flex items-center">
                                        <div class="font-medium text-gray-900">{{ $achat->product->nom_produit ?? 'Produit supprimé' }}</div>
                                        <span class="ml-2 inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                            {{ $achat->period }}
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        Qté: {{ $achat->qt }} •
                                        {{ number_format($achat->points_unitaire_achat * $achat->qt, 2) }} PV
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-semibold text-green-600">{{ number_format($achat->montant_total_ligne, 0, ',', ' ') }} FCFA</div>
                                    <div class="text-xs text-gray-500">
                                        @if($achat->created_at)
                                            {{ $achat->created_at->format('d/m/Y') }}
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @if($distributeur->achats->count() > 5)
                        <div class="mt-4 text-center">
                            <a href="{{ route('admin.achats.index', ['search' => $distributeur->distributeur_id]) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                Voir tous les achats ({{ $distributeur->achats->count() }})
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar statistiques et actions --}}
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
                                <span class="text-sm text-gray-500">Total achats</span>
                                <span class="text-lg font-semibold text-gray-900">{{ $statistics['total_achats'] ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Enfants directs</span>
                                <span class="text-lg font-semibold text-blue-600">{{ $statistics['total_children'] ?? 0 }}</span>
                            </div>
                            @if(isset($statistics['last_achat']) && $statistics['last_achat'])
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Dernier achat</span>
                                <span class="text-sm font-medium text-gray-900">
                                    @if($statistics['last_achat']->created_at)
                                        {{ $statistics['last_achat']->created_at->format('d/m/Y') }}
                                    @else
                                        Non défini
                                    @endif
                                </span>
                            </div>
                            @endif
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
                        <a href="{{ route('admin.distributeurs.edit', $distributeur) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Modifier les informations
                        </a>

                        <a href="{{ route('admin.achats.index', ['search' => $distributeur->distributeur_id]) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/>
                            </svg>
                            Voir les achats
                        </a>

                        <a href="{{ route('admin.bonuses.index', ['search' => $distributeur->distributeur_id]) }}"
                           class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                            </svg>
                            Voir les bonus
                        </a>

                        <a href="{{ route('admin.distributeurs.index') }}"
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
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">ID Système</dt>
                                <dd class="mt-1 text-sm text-gray-900">#{{ $distributeur->id }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Créé le</dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    @if($distributeur->created_at)
                                        {{ $distributeur->created_at->format('d/m/Y à H:i') }}
                                    @else
                                        <span class="text-gray-500">Non défini</span>
                                    @endif
                                </dd>
                            </div>
                            @if($distributeur->updated_at && $distributeur->created_at && !$distributeur->updated_at->equalTo($distributeur->created_at))
                            <div>
                                <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Modifié le</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $distributeur->updated_at->format('d/m/Y à H:i') }}</dd>
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
