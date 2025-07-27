{{-- resources/views/admin/processes/index.blade.php --}}

@extends('layouts.admin')

@section('title', 'Processus de calcul')

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
                <span class="text-gray-700 font-medium">Processus de calcul</span>
            </nav>
        </div>

        {{-- Titre et description --}}
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Processus de calcul</h1>
            <p class="mt-2 text-gray-600">Exécutez les processus d'avancement et de régularisation des grades</p>
        </div>

        {{-- Messages flash --}}
        @if(session('success'))
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
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
            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-lg">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700">{{ session('error') }}</p>
                    </div>
                </div>
            </div>
        @endif

        {{-- Grille des processus --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Processus d'avancement --}}
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                        </svg>
                        Processus d'avancement
                    </h2>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">
                        Lance le calcul des avancements de grade basé sur les performances et les critères définis.
                    </p>

                    <div class="space-y-4 mb-6">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">Calcul automatique des grades</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">Vérification des critères de qualification</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">Historique des modifications</span>
                        </div>
                    </div>

                    <form action="{{ route('admin.processes.advancements') }}" method="POST" onsubmit="return confirmAdvancement()">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="advancement_period" class="block text-sm font-medium text-gray-700 mb-2">
                                    Période de calcul
                                </label>
                                <input type="month"
                                       name="period"
                                       id="advancement_period"
                                       value="{{ date('Y-m') }}"
                                       max="{{ date('Y-m') }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200"
                                       required>
                            </div>

                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           name="simulate"
                                           value="1"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Mode simulation (aucune modification)</span>
                                </label>
                            </div>

                            <button type="submit"
                                    class="w-full px-6 py-3 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                                Lancer le processus d'avancement
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Processus de régularisation --}}
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-orange-500 to-orange-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Régularisation des grades
                    </h2>
                </div>
                <div class="p-6">
                    <p class="text-gray-600 mb-6">
                        Vérifie et corrige les incohérences dans les grades des distributeurs.
                    </p>

                    <div class="space-y-4 mb-6">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-orange-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">Détection des anomalies</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-orange-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">Correction automatique des grades</span>
                        </div>
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-orange-500 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            <span class="text-sm text-gray-700">Rapport détaillé des corrections</span>
                        </div>
                    </div>

                    <form action="{{ route('admin.processes.regularization') }}" method="POST" onsubmit="return confirmRegularization()">
                        @csrf
                        <div class="space-y-4">
                            <div>
                                <label for="regularization_period" class="block text-sm font-medium text-gray-700 mb-2">
                                    Période à régulariser
                                </label>
                                <input type="month"
                                       name="period"
                                       id="regularization_period"
                                       value="{{ date('Y-m') }}"
                                       max="{{ date('Y-m') }}"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-orange-500 focus:border-orange-500 transition-colors duration-200"
                                       required>
                            </div>

                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox"
                                           name="dry_run"
                                           value="1"
                                           checked
                                           class="h-4 w-4 text-orange-600 focus:ring-orange-500 border-gray-300 rounded">
                                    <span class="ml-2 text-sm text-gray-700">Mode test (afficher les changements sans les appliquer)</span>
                                </label>
                            </div>

                            <button type="submit"
                                    class="w-full px-6 py-3 bg-orange-600 text-white font-medium rounded-lg hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-colors duration-200">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                Lancer la régularisation
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Section historique --}}
        <div class="mt-8">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-gray-700 to-gray-800 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Historique des exécutions
                    </h2>
                </div>
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <p class="text-gray-600">Consultez l'historique des processus exécutés</p>
                        <a href="{{ route('admin.processes.history') }}"
                           class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            Voir l'historique complet
                        </a>
                    </div>

                    {{-- Dernières exécutions --}}
                    @if(isset($recentExecutions) && $recentExecutions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Exécuté par</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($recentExecutions as $execution)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $execution->type }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $execution->period }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $execution->user->name ?? 'Système' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $execution->created_at->format('d/m/Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $execution->status == 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ $execution->status }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-4">Aucune exécution récente</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function confirmAdvancement() {
        const simulate = document.querySelector('input[name="simulate"]').checked;
        const message = simulate
            ? "Voulez-vous lancer le processus d'avancement en mode simulation ?"
            : "⚠️ ATTENTION : Cette action va modifier les grades des distributeurs. Êtes-vous sûr de vouloir continuer ?";
        return confirm(message);
    }

    function confirmRegularization() {
        const dryRun = document.querySelector('input[name="dry_run"]').checked;
        const message = dryRun
            ? "Voulez-vous lancer la régularisation en mode test ?"
            : "⚠️ ATTENTION : Cette action va corriger les grades des distributeurs. Êtes-vous sûr de vouloir continuer ?";
        return confirm(message);
    }
</script>
@endpush
@endsection
