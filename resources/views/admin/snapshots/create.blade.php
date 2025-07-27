{{-- resources/views/admin/snapshots/create.blade.php --}}

@extends('layouts.admin')

@section('title', 'Créer un Snapshot')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <nav class="flex items-center text-sm">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de Bord
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">Créer un Snapshot</span>
            </nav>
        </div>

        {{-- Messages de retour --}}
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{{ session('success') }}</p>
                    </div>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Formulaire principal --}}
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900">Créer un Snapshot des Niveaux</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Un snapshot capture l'état actuel de tous les distributeurs pour une période donnée.
                    Cette opération copie les données de <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">level_currents</code> 
                    vers <code class="text-xs bg-gray-100 px-1 py-0.5 rounded">level_current_histories</code>.
                </p>
            </div>

            <form action="{{ route('admin.snapshots.store') }}" method="POST" class="p-6">
                @csrf

                {{-- Sélection de la période --}}
                <div class="mb-6">
                    <label for="period" class="block text-sm font-medium text-gray-700 mb-2">
                        Période à archiver
                    </label>
                    <input type="month" 
                           id="period" 
                           name="period" 
                           value="{{ old('period', $suggestedPeriod ?? date('Y-m')) }}"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('period') border-red-300 @enderror"
                           required>
                    @error('period')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-sm text-gray-500">
                        Format : YYYY-MM (ex: {{ date('Y-m') }})
                    </p>
                </div>

                {{-- Option Force --}}
                <div class="mb-6">
                    <div class="flex items-start">
                        <div class="flex items-center h-5">
                            <input type="checkbox" 
                                   id="force" 
                                   name="force" 
                                   value="1"
                                   {{ old('force') ? 'checked' : '' }}
                                   class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                        </div>
                        <div class="ml-3">
                            <label for="force" class="text-sm font-medium text-gray-700">
                                Forcer la création (remplacer si existe)
                            </label>
                            <p class="text-sm text-gray-500">
                                Cochez cette option pour remplacer un snapshot existant pour cette période.
                                <span class="text-amber-600 font-medium">Attention : cette action supprimera l'ancien snapshot !</span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Informations importantes --}}
                <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">Points importants</h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <ul class="list-disc list-inside space-y-1">
                                    <li>Cette opération peut prendre plusieurs minutes selon le nombre de distributeurs</li>
                                    <li>Il est recommandé de créer un snapshot à la fin de chaque période</li>
                                    <li>Les snapshots sont utilisés pour l'historique et les rapports</li>
                                    <li>Assurez-vous que tous les calculs de la période sont terminés avant de créer le snapshot</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Boutons d'action --}}
                <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200">
                    <a href="{{ route('admin.dashboard') }}" 
                       class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Annuler
                    </a>
                    <button type="submit" 
                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V2"/>
                        </svg>
                        Créer le Snapshot
                    </button>
                </div>
            </form>
        </div>

        {{-- Section d'aide --}}
        <div class="mt-8 bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Aide et Documentation</h3>
            </div>
            <div class="p-6">
                <div class="prose text-gray-700 max-w-none">
                    <h4 class="text-base font-medium text-gray-900 mb-2">Qu'est-ce qu'un snapshot ?</h4>
                    <p class="text-sm mb-4">
                        Un snapshot est une capture de l'état de tous les distributeurs à un moment donné. 
                        Il permet de conserver un historique des performances et des niveaux pour chaque période.
                    </p>

                    <h4 class="text-base font-medium text-gray-900 mb-2">Quand créer un snapshot ?</h4>
                    <ul class="text-sm list-disc list-inside space-y-1 mb-4">
                        <li>À la fin de chaque mois, après tous les calculs</li>
                        <li>Avant d'effectuer des modifications majeures</li>
                        <li>Après la validation des avancements de grade</li>
                        <li>Avant la génération des bonus mensuels</li>
                    </ul>

                    <h4 class="text-base font-medium text-gray-900 mb-2">Processus recommandé</h4>
                    <ol class="text-sm list-decimal list-inside space-y-1">
                        <li>Terminer tous les calculs d'avancement pour la période</li>
                        <li>Vérifier et valider les données dans level_currents</li>
                        <li>Créer le snapshot pour archiver l'état</li>
                        <li>Procéder aux calculs de bonus si nécessaire</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection