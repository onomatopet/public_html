{{-- resources/views/admin/workflow/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Workflow de gestion - Période ' . $period)

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Workflow de gestion</h1>
                    <p class="mt-1 text-sm text-gray-600">Gérez étape par étape la clôture de la période</p>
                </div>
                <div class="flex items-center space-x-4">
                    {{-- Sélecteur de période --}}
                    <form action="{{ route('admin.workflow.index') }}" method="GET" class="flex items-center">
                        <label for="period" class="mr-2 text-sm font-medium text-gray-700">Période :</label>
                        <select name="period" id="period" onchange="this.form.submit()"
                                class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @foreach($allPeriods as $p)
                                <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>
                                    {{ $p }}
                                </option>
                            @endforeach
                        </select>
                    </form>

                    {{-- Statut de la période --}}
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium
                        @if($systemPeriod->status === 'open') bg-green-100 text-green-800
                        @elseif($systemPeriod->status === 'validation') bg-yellow-100 text-yellow-800
                        @else bg-gray-100 text-gray-800
                        @endif">
                        {{ ucfirst($systemPeriod->status) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Messages flash --}}
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-700">{!! session('success') !!}</p>
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

        {{-- Barre de progression --}}
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-medium text-gray-900">Progression</h2>
                <span class="text-sm font-medium text-gray-700">{{ $systemPeriod->getWorkflowProgress() }}%</span>
            </div>
            <div class="w-full bg-gray-200 rounded-full h-2.5">
                <div class="bg-indigo-600 h-2.5 rounded-full transition-all duration-500"
                     style="width: {{ $systemPeriod->getWorkflowProgress() }}%"></div>
            </div>
        </div>

        {{-- Étapes du workflow --}}
        <div class="space-y-4">
            @php
                $workflowStatus = $systemPeriod->getWorkflowStatus();
                $steps = [
                    [
                        'id' => 'period_opened',
                        'title' => 'Période ouverte',
                        'description' => 'La période est créée et prête à recevoir des achats',
                        'completed' => $workflowStatus['period_opened'],
                        'can_execute' => false,
                        'action' => null,
                        'stats' => null,
                        'user' => null,
                        'date' => $systemPeriod->opened_at
                    ],
                    [
                        'id' => 'validation_started',
                        'title' => 'Phase de validation',
                        'description' => 'La période est en cours de validation, aucun nouvel achat n\'est accepté',
                        'completed' => $workflowStatus['validation_started'],
                        'can_execute' => $systemPeriod->status === 'open',
                        'action' => route('admin.periods.start-validation'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Démarrer la validation',
                        'stats' => null,
                        'user' => null,
                        'date' => $systemPeriod->validation_started_at
                    ],
                    [
                        'id' => 'purchases_validated',
                        'title' => 'Validation des achats',
                        'description' => 'Vérifier et valider tous les achats de la période',
                        'completed' => $workflowStatus['purchases_validated'],
                        'can_execute' => $systemPeriod->canValidatePurchases(),
                        'action' => route('admin.workflow.validate-purchases'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Valider les achats',
                        'stats' => $stats['validation'] ?? null,
                        'user' => $systemPeriod->purchasesValidatedBy,
                        'date' => $systemPeriod->purchases_validated_at
                    ],
                    [
                        'id' => 'purchases_aggregated',
                        'title' => 'Agrégation des achats',
                        'description' => 'Calculer les totaux et propager dans la hiérarchie',
                        'completed' => $workflowStatus['purchases_aggregated'],
                        'can_execute' => $systemPeriod->canAggregatePurchases(),
                        'action' => route('admin.workflow.aggregate-purchases'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Agréger les achats',
                        'stats' => $stats['aggregation'] ?? null,
                        'user' => $systemPeriod->purchasesAggregatedBy,
                        'date' => $systemPeriod->purchases_aggregated_at
                    ],
                    [
                        'id' => 'advancements_calculated',
                        'title' => 'Calcul des avancements',
                        'description' => 'Calculer les nouveaux grades basés sur les performances',
                        'completed' => $workflowStatus['advancements_calculated'],
                        'can_execute' => $systemPeriod->canCalculateAdvancements(),
                        'action' => route('admin.workflow.calculate-advancements'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Calculer les avancements',
                        'stats' => $stats['advancement'] ?? null,
                        'user' => $systemPeriod->advancementsCalculatedBy,
                        'date' => $systemPeriod->advancements_calculated_at
                    ],
                    [
                        'id' => 'bonus_calculated',
                        'title' => 'Calcul des bonus',
                        'description' => 'Calculer les bonus directs et indirects selon les règles métier (quotas et épargne)',
                        'completed' => $workflowStatus['bonus_calculated'] ?? false,
                        'can_execute' => $systemPeriod->canCalculateBonus(),
                        'action' => route('admin.workflow.calculate-bonus'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Calculer les bonus',
                        'stats' => $stats['bonus'] ?? null,
                        'user' => $systemPeriod->bonusCalculatedBy ?? null,
                        'date' => $systemPeriod->bonus_calculated_at ?? null
                    ],
                    [
                        'id' => 'snapshot_created',
                        'title' => 'Création du snapshot',
                        'description' => 'Archiver l\'état actuel dans l\'historique',
                        'completed' => $workflowStatus['snapshot_created'],
                        'can_execute' => $systemPeriod->canCreateSnapshot(),
                        'action' => route('admin.workflow.create-snapshot'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Créer le snapshot',
                        'stats' => $stats['snapshot'] ?? null,
                        'user' => $systemPeriod->snapshotCreatedBy,
                        'date' => $systemPeriod->snapshot_created_at
                    ],
                    [
                        'id' => 'period_closed',
                        'title' => 'Clôture de la période',
                        'description' => 'Finaliser la période et créer la suivante',
                        'completed' => $workflowStatus['period_closed'],
                        'can_execute' => $systemPeriod->canClose(),
                        'action' => route('admin.workflow.close-period'),
                        'action_method' => 'POST',
                        'action_data' => ['period' => $period],
                        'button_text' => 'Clôturer la période',
                        'stats' => null,
                        'user' => $systemPeriod->closedBy,
                        'date' => $systemPeriod->closed_at
                    ],
                ];
            @endphp

            @foreach($steps as $index => $step)
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="p-6">
                    <div class="flex items-start">
                        {{-- Icône d'état --}}
                        <div class="flex-shrink-0">
                            @if($step['completed'])
                                <div class="h-10 w-10 rounded-full bg-green-100 flex items-center justify-center">
                                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                    </svg>
                                </div>
                            @elseif($step['can_execute'])
                                <div class="h-10 w-10 rounded-full bg-yellow-100 flex items-center justify-center animate-pulse">
                                    <span class="text-yellow-600 font-bold">{{ $index + 1 }}</span>
                                </div>
                            @else
                                <div class="h-10 w-10 rounded-full bg-gray-100 flex items-center justify-center">
                                    <span class="text-gray-400 font-bold">{{ $index + 1 }}</span>
                                </div>
                            @endif
                        </div>

                        {{-- Contenu --}}
                        <div class="ml-4 flex-1">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h3 class="text-lg font-medium text-gray-900">{{ $step['title'] }}</h3>
                                    <p class="mt-1 text-sm text-gray-500">{{ $step['description'] }}</p>
                                </div>

                                {{-- Boutons d'action --}}
                                <div class="ml-4 flex items-center space-x-2">
                                    {{-- Bouton d'exécution --}}
                                    @if($step['can_execute'] && $step['action'])
                                        <form action="{{ $step['action'] }}" method="{{ $step['action_method'] ?? 'POST' }}" class="inline">
                                            @csrf
                                            @foreach($step['action_data'] ?? [] as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach

                                            @if($step['id'] === 'bonus_calculated')
                                                <button type="submit"
                                                        onclick="return confirm('Voulez-vous lancer le calcul des bonus pour cette période ?')"
                                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                                    <svg class="mr-2 -ml-1 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                    </svg>
                                                    {{ $step['button_text'] ?? 'Exécuter' }}
                                                </button>
                                            @else
                                                <button type="submit"
                                                        class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                    {{ $step['button_text'] ?? 'Exécuter' }}
                                                </button>
                                            @endif
                                        </form>
                                    @endif

                                    {{-- Bouton de réinitialisation --}}
                                    @if($step['completed'] && in_array($step['id'], ['purchases_validated', 'purchases_aggregated', 'advancements_calculated', 'bonus_calculated', 'snapshot_created']) && $systemPeriod->status !== 'closed')
                                        <div x-data="{ showResetModal_{{ $step['id'] }}: false }">
                                            <button @click="showResetModal_{{ $step['id'] }} = true"
                                                    type="button"
                                                    class="inline-flex items-center p-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                    title="Réinitialiser cette étape">
                                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                                </svg>
                                            </button>

                                            {{-- Modal de confirmation --}}
                                            <div x-show="showResetModal_{{ $step['id'] }}"
                                                x-cloak
                                                class="fixed inset-0 z-50 overflow-y-auto"
                                                aria-labelledby="modal-title"
                                                role="dialog"
                                                aria-modal="true">
                                                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                                    <div x-show="showResetModal_{{ $step['id'] }}"
                                                        x-transition:enter="ease-out duration-300"
                                                        x-transition:enter-start="opacity-0"
                                                        x-transition:enter-end="opacity-100"
                                                        x-transition:leave="ease-in duration-200"
                                                        x-transition:leave-start="opacity-100"
                                                        x-transition:leave-end="opacity-0"
                                                        class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
                                                        @click="showResetModal_{{ $step['id'] }} = false"></div>

                                                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                                                    <div x-show="showResetModal_{{ $step['id'] }}"
                                                        x-transition:enter="ease-out duration-300"
                                                        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                                        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                                                        x-transition:leave="ease-in duration-200"
                                                        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                                                        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                                                        class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                                        <form action="{{ route('admin.workflow.reset-step') }}" method="POST">
                                                            @csrf
                                                            <input type="hidden" name="period" value="{{ $period }}">
                                                            <input type="hidden" name="step" value="{{ $step['id'] }}">

                                                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                                                <div class="sm:flex sm:items-start">
                                                                    <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                                                        <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                                                        </svg>
                                                                    </div>
                                                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                                                                            Réinitialiser : {{ $step['title'] }}
                                                                        </h3>
                                                                        <div class="mt-2">
                                                                            <p class="text-sm text-gray-500">
                                                                                Êtes-vous sûr de vouloir réinitialiser cette étape ? Cette action :
                                                                            </p>
                                                                            <ul class="mt-2 text-sm text-red-600 list-disc list-inside">
                                                                                @switch($step['id'])
                                                                                    @case('purchases_validated')
                                                                                        <li>Remettra tous les achats en statut "pending"</li>
                                                                                        <li>Réinitialisera toutes les étapes suivantes</li>
                                                                                        @break
                                                                                    @case('purchases_aggregated')
                                                                                        <li>Réinitialisera les cumuls (new_cumul, cumul_total)</li>
                                                                                        <li>Supprimera les avancements calculés</li>
                                                                                        <li>Supprimera les bonus calculés</li>
                                                                                        @break
                                                                                    @case('advancements_calculated')
                                                                                        <li>Supprimera l'historique des avancements</li>
                                                                                        <li>Réinitialisera les grades à leur valeur d'origine</li>
                                                                                        <li>Supprimera les bonus calculés</li>
                                                                                        @break
                                                                                    @case('bonus_calculated')
                                                                                        <li>Supprimera tous les bonus calculés</li>
                                                                                        <li>Devra recalculer les bonus</li>
                                                                                        @break
                                                                                    @case('snapshot_created')
                                                                                        <li>Supprimera le snapshot créé</li>
                                                                                        @break
                                                                                @endswitch
                                                                            </ul>
                                                                        </div>
                                                                        <div class="mt-3">
                                                                            <label for="reason_{{ $step['id'] }}" class="block text-sm font-medium text-gray-700">
                                                                                Raison de la réinitialisation (optionnel)
                                                                            </label>
                                                                            <textarea name="reason"
                                                                                    id="reason_{{ $step['id'] }}"
                                                                                    rows="2"
                                                                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                                                                    placeholder="Ex: Erreur dans les données, recalcul nécessaire..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                                                <button type="submit"
                                                                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                                                    Réinitialiser
                                                                </button>
                                                                <button type="button"
                                                                        @click="showResetModal_{{ $step['id'] }} = false"
                                                                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                                    Annuler
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>
                            </div>

                            {{-- Reste du contenu de l'étape (statistiques, etc.) --}}
                            {{-- ... (garder tout le reste du code existant pour les statistiques et informations) ... --}}
                        </div>
                    </div>
                </div>
            </div>
             @endforeach
        </div>

        {{-- Logs récents --}}
        @if($recentLogs->isNotEmpty())
            <div class="mt-8 bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Historique récent</h2>
                </div>
                <div class="divide-y divide-gray-200">
                    @foreach($recentLogs as $log)
                        <div class="px-6 py-4">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        bg-{{ $log->status_color }}-100 text-{{ $log->status_color }}-800">
                                        {{ $log->status_label }}
                                    </span>
                                    <span class="ml-3 text-sm text-gray-900">{{ $log->step_label }}</span>
                                    <span class="ml-2 text-sm text-gray-500">- {{ $log->action }}</span>
                                </div>
                                <div class="flex items-center text-sm text-gray-500">
                                    <span>{{ $log->user->name }}</span>
                                    <span class="mx-2">•</span>
                                    <span>{{ $log->created_at->format('d/m/Y H:i') }}</span>
                                    @if($log->duration_for_humans)
                                        <span class="mx-2">•</span>
                                        <span>{{ $log->duration_for_humans }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($log->error_message)
                                <p class="mt-2 text-sm text-red-600">{{ $log->error_message }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                <div class="px-6 py-3 bg-gray-50 text-right">
                    <a href="{{ route('admin.workflow.history', $period) }}"
                       class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                        Voir l'historique complet →
                    </a>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

{{-- Désactivé temporairement
@push('scripts')
<script>
    @if($recentLogs->where('status', 'started')->isNotEmpty())
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    @endif
</script>
@endpush
--}}
