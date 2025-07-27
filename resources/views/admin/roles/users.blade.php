{{-- resources/views/admin/roles/users.blade.php --}}

@extends('layouts.admin')

@section('title', 'Gestion des Utilisateurs et Rôles')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Gestion des Utilisateurs</h1>
                    <p class="mt-1 text-sm text-gray-600">Assignez des rôles aux utilisateurs et gérez leurs permissions</p>
                </div>
                <div>
                    <a href="{{ route('admin.roles.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                        </svg>
                        Retour aux rôles
                    </a>
                </div>
            </div>
        </div>

        {{-- Filtres --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <form method="GET" action="{{ route('admin.roles.users') }}" class="flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <input type="text" 
                           name="search" 
                           value="{{ request('search') }}"
                           placeholder="Rechercher par nom ou email..." 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="sm:w-48">
                    <select name="role" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Tous les rôles</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->name }}" {{ request('role') == $role->name ? 'selected' : '' }}>
                                {{ $role->display_name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" 
                        class="px-6 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    Filtrer
                </button>
            </form>
        </div>

        {{-- Liste des utilisateurs --}}
        <div class="bg-white shadow-lg rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Utilisateur
                            </th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Rôles actuels
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Statut
                            </th>
                            <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                Dernière connexion
                            </th>
                            <th scope="col" class="relative px-6 py-3">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($users as $user)
                        <tr class="hover:bg-gray-50 transition-colors duration-200">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10">
                                        <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-gray-600 font-medium text-sm">
                                                {{ substr($user->name, 0, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900">
                                            {{ $user->name }}
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            {{ $user->email }}
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    @forelse($user->roles as $role)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $role->display_name }}
                                        </span>
                                    @empty
                                        <span class="text-sm text-gray-500 italic">Aucun rôle</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <button onclick="toggleUserStatus({{ $user->id }})" 
                                        class="relative inline-flex items-center h-6 rounded-full w-11 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 {{ $user->is_active ? 'bg-green-600' : 'bg-gray-200' }}">
                                    <span class="sr-only">{{ $user->is_active ? 'Désactiver' : 'Activer' }}</span>
                                    <span class="translate-x-0 inline-block w-4 h-4 transform bg-white rounded-full transition-transform {{ $user->is_active ? 'translate-x-6' : 'translate-x-1' }}"></span>
                                </button>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500">
                                @if($user->last_login_at)
                                    {{ $user->last_login_at->diffForHumans() }}
                                @else
                                    Jamais connecté
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button onclick="editUserRoles({{ $user->id }}, {{ json_encode($user->roles->pluck('id')) }})" 
                                        class="text-blue-600 hover:text-blue-900">
                                    Modifier les rôles
                                </button>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                Aucun utilisateur trouvé.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-6 py-4 border-t border-gray-200">
                {{ $users->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>

{{-- Modal pour éditer les rôles --}}
<div id="rolesModal" class="hidden fixed z-10 inset-0 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
        
        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
            <form id="rolesForm" method="POST">
                @csrf
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                        Modifier les rôles
                    </h3>
                    
                    <div class="space-y-2">
                        @foreach($roles as $role)
                        <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                            <input type="checkbox" 
                                   name="roles[]" 
                                   value="{{ $role->id }}" 
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900">{{ $role->display_name }}</div>
                                @if($role->description)
                                <div class="text-xs text-gray-500">{{ $role->description }}</div>
                                @endif
                            </div>
                        </label>
                        @endforeach
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="submit" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Enregistrer
                    </button>
                    <button type="button" 
                            onclick="closeModal()" 
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
let currentUserId = null;

function editUserRoles(userId, currentRoles) {
    currentUserId = userId;
    const form = document.getElementById('rolesForm');
    form.action = `/admin/roles/users/${userId}/roles`;
    
    // Réinitialiser les checkboxes
    form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
        checkbox.checked = currentRoles.includes(parseInt(checkbox.value));
    });
    
    // Afficher le modal
    document.getElementById('rolesModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('rolesModal').classList.add('hidden');
}

function toggleUserStatus(userId) {
    fetch(`/admin/roles/users/${userId}/toggle-status`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert(data.error || 'Une erreur est survenue');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Une erreur est survenue');
    });
}

// Fermer le modal en cliquant en dehors
window.onclick = function(event) {
    const modal = document.getElementById('rolesModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>
@endpush
@endsection