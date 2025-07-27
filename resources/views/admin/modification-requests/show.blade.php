@extends('layouts.admin')

@section('title', 'Demande de modification #' . $modificationRequest->id)

@section('content')
<div class="container-fluid">
    {{-- En-tête --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">Demande de modification #{{ $modificationRequest->id }}</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Créée le {{ $modificationRequest->created_at->format('d/m/Y à H:i') }}
                </p>
            </div>
            <a href="{{ route('admin.modification-requests.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Retour à la liste
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Informations principales --}}
        <div class="lg:col-span-2">
            {{-- Détails de la demande --}}
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Informations de la demande</h3>
                </div>
                <div class="px-6 py-4">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Type de modification</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    @switch($modificationRequest->modification_type)
                                        @case('change_parent')
                                            Changement de parent
                                            @break
                                        @case('manual_grade')
                                            Modification de grade
                                            @break
                                        @case('adjust_cumul')
                                            Ajustement de cumul
                                            @break
                                        @case('modify_bonus')
                                            Modification de bonus
                                            @break
                                        @default
                                            {{ $modificationRequest->modification_type }}
                                    @endswitch
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Statut</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($modificationRequest->status == 'pending') bg-yellow-100 text-yellow-800
                                    @elseif($modificationRequest->status == 'approved') bg-blue-100 text-blue-800
                                    @elseif($modificationRequest->status == 'executed') bg-green-100 text-green-800
                                    @elseif($modificationRequest->status == 'rejected') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    @switch($modificationRequest->status)
                                        @case('pending') En attente @break
                                        @case('approved') Approuvée @break
                                        @case('executed') Exécutée @break
                                        @case('rejected') Rejetée @break
                                        @default {{ ucfirst($modificationRequest->status) }}
                                    @endswitch
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Niveau de risque</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    @if($modificationRequest->risk_level == 'low') bg-green-100 text-green-800
                                    @elseif($modificationRequest->risk_level == 'medium') bg-yellow-100 text-yellow-800
                                    @elseif($modificationRequest->risk_level == 'high') bg-orange-100 text-orange-800
                                    @elseif($modificationRequest->risk_level == 'critical') bg-red-100 text-red-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ ucfirst($modificationRequest->risk_level ?? 'N/A') }}
                                </span>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">Demandeur</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $modificationRequest->requestedBy->name ?? 'N/A' }}
                            </dd>
                        </div>

                        @if($modificationRequest->approved_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">
                                {{ $modificationRequest->status == 'rejected' ? 'Rejetée par' : 'Approuvée par' }}
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $modificationRequest->approvedBy->name ?? 'N/A' }}
                            </dd>
                        </div>

                        <div>
                            <dt class="text-sm font-medium text-gray-500">
                                Date {{ $modificationRequest->status == 'rejected' ? 'de rejet' : 'd\'approbation' }}
                            </dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $modificationRequest->approved_at->format('d/m/Y H:i') }}
                            </dd>
                        </div>
                        @endif

                        @if($modificationRequest->executed_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Date d'exécution</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $modificationRequest->executed_at->format('d/m/Y H:i') }}
                            </dd>
                        </div>
                        @endif

                        @if($modificationRequest->expires_at)
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Expiration</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                {{ $modificationRequest->expires_at->format('d/m/Y H:i') }}
                                @if($modificationRequest->isExpired())
                                    <span class="text-red-600 text-xs">(Expirée)</span>
                                @endif
                            </dd>
                        </div>
                        @endif
                    </dl>

                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500">Raison de la demande</dt>
                        <dd class="mt-1 text-sm text-gray-900 bg-gray-50 rounded-md p-3">
                            {{ $modificationRequest->reason }}
                        </dd>
                    </div>

                    @if($modificationRequest->rejection_reason)
                    <div class="mt-6">
                        <dt class="text-sm font-medium text-gray-500">Raison du rejet</dt>
                        <dd class="mt-1 text-sm text-gray-900 bg-red-50 rounded-md p-3 text-red-700">
                            {{ $modificationRequest->rejection_reason }}
                        </dd>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Détails de la modification --}}
            <div class="bg-white shadow rounded-lg mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Détails de la modification</h3>
                </div>
                <div class="px-6 py-4">
                    {{-- Entité concernée --}}
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Entité concernée</h4>
                        <div class="bg-blue-50 border border-blue-200 rounded-md p-4">
                            <p class="text-sm text-blue-800">
                                <strong>Type :</strong> {{ ucfirst($modificationRequest->entity_type) }}<br>
                                <strong>ID :</strong> #{{ $modificationRequest->entity_id }}
                                @if($entity)
                                    <br><strong>Nom :</strong>
                                    @if($modificationRequest->entity_type == 'distributeur')
                                        {{ $entity->full_name ?? 'N/A' }}
                                    @else
                                        {{ $entity->name ?? 'Entité #' . $entity->id }}
                                    @endif
                                @endif
                            </p>
                        </div>
                    </div>

                    {{-- Valeurs originales et nouvelles --}}
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Valeurs actuelles</h4>
                            <div class="bg-gray-50 rounded-md p-4">
                                <pre class="text-xs text-gray-600 overflow-x-auto">{{ json_encode($modificationRequest->original_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-sm font-medium text-gray-700 mb-2">Nouvelles valeurs</h4>
                            <div class="bg-green-50 rounded-md p-4">
                                <pre class="text-xs text-green-600 overflow-x-auto">{{ json_encode($modificationRequest->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </div>

                    {{-- Résumé des changements --}}
                    @if($modificationRequest->changes_summary)
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Résumé des changements</h4>
                        <ul class="list-disc list-inside space-y-1">
                            @foreach($modificationRequest->changes_summary as $change)
                                <li class="text-sm text-gray-600">{{ $change }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- Analyse d'impact --}}
                    @if($modificationRequest->impact_analysis)
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-700 mb-2">Analyse d'impact</h4>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                            <pre class="text-xs text-yellow-800 overflow-x-auto">{{ json_encode($modificationRequest->impact_analysis, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Actions et timeline --}}
        <div class="lg:col-span-1">
            {{-- Actions disponibles --}}
            @if($modificationRequest->isPending() && Auth::user()->hasPermission('approve_modifications'))
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Actions</h3>
                </div>
                <div class="px-6 py-4 space-y-3">
                    <form action="{{ route('admin.modification-requests.approve', $modificationRequest) }}" method="POST">
                        @csrf
                        <input type="hidden" name="execute_immediately" value="0">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Approuver
                        </button>
                    </form>

                    <form action="{{ route('admin.modification-requests.approve', $modificationRequest) }}" method="POST">
                        @csrf
                        <input type="hidden" name="execute_immediately" value="1">
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500"
                                onclick="return confirm('Approuver et exécuter immédiatement la modification ?')">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            Approuver et exécuter
                        </button>
                    </form>

                    <button onclick="openRejectModal()" class="w-full inline-flex justify-center items-center px-4 py-2 border border-red-300 rounded-md shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Rejeter
                    </button>
                </div>
            </div>
            @endif

            @if($modificationRequest->status == 'approved' && !$modificationRequest->isExecuted() && Auth::user()->hasPermission('execute_modifications'))
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Actions</h3>
                </div>
                <div class="px-6 py-4">
                    <form action="{{ route('admin.modification-requests.execute', $modificationRequest) }}" method="POST">
                        @csrf
                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                onclick="return confirm('Exécuter la modification maintenant ?')">
                            <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Exécuter la modification
                        </button>
                    </form>
                </div>
            </div>
            @endif

            {{-- Notes --}}
            @if($modificationRequest->notes)
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">Notes</h3>
                </div>
                <div class="px-6 py-4">
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $modificationRequest->notes }}</p>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal de rejet --}}
@if($modificationRequest->isPending())
<div id="rejectModal" class="hidden fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full sm:p-6">
            <form action="{{ route('admin.modification-requests.reject', $modificationRequest) }}" method="POST">
                @csrf
                <div>
                    <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                        <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </div>
                    <div class="mt-3 text-center sm:mt-5">
                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                            Rejeter la demande
                        </h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500">
                                Veuillez indiquer la raison du rejet de cette demande.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="mt-5">
                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700">
                        Raison du rejet
                    </label>
                    <textarea id="rejection_reason" name="rejection_reason" rows="3" required
                              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                              placeholder="Expliquez pourquoi cette demande est rejetée..."></textarea>
                </div>
                <div class="mt-5 sm:mt-6 sm:grid sm:grid-cols-2 sm:gap-3 sm:grid-flow-row-dense">
                    <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:col-start-2 sm:text-sm">
                        Rejeter
                    </button>
                    <button type="button" onclick="closeRejectModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:col-start-1 sm:mt-0 sm:text-sm">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openRejectModal() {
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
    document.getElementById('rejection_reason').value = '';
}
</script>
@endpush
@endif
@endsection
