@extends('layouts.admin')

@section('title', 'Rapports')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Rapports et Analyses</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Générez des rapports détaillés sur les performances de votre réseau
                    </p>
                </div>
                <div class="flex space-x-3">
                    <select class="rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option>Période actuelle</option>
                        <option>Derniers 3 mois</option>
                        <option>Derniers 6 mois</option>
                        <option>Année en cours</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Grille des rapports disponibles --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {{-- Rapport des ventes --}}
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0 bg-blue-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-green-600">+12.5%</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Rapport des Ventes</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Analyse détaillée des ventes par distributeur, produit et période
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Dernière mise à jour: Aujourd'hui</span>
                        <button class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Générer →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Rapport des commissions --}}
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0 bg-green-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-green-600">+8.3%</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Rapport des Commissions</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Vue d'ensemble des commissions versées et à verser
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Dernière mise à jour: Hier</span>
                        <button class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Générer →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Rapport de croissance réseau --}}
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0 bg-purple-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-green-600">+15.2%</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Croissance du Réseau</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Évolution du nombre de distributeurs et structure du réseau
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Dernière mise à jour: Il y a 2 jours</span>
                        <button class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Générer →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Rapport des avancements --}}
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0 bg-yellow-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-red-600">-2.1%</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Avancements de Grade</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Analyse des progressions et avancements dans le réseau
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Dernière mise à jour: Il y a 3 jours</span>
                        <button class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Générer →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Rapport de performance --}}
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex-shrink-0 bg-red-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <span class="text-sm font-medium text-green-600">+5.7%</span>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Performance Globale</h3>
                    <p class="text-sm text-gray-600 mb-4">
                        KPIs et métriques de performance du système MLM
                    </p>
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Dernière mise à jour: Aujourd'hui</span>
                        <button class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Générer →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Rapport personnalisé --}}
            <div class="bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow duration-200 border-2 border-dashed border-gray-300">
                <div class="p-6">
                    <div class="flex items-center justify-center mb-4">
                        <div class="flex-shrink-0 bg-gray-100 p-3 rounded-lg">
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                        </div>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 text-center">Rapport Personnalisé</h3>
                    <p class="text-sm text-gray-600 mb-4 text-center">
                        Créez un rapport sur mesure selon vos besoins
                    </p>
                    <div class="flex justify-center">
                        <button class="text-sm font-medium text-blue-600 hover:text-blue-700">
                            Créer un rapport →
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section des rapports récents --}}
        <div class="mt-8">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Rapports récemment générés</h2>
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Type de rapport
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Période
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Généré par
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                Rapport des ventes
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Janvier 2024
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Admin
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                Il y a 2 heures
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="#" class="text-blue-600 hover:text-blue-900 mr-3">Voir</a>
                                <a href="#" class="text-gray-600 hover:text-gray-900">Télécharger</a>
                            </td>
                        </tr>
                        {{-- Ajoutez d'autres lignes selon vos besoins --}}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
