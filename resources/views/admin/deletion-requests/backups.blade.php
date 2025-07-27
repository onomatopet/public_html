@extends('layouts.admin')

@section('title', 'Gestion des Backups')

@php
    function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
@endphp

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestion des Backups</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        Backups créés lors des suppressions avec possibilité de restauration
                    </p>
                </div>
                <a href="{{ route('admin.deletion-requests.index') }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour aux demandes
                </a>
            </div>
        </div>

        {{-- Messages de session --}}
        @if(session('success'))
            <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                <span class="block sm:inline">{{ session('error') }}</span>
            </div>
        @endif

        {{-- Statistiques --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Total Backups</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $backups->total() ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Restaurés</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $backups->where('restored_at', '!=', null)->count() ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Cette semaine</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ $backups->where('created_at', '>=', now()->startOfWeek())->count() ?? 0 }}</p>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-100 rounded-full p-3">
                        <svg class="h-6 w-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <p class="text-sm font-medium text-gray-600">Taille totale</p>
                        <p class="text-2xl font-semibold text-gray-900">{{ formatBytes($totalSize ?? 0) }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Filtres --}}
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <form method="GET" action="{{ route('admin.backups.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div>
                    <label for="entity_type" class="block text-sm font-medium text-gray-700 mb-1">Type d'entité</label>
                    <div class="relative">
                        <select id="entity_type" name="entity_type"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 appearance-none pr-10 text-gray-900">
                            <option value="" class="text-gray-500">Tous les types</option>
                            <option value="distributeur" {{ request('entity_type') == 'distributeur' ? 'selected' : '' }}>
                                🧑‍💼 Distributeur
                            </option>
                            <option value="achat" {{ request('entity_type') == 'achat' ? 'selected' : '' }}>
                                🛒 Achat
                            </option>
                            <option value="product" {{ request('entity_type') == 'product' ? 'selected' : '' }}>
                                📦 Produit
                            </option>
                            <option value="bonus" {{ request('entity_type') == 'bonus' ? 'selected' : '' }}>
                                🎁 Bonus
                            </option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">Date début</label>
                    <div class="relative">
                        <input type="date" id="date_from" name="date_from" value="{{ request('date_from') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pl-10">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">Date fin</label>
                    <div class="relative">
                        <input type="date" id="date_to" name="date_to" value="{{ request('date_to') }}"
                               class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 pl-10">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div>
                    <label for="restored" class="block text-sm font-medium text-gray-700 mb-1">Statut</label>
                    <div class="relative">
                        <select id="restored" name="restored"
                                class="w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 appearance-none pr-10 text-gray-900">
                            <option value="" class="text-gray-500">Tous les statuts</option>
                            <option value="0" {{ request('restored') === '0' ? 'selected' : '' }}>
                                ⏳ Non restaurés
                            </option>
                            <option value="1" {{ request('restored') === '1' ? 'selected' : '' }}>
                                ✅ Restaurés
                            </option>
                        </select>
                        <div class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        Filtrer
                    </button>
                    @if(request()->anyFilled(['entity_type', 'date_from', 'date_to', 'restored']))
                        <a href="{{ route('admin.backups.index') }}"
                           class="px-3 py-2 border border-gray-300 text-gray-700 font-medium rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200"
                           title="Réinitialiser les filtres">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Tableau des backups --}}
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                ID / Type
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Entité
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Créé par
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Date de création
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Statut
                            </th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($backups as $backup)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $backup->backup_id }}</div>
                                    <div class="text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ ucfirst($backup->entity_type) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">ID: {{ $backup->entity_id }}</div>
                                    @php
                                        // Gérer le cas où backup_data est déjà un array ou un objet
                                        if (is_string($backup->backup_data)) {
                                            $data = json_decode($backup->backup_data, true);
                                        } elseif (is_array($backup->backup_data)) {
                                            $data = $backup->backup_data;
                                        } elseif (is_object($backup->backup_data)) {
                                            $data = (array) $backup->backup_data;
                                        } else {
                                            $data = [];
                                        }

                                        $name = '';
                                        if ($backup->entity_type === 'distributeur') {
                                            $name = ($data['nom_distributeur'] ?? '') . ' ' . ($data['pnom_distributeur'] ?? '');
                                        } elseif ($backup->entity_type === 'product') {
                                            $name = $data['name'] ?? '';
                                        }
                                    @endphp
                                    @if($name)
                                        <div class="text-sm text-gray-500">{{ $name }}</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $backup->createdBy->name ?? 'N/A' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">{{ $backup->created_at->format('d/m/Y H:i') }}</div>
                                    <div class="text-sm text-gray-500">{{ $backup->created_at->diffForHumans() }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($backup->restored_at)
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            Restauré
                                        </span>
                                        <div class="text-xs text-gray-500 mt-1">
                                            {{ $backup->restored_at->format('d/m/Y H:i') }}
                                        </div>
                                    @else
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            En attente
                                        </span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex items-center justify-end space-x-2">
                                        <button onclick="showBackupDetails('{{ $backup->backup_id }}')"
                                                class="text-blue-600 hover:text-blue-900 transition-colors duration-200"
                                                title="Voir les détails">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                            </svg>
                                        </button>

                                        @if(!$backup->restored_at && auth()->user()->hasPermission('restore_backups'))
                                            <form action="{{ route('admin.deletion-requests.restore-backup') }}" method="POST" class="inline">
                                                @csrf
                                                <input type="hidden" name="backup_id" value="{{ $backup->backup_id }}">
                                                <button type="submit"
                                                        onclick="return confirm('Êtes-vous sûr de vouloir restaurer cette entité ?')"
                                                        class="text-green-600 hover:text-green-900 transition-colors duration-200"
                                                        title="Restaurer">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                                    </svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                    </svg>
                                    <h3 class="mt-2 text-sm font-medium text-gray-900">Aucun backup trouvé</h3>
                                    <p class="mt-1 text-sm text-gray-500">
                                        Les backups apparaîtront ici lors des suppressions d'entités.
                                    </p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            @if($backups->hasPages())
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                    {{ $backups->withQueryString()->links() }}
                </div>
            @endif
        </div>
    </div>
</div>

{{-- Modal de détails --}}
<div id="backupDetailsModal" class="hidden fixed z-50 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div class="inline-block align-bottom bg-white rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-3xl sm:w-full sm:p-6">
            <div class="absolute top-0 right-0 pt-4 pr-4">
                <button type="button" onclick="closeModal()" class="bg-white rounded-md text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <span class="sr-only">Fermer</span>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="sm:flex sm:items-start">
                <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 sm:mx-0 sm:h-10 sm:w-10">
                    <svg class="h-6 w-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                    </svg>
                </div>
                <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                    <h3 class="text-lg leading-6 font-medium text-gray-900" id="modal-title">
                        Détails du backup
                    </h3>
                    <div class="mt-4">
                        <pre id="backupContent" class="bg-gray-50 rounded-lg p-4 overflow-x-auto text-xs text-gray-700 max-h-96 overflow-y-auto"></pre>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Données des backups pour le modal
    const backupData = {
        @foreach($backups as $backup)
            @php
                // Gérer le cas où backup_data est déjà décodé
                if (is_string($backup->backup_data)) {
                    $backupDataDecoded = json_decode($backup->backup_data);
                } elseif (is_array($backup->backup_data) || is_object($backup->backup_data)) {
                    $backupDataDecoded = $backup->backup_data;
                } else {
                    $backupDataDecoded = null;
                }
            @endphp
            '{{ $backup->backup_id }}': @json($backupDataDecoded){{ !$loop->last ? ',' : '' }}
        @endforeach
    };

    function showBackupDetails(backupId) {
        const modal = document.getElementById('backupDetailsModal');
        const content = document.getElementById('backupContent');

        if (backupData[backupId]) {
            content.textContent = JSON.stringify(backupData[backupId], null, 2);
            modal.classList.remove('hidden');
        }
    }

    function closeModal() {
        document.getElementById('backupDetailsModal').classList.add('hidden');
    }

    // Fermer le modal en cliquant en dehors
    document.getElementById('backupDetailsModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeModal();
        }
    });
</script>
@endpush
@endsection
