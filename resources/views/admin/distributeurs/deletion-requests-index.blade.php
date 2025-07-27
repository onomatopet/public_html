@extends('layouts.admin')

@section('content')
<div class="max-w-7xl mx-auto py-6">
    <!-- Header avec statistiques -->
    <div class="mb-8">
        <div class="md:flex md:items-center md:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Demandes de suppression</h1>
                <p class="mt-1 text-sm text-gray-600">
                    Gestion des demandes de suppression avec workflow d'approbation
                </p>
            </div>
            <div class="mt-4 md:mt-0">
                <a href="{{ route('admin.backups.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                    Gérer les backups
                </a>
            </div>
        </div>

        <!-- Statistiques en temps réel -->
        <div class="mt-6 grid grid-cols-1 md:grid-cols-5 gap-4">
            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-gray-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ $stats['total'] }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Total</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['total'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ $stats['pending'] }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">En attente</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['pending'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ $stats['approved'] }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Approuvées</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['approved'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ $stats['completed'] }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Exécutées</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['completed'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white overflow-hidden shadow rounded-lg">
                <div class="p-5">
                    <div class="flex items-center">
                        <div class="flex-shrink-0">
                            <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center">
                                <span class="text-white text-sm font-medium">{{ $stats['rejected'] }}</span>
                            </div>
                        </div>
                        <div class="ml-5 w-0 flex-1">
                            <dl>
                                <dt class="text-sm font-medium text-gray-500 truncate">Rejetées</dt>
                                <dd class="text-lg font-medium text-gray-900">{{ $stats['rejected'] }}</dd>
                            </dl>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">Filtres</h3>
        </div>
        <div class="px-6 py-4">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <!-- Statut -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <select name="status" id="status" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les statuts</option>
                        <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>En attente</option>
                        <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approuvées</option>
                        <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Exécutées</option>
                        <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejetées</option>
                    </select>
                </div>

                <!-- Type d'entité -->
                <div>
                    <label for="entity_type" class="block text-sm font-medium text-gray-700 mb-1">Type d'entité</label>
                    <select name="entity_type" id="entity_type" class="w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les types</option>
                        <option value="distributeur" {{ request('entity_type') === 'distributeur' ? 'selected' : '' }}>Distributeur</option>
                        <option value="achat" {{ request('entity_type') === 'achat' ? 'selected' : '' }}>Achat</option>
                        <option value="product" {{ request('entity_type') === 'product' ? 'selected' : '' }}>Produit</option>
                    </select>
                </div>

                <!-- Actions -->
                <div class="md:col-span-2 flex items-end space-x-3">
                    <button type="submit" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.414A1 1 0 013 6.707V4z"/>
                        </svg>
                        Filtrer
                    </button>

                    <a href="{{ route('admin.deletion-requests.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        Réinitialiser
                    </a>

                    @if(auth()->user()->hasPermission('export_data'))
                    <a href="{{ route('admin.deletion-requests.export', request()->query()) }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        Exporter CSV
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <!-- Liste des demandes -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h3 class="text-lg font-medium text-gray-900">
                Demandes de suppression
                <span class="text-sm text-gray-500">({{ $deletionRequests->total() }} résultats)</span>
            </h3>
        </div>

        @if($deletionRequests->count() > 0)
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Entité</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Demandeur</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approbateur</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($deletionRequests as $request)
                    <tr class="hover:bg-gray-50">
                        <!-- Entité -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-10 w-10">
                                    <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                        @switch($request->entity_type)
                                            @case('distributeur')
                                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                                </svg>
                                                @break
                                            @case('achat')
                                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5L2 21m0 0h3.5M7 13v8a2 2 0 002 2h6a2 2 0 002-2v-8"/>
                                                </svg>
                                                @break
                                            @default
                                                <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                                </svg>
                                        @endswitch
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <div class="text-sm font-medium text-gray-900">{{ $request->getEntityName() }}</div>
                                    <div class="text-sm text-gray-500">{{ ucfirst($request->entity_type) }} #{{ $request->entity_id }}</div>
                                </div>
                            </div>
                        </td>

                        <!-- Statut -->
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $request->getStatusColor() === 'yellow' ? 'bg-yellow-100 text-yellow-800' :
                                   ($request->getStatusColor() === 'green' ? 'bg-green-100 text-green-800' :
                                   ($request->getStatusColor() === 'red' ? 'bg-red-100 text-red-800' :
                                   ($request->getStatusColor() === 'blue' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'))) }}">
                                {{ $request->getStatusLabel() }}
                            </span>
                        </td>

                        <!-- Demandeur -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $request->requestedBy->name ?? 'N/A' }}
                        </td>

                        <!-- Date -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $request->created_at->format('d/m/Y H:i') }}
                        </td>

                        <!-- Approbateur -->
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $request->approvedBy->name ?? '-' }}
                        </td>

                        <!-- Actions -->
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <div class="flex items-center justify-end space-x-2">
                                <!-- Voir les détails -->
                                <a href="{{ route('admin.deletion-requests.show', $request) }}"
                                   class="text-blue-600 hover:text-blue-900" title="Voir les détails">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                    </svg>
                                </a>

                                @if($request->isPending() && auth()->user()->hasPermission('approve_deletions'))
                                    <!-- Actions d'approbation rapides -->
                                    <div class="flex space-x-1">
                                        <form action="{{ route('admin.deletion-requests.approve', $request) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-green-600 hover:text-green-900" title="Approuver"
                                                    onclick="return confirm('Approuver cette demande de suppression ?')">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                </svg>
                                            </button>
                                        </form>

                                        <button type="button" class="text-red-600 hover:text-red-900" title="Rejeter"
                                                onclick="openRejectModal({{ $request->id }})">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                @endif

                                @if($request->isApproved() && auth()->user()->hasPermission('execute_deletions'))
                                    <!-- Exécuter -->
                                    <form action="{{ route('admin.deletion-requests.execute', $request) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-red-600 hover:text-red-900" title="Exécuter la suppression"
                                                onclick="return confirm('⚠️ ATTENTION: Exécuter la suppression maintenant ?\n\nCette action est irréversible!')">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                            </svg>
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $deletionRequests->links() }}
        </div>

        @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Aucune demande de suppression</h3>
            <p class="mt-1 text-sm text-gray-500">Aucune demande ne correspond aux critères de filtrage.</p>
        </div>
        @endif
    </div>
</div>

<!-- Modal de rejet -->
<div id="rejectModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
    <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
        <div class="mt-3">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Rejeter la demande</h3>
            <form id="rejectForm" method="POST">
                @csrf
                <div class="mb-4">
                    <label for="rejection_reason" class="block text-sm font-medium text-gray-700 mb-2">
                        Raison du rejet <span class="text-red-500">*</span>
                    </label>
                    <textarea
                        name="rejection_reason"
                        id="rejection_reason"
                        rows="3"
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Expliquez pourquoi cette demande est rejetée..."
                        required
                    ></textarea>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeRejectModal()"
                            class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Annuler
                    </button>
                    <button type="submit"
                            class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
                        Rejeter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openRejectModal(requestId) {
    const modal = document.getElementById('rejectModal');
    const form = document.getElementById('rejectForm');
    form.action = `/admin/deletion-requests/${requestId}/reject`;
    modal.classList.remove('hidden');
}

function closeRejectModal() {
    const modal = document.getElementById('rejectModal');
    modal.classList.add('hidden');
    document.getElementById('rejection_reason').value = '';
}

// Fermer le modal en cliquant à l'extérieur
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeRejectModal();
    }
});
</script>
@endpush
@endsection
