{{-- resources/views/admin/mlm-cleaning/preview.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- Header --}}
        <div class="mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Preview des Corrections</h1>
                    <p class="mt-2 text-gray-600">Session : {{ $session->session_code }}</p>
                </div>
                <a href="{{ route('admin.mlm-cleaning.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Retour
                </a>
            </div>
        </div>

        {{-- Résumé --}}
        <div class="bg-white rounded-lg shadow mb-8">
            <div class="p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Résumé de l'analyse</h2>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div>
                        <p class="text-sm text-gray-500">Enregistrements analysés</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ number_format($preview['session']['total_records_analyzed']) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Anomalies détectées</p>
                        <p class="text-2xl font-semibold text-red-600">{{ number_format($preview['summary']['total_anomalies']) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Corrections automatiques</p>
                        <p class="text-2xl font-semibold text-green-600">{{ number_format($preview['summary']['auto_fixable']) }}</p>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Corrections manuelles</p>
                        <p class="text-2xl font-semibold text-yellow-600">{{ number_format($preview['summary']['manual_required']) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Anomalies par sévérité --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Anomalies par sévérité</h3>
                    <div class="space-y-3">
                        @foreach(['critical' => 'Critique', 'high' => 'Élevée', 'medium' => 'Moyenne', 'low' => 'Faible'] as $severity => $label)
                            @php
                                $count = $preview['by_severity'][$severity]['count'] ?? 0;
                                $percentage = $preview['by_severity'][$severity]['percentage'] ?? 0;
                                $colors = [
                                    'critical' => 'bg-red-100 text-red-800',
                                    'high' => 'bg-orange-100 text-orange-800',
                                    'medium' => 'bg-yellow-100 text-yellow-800',
                                    'low' => 'bg-blue-100 text-blue-800'
                                ];
                            @endphp
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $colors[$severity] }}">
                                        {{ $label }}
                                    </span>
                                    <span class="ml-3 text-sm text-gray-600">{{ $count }} anomalies</span>
                                </div>
                                <div class="flex items-center">
                                    <div class="w-32 bg-gray-200 rounded-full h-2 mr-2">
                                        <div class="bg-gray-600 h-2 rounded-full" style="width: {{ $percentage }}%"></div>
                                    </div>
                                    <span class="text-sm text-gray-500">{{ $percentage }}%</span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Anomalies par type</h3>
                    <div class="space-y-2">
                        @foreach($preview['by_type'] as $type => $data)
                            <div class="border-b border-gray-200 pb-2">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">{{ $data['label'] }}</p>
                                        <p class="text-xs text-gray-500">{{ $data['count'] }} occurrences ({{ $data['percentage'] }}%)</p>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Recommandations --}}
        @if(!empty($preview['recommendations']))
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Recommandations</h3>
                    <div class="space-y-4">
                        @foreach($preview['recommendations'] as $recommendation)
                            <div class="flex items-start p-4 {{ $recommendation['priority'] === 'critical' ? 'bg-red-50' : ($recommendation['priority'] === 'high' ? 'bg-yellow-50' : 'bg-blue-50') }} rounded-lg">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 {{ $recommendation['priority'] === 'critical' ? 'text-red-400' : ($recommendation['priority'] === 'high' ? 'text-yellow-400' : 'text-blue-400') }}" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h4 class="text-sm font-medium {{ $recommendation['priority'] === 'critical' ? 'text-red-800' : ($recommendation['priority'] === 'high' ? 'text-yellow-800' : 'text-blue-800') }}">
                                        {{ $recommendation['message'] }}
                                    </h4>
                                    <p class="mt-1 text-sm {{ $recommendation['priority'] === 'critical' ? 'text-red-700' : ($recommendation['priority'] === 'high' ? 'text-yellow-700' : 'text-blue-700') }}">
                                        {{ $recommendation['action'] }}
                                    </p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- Options de traitement --}}
        <form id="processForm" method="POST" action="{{ route('admin.mlm-cleaning.process', $session->id) }}">
            @csrf
            <div class="bg-white rounded-lg shadow mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Options de traitement</h3>

                    <div class="space-y-4">
                        <div>
                            <label class="text-base font-medium text-gray-900">Types d'anomalies à corriger</label>
                            <p class="text-sm text-gray-500 mb-3">Sélectionnez les types d'anomalies à corriger automatiquement</p>

                            <div class="space-y-2">
                                @php
                                    $fixableTypes = [
                                        'orphan_parent' => 'Parents orphelins',
                                        'cumul_individual_negative' => 'Cumuls individuels négatifs',
                                        'cumul_collective_less_than_individual' => 'Cumuls collectifs invalides',
                                        'grade_conditions_not_met' => 'Grades incorrects'
                                    ];
                                @endphp

                                @foreach($fixableTypes as $type => $label)
                                    <label class="flex items-center">
                                        <input type="checkbox" name="fix_types[]" value="{{ $type }}" checked
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span class="ml-2 text-sm text-gray-700">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t pt-4">
                            <label class="flex items-center">
                                <input type="checkbox" name="confirm" value="1" required
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="ml-2 text-sm text-gray-700">
                                    Je confirme vouloir appliquer les corrections sélectionnées
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-between">
                <a href="{{ route('admin.mlm-cleaning.index') }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-lg font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300">
                    Annuler
                </a>

                <button type="submit" id="processBtn"
                        class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-lg font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 focus:outline-none focus:border-green-700 focus:ring focus:ring-green-200 disabled:opacity-25 transition">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Appliquer les corrections
                </button>
            </div>
        </form>

        {{-- Liste détaillée des anomalies --}}
        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">Détail des anomalies</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Distributeur</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Période</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sévérité</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Correction</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @foreach($anomalies as $anomaly)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $anomaly->distributeur->nom_distributeur ?? 'N/A' }}
                                    <span class="text-gray-500">({{ $anomaly->distributeur->distributeur_id ?? 'N/A' }})</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $anomaly->period }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    {{ $anomaly->getTypeLabel() }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $anomaly->getSeverityColor() }}-100 text-{{ $anomaly->getSeverityColor() }}-800">
                                        {{ $anomaly->getSeverityLabel() }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    <div>{{ $anomaly->description }}</div>
                                    @if($anomaly->current_value !== null)
                                        <div class="text-xs mt-1">
                                            <span class="text-red-600">Actuel: {{ $anomaly->current_value }}</span>
                                            @if($anomaly->expected_value !== null)
                                                → <span class="text-green-600">Attendu: {{ $anomaly->expected_value }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    @if($anomaly->can_auto_fix)
                                        <span class="text-green-600">✓ Auto</span>
                                    @else
                                        <span class="text-yellow-600">Manuel</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($anomalies->hasPages())
                <div class="p-6 border-t border-gray-200">
                    {{ $anomalies->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('processForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const btn = document.getElementById('processBtn');
        btn.disabled = true;
        btn.innerHTML = '<svg class="animate-spin w-4 h-4 mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Traitement en cours...';

        try {
            const formData = new FormData(this);
            const response = await fetch(this.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (data.success) {
                if (data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    showNotification('Traitement lancé avec succès', 'success');
                    setTimeout(() => window.location.href = '{{ route("admin.mlm-cleaning.index") }}', 2000);
                }
            } else {
                showNotification(data.message || 'Une erreur est survenue', 'error');
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Appliquer les corrections';
            }
        } catch (error) {
            showNotification('Erreur de connexion', 'error');
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Appliquer les corrections';
        }
    });

    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
            type === 'success' ? 'bg-green-500' :
            type === 'error' ? 'bg-red-500' :
            'bg-blue-500'
        } text-white`;
        notification.innerHTML = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
</script>
@endpush
