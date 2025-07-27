{{-- resources/views/admin/roles/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Détails du rôle - ' . $role->display_name)

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $role->display_name }}</h1>
                    <p class="mt-1 text-sm text-gray-600">{{ $role->description ?? 'Aucune description' }}</p>
                </div>
                <div class="flex space-x-3">
                    @if(!$role->is_system && auth()->user()->hasPermission('edit_roles'))
                    <a href="{{ route('admin.roles.edit', $role) }}" 
                       class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white text-sm font-medium rounded-lg hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Modifier
                    </a>
                    @endif
                    <a href="{{ route('admin.roles.index') }}" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                        </svg>
                        Retour
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Informations du rôle --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Détails --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Informations du rôle</h2>
                    </div>
                    <div class="p-6">
                        <dl class="grid grid-cols-1 gap-x-4 gap-y-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Identifiant</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $role->name }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Type</dt>
                                <dd class="mt-1">
                                    @if($role->is_system)
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            Système
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            Personnalisé
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Priorité</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $role->priority }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500">Créé le</dt>
                                <dd class="mt-1 text-sm text-gray-900">{{ $role->created_at->format('d/m/Y H:i') }}</dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Permissions --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">
                            Permissions ({{ $role->permissions->count() }})
                        </h2>
                    </div>
                    <div class="p-6">
                        @if($permissionsByCategory->isEmpty())
                            <p class="text-gray-500 text-center py-4">Aucune permission assignée à ce rôle.</p>
                        @else
                            <div class="space-y-6">
                                @foreach($categories as $categoryKey => $categoryName)
                                    @if(isset($permissionsByCategory[$categoryKey]))
                                    <div>
                                        <h3 class="text-sm font-medium text-gray-900 mb-2">{{ $categoryName }}</h3>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                            @foreach($permissionsByCategory[$categoryKey] as $permission)
                                            <div class="flex items-center">
                                                <svg class="h-5 w-5 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span class="text-sm text-gray-700">{{ $permission->display_name }}</span>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @endif
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Statistiques --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Statistiques</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Utilisateurs</span>
                                <span class="text-2xl font-semibold text-gray-900">{{ $role->users->count() }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-medium text-gray-500">Permissions</span>
                                <span class="text-2xl font-semibold text-gray-900">{{ $role->permissions->count() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Utilisateurs avec ce rôle --}}
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Utilisateurs avec ce rôle</h2>
                    </div>
                    <div class="p-6">
                        @if($role->users->isEmpty())
                            <p class="text-gray-500 text-center py-4">Aucun utilisateur n'a ce rôle.</p>
                        @else
                            <div class="space-y-3 max-h-60 overflow-y-auto">
                                @foreach($role->users->take(10) as $user)
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-gray-600 font-medium text-xs">
                                                {{ substr($user->name, 0, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $user->name }}</p>
                                        <p class="text-xs text-gray-500">{{ $user->email }}</p>
                                    </div>
                                </div>
                                @endforeach
                                @if($role->users->count() > 10)
                                    <a href="{{ route('admin.roles.users', ['role' => $role->name]) }}" 
                                       class="block text-center text-sm text-blue-600 hover:text-blue-800 mt-2">
                                        Voir tous les {{ $role->users->count() }} utilisateurs
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Actions --}}
                @if(!$role->is_system && auth()->user()->hasPermission('delete_roles'))
                <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-medium text-gray-900">Actions</h2>
                    </div>
                    <div class="p-6">
                        @if($role->users->count() == 0)
                        <button type="button" 
                                onclick="deleteRole({{ $role->id }})" 
                                class="w-full inline-flex justify-center items-center px-4 py-2 bg-red-600 text-white text-sm font-medium rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors duration-200">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                            Supprimer ce rôle
                        </button>
                        @else
                        <p class="text-sm text-gray-500 text-center">
                            Ce rôle ne peut pas être supprimé car il est assigné à {{ $role->users->count() }} utilisateur(s).
                        </p>
                        @endif
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function deleteRole(roleId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce rôle ? Cette action est irréversible.')) {
        fetch(`/admin/roles/${roleId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                'Accept': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '{{ route('admin.roles.index') }}';
            } else {
                alert(data.error || 'Une erreur est survenue');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue');
        });
    }
}
</script>
@endpush
@endsection