{{-- resources/views/admin/workflow/history.blade.php --}}
@extends('layouts.admin')

@section('title', 'Historique du workflow - Période ' . $period)

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <nav class="flex items-center text-sm">
                        <a href="{{ route('admin.workflow.index', ['period' => $period]) }}"
                           class="text-gray-500 hover:text-gray-700">
                            Workflow
                        </a>
                        <span class="mx-2 text-gray-400">/</span>
                        <span class="text-gray-700 font-medium">Historique</span>
                    </nav>
                    <h1 class="mt-2 text-2xl font-bold text-gray-900">Historique des actions</h1>
                    <p class="mt-1 text-sm text-gray-600">Période {{ $period }}</p>
                </div>
                <a href="{{ route('admin.workflow.index', ['period' => $period]) }}"
                   class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                    ← Retour au workflow
                </a>
            </div>
        </div>

        {{-- Tableau des logs --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Date/Heure
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Étape
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Action
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Statut
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Utilisateur
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Durée
                        </th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                            Détails
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $log->step_label }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $log->action }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                    bg-{{ $log->status_color }}-100 text-{{ $log->status_color }}-800">
                                    {{ $log->status_label }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $log->user->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $log->duration_for_humans ?? '-' }}
                            </td>
                            <td class="px-6 py-4 text-sm">
                                @if($log->error_message)
                                    <button onclick="showDetails('error-{{ $log->id }}')"
                                            class="text-red-600 hover:text-red-900">
                                        Voir l'erreur
                                    </button>
                                    <div id="error-{{ $log->id }}" class="hidden mt-2 p-2 bg-red-50 rounded text-xs text-red-700">
                                        {{ $log->error_message }}
                                    </div>
                                @elseif($log->details && count($log->details) > 0)
                                    <button onclick="showDetails('details-{{ $log->id }}')"
                                            class="text-indigo-600 hover:text-indigo-900">
                                        Voir détails
                                    </button>
                                    <div id="details-{{ $log->id }}" class="hidden mt-2 p-2 bg-gray-50 rounded text-xs">
                                        <pre>{{ json_encode($log->details, JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-4 text-center text-sm text-gray-500">
                                Aucun historique trouvé pour cette période.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($logs->hasPages())
            <div class="mt-4">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>

<script>
function showDetails(id) {
    const element = document.getElementById(id);
    if (element.classList.contains('hidden')) {
        element.classList.remove('hidden');
    } else {
        element.classList.add('hidden');
    }
}
</script>
@endsection
