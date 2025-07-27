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

                                    {{-- Bouton d'action --}}
                                    @if($step['can_execute'] && $step['action'])
                                        <form action="{{ $step['action'] }}" method="{{ $step['action_method'] ?? 'POST' }}" class="ml-4">
                                            @csrf
                                            @foreach($step['action_data'] ?? [] as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <button type="submit"
                                                    class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                                {{ $step['button_text'] ?? 'Exécuter' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>

                                {{-- Statistiques --}}
                                @if($step['stats'])
                                    <div class="mt-3 grid grid-cols-2 gap-4 sm:grid-cols-4">
                                        @foreach($step['stats'] as $key => $value)
                                            @if(!in_array($key, ['progress']))
                                                <div>
                                                    <p class="text-sm font-medium text-gray-500">{{ ucfirst(str_replace('_', ' ', $key)) }}</p>
                                                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ is_numeric($value) ? number_format($value) : $value }}</p>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif

                                {{-- Informations de completion --}}
                                @if($step['completed'] && ($step['user'] || $step['date']))
                                    <div class="mt-3 flex items-center text-sm text-gray-500">
                                        @if($step['user'])
                                            <svg class="mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                            </svg>
                                            {{ $step['user']->name }}
                                        @endif
                                        @if($step['date'])
                                            <svg class="ml-4 mr-1.5 h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                            </svg>
                                            {{ $step['date']->format('d/m/Y H:i') }}
                                        @endif
                                    </div>
                                @endif
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

@push('scripts')
<script>
    // Auto-refresh si une étape est en cours
    @if($recentLogs->where('status', 'started')->isNotEmpty())
        setTimeout(function() {
            window.location.reload();
        }, 5000);
    @endif
</script>
@endpush
