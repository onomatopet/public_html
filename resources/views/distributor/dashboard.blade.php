{{-- resources/views/distributor/dashboard.blade.php --}}
@extends('layouts.app')

@section('title', 'Mon Espace Distributeur')

@section('content')
<div class="min-h-screen bg-gray-50 py-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- En-tête -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Bienvenue, {{ $distributeur->nom_distributeur }}</h1>
                        <p class="mt-1 text-sm text-gray-600">Matricule: {{ $distributeur->distributeur_id }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">Grade actuel</p>
                        <p class="text-2xl font-bold text-blue-600">{{ $stats['grade_actuel'] }} ⭐</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiques principales -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Cumul Personnel -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Cumul Personnel</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format($stats['cumul_personnel']) }} pts</p>
                    </div>
                </div>
            </div>

            <!-- Cumul Collectif -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Cumul Collectif</p>
                        <p class="text-xl font-bold text-gray-900">{{ number_format($stats['cumul_collectif']) }} pts</p>
                    </div>
                </div>
            </div>

            <!-- Équipe Directe -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Équipe Directe</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['equipe_directe'] }}</p>
                    </div>
                </div>
            </div>

            <!-- Total Équipe -->
            <div class="bg-white rounded-lg shadow p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-indigo-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.217 10.907a2.25 2.25 0 100 2.186m0-2.186c.18.324.283.696.283 1.093s-.103.77-.283 1.093m0-2.186l9.566-5.314m-9.566 7.5l9.566 5.314m0 0a2.25 2.25 0 103.935 2.186 2.25 2.25 0 00-3.935-2.186zm0-12.814a2.25 2.25 0 103.933-2.185 2.25 2.25 0 00-3.933 2.185z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-500">Total Réseau</p>
                        <p class="text-xl font-bold text-gray-900">{{ $stats['total_equipe'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sections principales -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Derniers achats -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900">Derniers achats</h2>
                    <a href="{{ route('distributor.purchases.index') }}" class="text-sm text-blue-600 hover:text-blue-500">
                        Voir tout →
                    </a>
                </div>
                <div class="p-6">
                    @if($recentPurchases->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentPurchases as $purchase)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $purchase->product->nom_product ?? 'Produit' }}</p>
                                        <p class="text-xs text-gray-500">{{ $purchase->created_at->format('d/m/Y') }}</p>
                                    </div>
                                    <span class="text-sm font-semibold text-gray-900">{{ $purchase->point_achat }} pts</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 text-center py-4">Aucun achat récent</p>
                    @endif
                </div>
            </div>

            <!-- Derniers bonus -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                    <h2 class="text-lg font-medium text-gray-900">Derniers bonus</h2>
                    <a href="{{ route('distributor.bonuses.index') }}" class="text-sm text-blue-600 hover:text-blue-500">
                        Voir tout →
                    </a>
                </div>
                <div class="p-6">
                    @if($recentBonuses->count() > 0)
                        <div class="space-y-4">
                            @foreach($recentBonuses as $bonus)
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $bonus->type }}</p>
                                        <p class="text-xs text-gray-500">{{ $bonus->created_at->format('d/m/Y') }}</p>
                                    </div>
                                    <span class="text-sm font-semibold text-green-600">{{ number_format($bonus->montant, 2) }} €</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-sm text-gray-500 text-center py-4">Aucun bonus récent</p>
                    @endif
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="mt-6 bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Actions rapides</h2>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <a href="{{ route('distributor.profile.show') }}" class="text-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="h-8 w-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span class="text-sm text-gray-600">Mon Profil</span>
                    </a>

                    <a href="{{ route('distributor.network.index') }}" class="text-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="h-8 w-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-sm text-gray-600">Mon Réseau</span>
                    </a>

                    <a href="{{ route('distributor.purchases.index') }}" class="text-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="h-8 w-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        <span class="text-sm text-gray-600">Mes Achats</span>
                    </a>

                    <a href="{{ route('distributor.bonuses.index') }}" class="text-center p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <svg class="h-8 w-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm text-gray-600">Mes Bonus</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
