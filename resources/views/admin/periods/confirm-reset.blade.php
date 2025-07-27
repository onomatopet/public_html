{{-- resources/views/admin/periods/confirm-reset.blade.php --}}
@extends('layouts.admin')

@section('title', 'Confirmer la réinitialisation - Période ' . $systemPeriod->period)

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Réinitialisation de la période</h1>
                    <p class="mt-1 text-sm text-gray-600">Mode : Réinitialisation douce</p>
                </div>
                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                    {{ $systemPeriod->period }}
                </span>
            </div>
        </div>

        {{-- Avertissement --}}
        <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">Attention - Action irréversible</h3>
                    <p class="mt-2 text-sm text-red-700">
                        Cette action va modifier les données de la période. Une sauvegarde sera créée automatiquement.
                    </p>
                </div>
            </div>
        </div>

        {{-- Mode de réinitialisation --}}
        <div class="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
            <h3 class="text-sm font-medium text-blue-800 mb-2">Mode : Réinitialisation douce</h3>
            <ul class="text-sm text-blue-700 space-y-1">
                <li>• Les cumuls seront récupérés depuis la période précédente ({{ $previousPeriod }})</li>
                <li>• Les grades et rangs seront restaurés depuis l'historique</li>
                <li>• Les achats seront conservés par défaut (sauf si vous cochez l'option)</li>
                <li>• Les bonus et avancements seront supprimés</li>
            </ul>
            @if(!$hasHistory)
            <p class="mt-2 text-sm text-orange-700 font-medium">
                ⚠️ Attention : Aucun historique trouvé pour {{ $previousPeriod }}. Les nouveaux distributeurs auront des valeurs par défaut.
            </p>
            @endif
        </div>

        {{-- Statistiques actuelles --}}
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Données actuelles de la période</h2>
            </div>
            <div class="px-6 py-4">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Achats</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $stats['achats']['total'] }} enregistrements
                            ({{ number_format($stats['achats']['montant_total'], 0, ',', ' ') }} FCFA)
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Distributeurs actifs</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $stats['distributeurs']['actifs'] }} / {{ $stats['distributeurs']['total'] }}
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Bonus</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $stats['bonuses']['total'] }} enregistrements
                            ({{ number_format($stats['bonuses']['montant_total'], 0, ',', ' ') }} FCFA)
                        </dd>
                    </div>
                    <div class="sm:col-span-1">
                        <dt class="text-sm font-medium text-gray-500">Avancements</dt>
                        <dd class="mt-1 text-sm text-gray-900">
                            {{ $stats['distributeurs']['avec_avancements'] }} distributeurs
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Formulaire de confirmation --}}
        <form action="{{ route('admin.periods.reset.reset') }}" method="POST" class="space-y-6">
            @csrf
            <input type="hidden" name="period" value="{{ $systemPeriod->period }}">

            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Confirmation requise</h2>
                </div>
                <div class="px-6 py-4 space-y-4">
                    {{-- Option suppression des achats --}}
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <div class="flex items-start">
                            <div class="flex items-center h-5">
                                <input id="delete_achats"
                                       name="delete_achats"
                                       type="checkbox"
                                       value="1"
                                       class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 rounded">
                            </div>
                            <div class="ml-3 text-sm">
                                <label for="delete_achats" class="font-medium text-gray-700">
                                    Supprimer les achats de la période
                                </label>
                                <p class="text-gray-500">
                                    Par défaut, les achats sont conservés. Cochez cette case uniquement si vous voulez les supprimer.
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Raison --}}
                    <div>
                        <label for="reason" class="block text-sm font-medium text-gray-700">
                            Raison de la réinitialisation
                        </label>
                        <textarea id="reason"
                                  name="reason"
                                  rows="3"
                                  required
                                  minlength="10"
                                  maxlength="500"
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                  placeholder="Expliquez pourquoi cette réinitialisation est nécessaire...">{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Confirmation --}}
                    <div>
                        <label for="confirmation" class="block text-sm font-medium text-gray-700">
                            Tapez <span class="font-mono font-bold text-red-600">REINITIALISER</span> pour confirmer
                        </label>
                        <input type="text"
                               id="confirmation"
                               name="confirmation"
                               required
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                               placeholder="REINITIALISER">
                        @error('confirmation')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.periods.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    Annuler
                </a>
                <button type="submit"
                        onclick="return confirm('Êtes-vous absolument sûr de vouloir réinitialiser cette période ?')"
                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Réinitialiser la période
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    // Avertissement supplémentaire si la case de suppression des achats est cochée
    document.getElementById('delete_achats').addEventListener('change', function() {
        if (this.checked) {
            alert('Attention ! Vous avez choisi de supprimer tous les achats. Cette action est irréversible.');
        }
    });
</script>
@endpush
@endsection
