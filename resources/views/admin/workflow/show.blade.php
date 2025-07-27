@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    {{-- En-tête --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">
                    Workflow Période {{ $period->period }}
                </h1>
                <p class="text-gray-600 mt-2">
                    Statut actuel:
                    <span class="px-3 py-1 rounded-full text-sm font-semibold
                        @if($period->status === 'active') bg-green-100 text-green-800
                        @elseif($period->status === 'closed') bg-gray-100 text-gray-800
                        @else bg-yellow-100 text-yellow-800
                        @endif">
                        {{ ucfirst($period->status) }}
                    </span>
                </p>
            </div>
            <a href="{{ route('workflow.index') }}"
               class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                Retour aux périodes
            </a>
        </div>
    </div>

    {{-- Barre de progression --}}
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Progression du workflow</h2>
        <div class="w-full bg-gray-200 rounded-full h-4 mb-2">
            <div class="bg-blue-600 h-4 rounded-full transition-all duration-500"
                 style="width: {{ $workflowStatus['progress']['percentage'] }}%"></div>
        </div>
        <p class="text-sm text-gray-600 text-center">
            {{ $workflowStatus['progress']['completed'] }} / {{ $workflowStatus['progress']['total'] }} étapes complétées
        </p>
    </div>

    {{-- Étapes du workflow --}}
    <div class="space-y-4">
        @foreach($workflowStatus['steps'] as $key => $step)
            <div class="bg-white rounded-lg shadow-md p-6
                {{ $step['completed'] ? 'border-l-4 border-green-500' :
                   ($step['can_execute'] ? 'border-l-4 border-yellow-500' : 'border-l-4 border-gray-300') }}">

                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        {{-- Icône de statut --}}
                        <div class="flex-shrink-0">
                            @if($step['completed'])
                                <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                </div>
                            @elseif($step['can_execute'])
                                <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                            @else
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                            @endif
                        </div>

                        {{-- Informations de l'étape --}}
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">{{ $step['label'] }}</h3>

                            {{-- Statistiques si disponibles --}}
                            @if(isset($step['stats']))
                                <div class="mt-2 text-sm text-gray-600">
                                    @if($key === 'purchases_validated')
                                        <span>Total: {{ $step['stats']['total'] }}</span> |
                                        <span class="text-green-600">Validés: {{ $step['stats']['validated'] }}</span> |
                                        <span class="text-yellow-600">En attente: {{ $step['stats']['pending'] }}</span> |
                                        <span class="text-red-600">Rejetés: {{ $step['stats']['rejected'] }}</span>
                                    @elseif($key === 'purchases_aggregated')
                                        <span>Distributeurs impactés: {{ $step['stats']['distributeurs_impactes'] }}</span> |
                                        <span>Total points: {{ $step['stats']['total_points'] }}</span>
                                    @elseif($key === 'advancements_calculated')
                                        <span>Total changements: {{ $step['stats']['total'] }}</span> |
                                        <span class="text-green-600">Promotions: {{ $step['stats']['promotions'] }}</span> |
                                        <span class="text-red-600">Démotions: {{ $step['stats']['demotions'] }}</span>
                                    @endif
                                </div>
                            @endif

                            {{-- Informations de complétion --}}
                            @if($step['completed'])
                                <div class="mt-1 text-xs text-gray-500">
                                    @php
                                        $completedAtField = $key . '_at';
                                        $completedByField = $key . '_by';
                                    @endphp
                                    @if($period->$completedAtField)
                                        Complété le {{ $period->$completedAtField->format('d/m/Y à H:i') }}
                                        @if($period->$completedByField && $period->completedByUser($completedByField))
                                            par {{ $period->completedByUser($completedByField)->name }}
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div>
                        @if($step['can_execute'])
                            <form action="{{ route('workflow.' . str_replace('_', '-', $key), $period->id) }}"
                                  method="POST"
                                  class="inline"
                                  onsubmit="return confirm('Êtes-vous sûr de vouloir exécuter cette étape ?');">
                                @csrf
                                <button type="submit"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 transition-colors">
                                    Exécuter
                                </button>
                            </form>
                        @elseif($step['completed'])
                            <span class="text-green-600 font-semibold">✓ Complété</span>
                        @else
                            <span class="text-gray-400">En attente</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Actions globales --}}
    <div class="mt-8 bg-white rounded-lg shadow-md p-6">
        <h3 class="text-lg font-semibold mb-4">Actions</h3>
        <div class="flex space-x-4">
            @if($workflowStatus['steps']['period_closed']['can_execute'])
                <form action="{{ route('workflow.close-period', $period->id) }}"
                      method="POST"
                      onsubmit="return confirm('Êtes-vous sûr de vouloir clôturer cette période ? Cette action est irréversible.');">
                    @csrf
                    <button type="submit"
                            class="bg-red-600 text-white px-6 py-3 rounded hover:bg-red-700 transition-colors">
                        Clôturer la période
                    </button>
                </form>
            @endif

            <a href="{{ route('workflow.report', $period->id) }}"
               class="bg-gray-600 text-white px-6 py-3 rounded hover:bg-gray-700 transition-colors">
                Générer rapport
            </a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    // Auto-refresh toutes les 30 secondes si des processus sont en cours
    @if(!$period->status === 'closed')
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    @endif
</script>
@endpush
