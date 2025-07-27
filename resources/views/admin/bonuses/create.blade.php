{{-- resources/views/admin/bonuses/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Calculer les Bonus')

@section('content')
<div class="container-fluid max-w-6xl mx-auto px-4 py-8">
    {{-- En-tête --}}
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900">Calculer les Bonus</h1>
                <p class="mt-2 text-gray-600">Calculez les bonus pour un distributeur spécifique sur une période donnée</p>
            </div>
            <a href="{{ route('admin.bonuses.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Retour à la liste
            </a>
        </div>
    </div>

    {{-- Messages d'alerte --}}
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

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {{-- Formulaire principal --}}
        <div class="lg:col-span-2">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Paramètres du calcul
                    </h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.bonuses.store') }}" onsubmit="return confirmCalculation()">
                        @csrf

                        {{-- Sélection du distributeur --}}
                        <div class="mb-6">
                            <label for="distributeur_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Distributeur <span class="text-red-500">*</span>
                            </label>
                            <select name="distributeur_id" id="distributeur_id" class="form-select w-full" required>
                                <option value="">-- Sélectionnez un distributeur --</option>
                            </select>
                            <p class="mt-2 text-sm text-gray-500">
                                Recherchez et sélectionnez le distributeur pour lequel calculer le bonus.
                            </p>
                            @error('distributeur_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Sélection de la période --}}
                        <div class="mb-6">
                            <label for="period" class="block text-sm font-medium text-gray-700 mb-2">
                                Période à calculer <span class="text-red-500">*</span>
                            </label>
                            <select name="period" id="period" class="form-select w-full" required>
                                <option value="">-- Sélectionnez une période --</option>
                                @foreach($availablePeriods as $period)
                                    <option value="{{ $period }}" {{ $period == $currentPeriod ? 'selected' : '' }}>
                                        {{ $period }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-gray-500">
                                Seules les périodes avec des achats sont affichées.
                            </p>
                            @error('period')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Aperçu des informations --}}
                        <div id="distributor-preview" class="mb-6 p-4 bg-gray-50 rounded-lg" style="display: none;">
                            <h3 class="text-sm font-medium text-gray-700 mb-3">Informations du distributeur</h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600">Nom :</span>
                                    <span id="preview-name" class="font-medium text-gray-900"></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Matricule :</span>
                                    <span id="preview-matricule" class="font-medium text-gray-900"></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Grade actuel :</span>
                                    <span id="preview-grade" class="font-medium text-gray-900"></span>
                                </div>
                                <div>
                                    <span class="text-gray-600">Téléphone :</span>
                                    <span id="preview-phone" class="font-medium text-gray-900"></span>
                                </div>
                            </div>
                        </div>

                        {{-- Alerte si aucun achat --}}
                        @if(!$hasAchats)
                            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-lg">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-yellow-700">
                                            Aucun achat trouvé dans le système. Veuillez d'abord enregistrer des achats.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <button type="submit" 
                            class="w-full px-6 py-3 bg-green-600 text-white font-medium rounded-lg hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200 {{ !$hasAchats ? 'opacity-50 cursor-not-allowed' : '' }}"
                            {{ !$hasAchats ? 'disabled' : '' }}>
                            <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Calculer le bonus
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Section Informations --}}
        <div class="lg:col-span-1">
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-4">
                    <h2 class="text-xl font-semibold text-white flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Comment ça marche ?
                    </h2>
                </div>
                <div class="p-6 space-y-4">
                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 text-green-800 text-sm font-medium">1</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">Sélection du distributeur</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Recherchez et sélectionnez le distributeur pour lequel calculer le bonus.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 text-green-800 text-sm font-medium">2</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">Choix de la période</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Sélectionnez la période mensuelle pour le calcul du bonus.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 text-green-800 text-sm font-medium">3</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">Calcul automatique</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Le système calcule automatiquement les bonus directs, indirects et de leadership.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start">
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center justify-center h-8 w-8 rounded-full bg-green-100 text-green-800 text-sm font-medium">4</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-sm font-medium text-gray-900">Génération du reçu</h3>
                            <p class="mt-1 text-sm text-gray-500">
                                Un reçu PDF est généré automatiquement après le calcul.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Informations importantes --}}
            <div class="mt-6 bg-blue-50 rounded-lg p-6">
                <h3 class="text-sm font-medium text-blue-800 mb-2">Important</h3>
                <ul class="text-sm text-blue-700 space-y-2">
                    <li class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Le bonus ne peut être calculé qu'une seule fois par distributeur et par période.
                    </li>
                    <li class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        Le distributeur doit avoir des achats dans la période sélectionnée.
                    </li>
                    <li class="flex items-start">
                        <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        10% du bonus total est automatiquement mis en épargne.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        height: 42px;
        padding: 6px 12px;
        font-size: 0.875rem;
        line-height: 1.5;
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #111827;
        padding-left: 0;
        padding-right: 20px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px;
    }
    .select2-dropdown {
        border: 1px solid #d1d5db;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
    }
</style>
@endpush

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialiser Select2 pour la recherche de distributeur
    $('#distributeur_id').select2({
        placeholder: '-- Sélectionnez un distributeur --',
        allowClear: true,
        ajax: {
            url: '{{ route('admin.distributeurs.search') }}',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    q: params.term
                };
            },
            processResults: function (data) {
                return {
                    results: data.results
                };
            },
            cache: true
        },
        minimumInputLength: 2,
        language: {
            inputTooShort: function() {
                return 'Tapez au moins 2 caractères pour rechercher';
            },
            noResults: function() {
                return 'Aucun distributeur trouvé';
            },
            searching: function() {
                return 'Recherche en cours...';
            }
        }
    });

    // Afficher les informations du distributeur sélectionné
    $('#distributeur_id').on('select2:select', function (e) {
        var data = e.params.data;
        if (data) {
            $('#preview-name').text(data.nom_distributeur + ' ' + data.pnom_distributeur);
            $('#preview-matricule').text('#' + data.distributeur_id);
            $('#preview-phone').text(data.tel_distributeur || 'Non renseigné');
            $('#preview-grade').text('Grade ' + (data.etoiles_id || 'N/A'));
            $('#distributor-preview').fadeIn();
        }
    });

    $('#distributeur_id').on('select2:clear', function () {
        $('#distributor-preview').fadeOut();
    });
});

function confirmCalculation() {
    const distributeur = $('#distributeur_id').select2('data')[0];
    const period = $('#period').val();
    
    if (!distributeur || !period) {
        alert('Veuillez sélectionner un distributeur et une période.');
        return false;
    }
    
    return confirm(`Êtes-vous sûr de vouloir calculer le bonus pour ${distributeur.text} pour la période ${period} ?`);
}
</script>
@endpush
@endsection