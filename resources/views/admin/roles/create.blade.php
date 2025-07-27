{{-- resources/views/admin/roles/create.blade.php --}}

@extends('layouts.admin')

@section('title', 'Créer un rôle')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Créer un nouveau rôle</h1>
                <a href="{{ route('admin.roles.index') }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>

        {{-- Formulaire --}}
        <form method="POST" action="{{ route('admin.roles.store') }}">
            @csrf
            
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                {{-- Informations du rôle --}}
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Informations du rôle</h2>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Identifiant unique *</label>
                        <input type="text" 
                               name="name" 
                               id="name" 
                               value="{{ old('name') }}"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('name') border-red-500 @enderror"
                               placeholder="ex: content_manager"
                               required>
                        <p class="mt-1 text-sm text-gray-500">Utilisé en interne, sans espaces ni caractères spéciaux</p>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700">Nom d'affichage *</label>
                        <input type="text" 
                               name="display_name" 
                               id="display_name" 
                               value="{{ old('display_name') }}"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('display_name') border-red-500 @enderror"
                               placeholder="ex: Gestionnaire de contenu"
                               required>
                        @error('display_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" 
                                  id="description" 
                                  rows="3"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror"
                                  placeholder="Description du rôle et de ses responsabilités...">{{ old('description') }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Permissions --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Permissions</h2>
                    
                    <div class="space-y-6">
                        @foreach($categories as $categoryKey => $categoryName)
                            @if(isset($permissions[$categoryKey]) && $permissions[$categoryKey]->count() > 0)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <input type="checkbox" 
                                           class="category-checkbox h-4 w-4 text-blue-600 rounded mr-2"
                                           data-category="{{ $categoryKey }}">
                                    {{ $categoryName }}
                                </h3>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($permissions[$categoryKey] as $permission)
                                    <label class="flex items-start">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->id }}"
                                               class="permission-checkbox category-{{ $categoryKey }} h-4 w-4 text-blue-600 rounded mt-0.5"
                                               {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                        <div class="ml-2">
                                            <div class="text-sm font-medium text-gray-700">{{ $permission->display_name }}</div>
                                            @if($permission->description)
                                            <div class="text-xs text-gray-500">{{ $permission->description }}</div>
                                            @endif
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <a href="{{ route('admin.roles.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Annuler
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Créer le rôle
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
// Gestion des checkboxes de catégorie
document.querySelectorAll('.category-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const category = this.dataset.category;
        const isChecked = this.checked;
        
        document.querySelectorAll(`.category-${category}`).forEach(permissionCheckbox => {
            permissionCheckbox.checked = isChecked;
        });
    });
});

// Mettre à jour l'état des checkboxes de catégorie
document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const categoryClass = Array.from(this.classList).find(c => c.startsWith('category-'));
        if (categoryClass) {
            const category = categoryClass.replace('category-', '');
            const categoryCheckbox = document.querySelector(`[data-category="${category}"]`);
            const allPermissions = document.querySelectorAll(`.${categoryClass}`);
            const checkedPermissions = document.querySelectorAll(`.${categoryClass}:checked`);
            
            categoryCheckbox.checked = allPermissions.length === checkedPermissions.length;
            categoryCheckbox.indeterminate = checkedPermissions.length > 0 && checkedPermissions.length < allPermissions.length;
        }
    });
});

// Initialiser l'état des checkboxes au chargement
window.addEventListener('load', function() {
    document.querySelectorAll('.category-checkbox').forEach(checkbox => {
        const category = checkbox.dataset.category;
        const allPermissions = document.querySelectorAll(`.category-${category}`);
        const checkedPermissions = document.querySelectorAll(`.category-${category}:checked`);
        
        checkbox.checked = allPermissions.length === checkedPermissions.length && allPermissions.length > 0;
        checkbox.indeterminate = checkedPermissions.length > 0 && checkedPermissions.length < allPermissions.length;
    });
});
</script>
@endpush
@endsection


{{-- resources/views/admin/roles/edit.blade.php --}}

@extends('layouts.admin')

@section('title', 'Modifier le rôle')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-bold text-gray-900">Modifier le rôle : {{ $role->display_name }}</h1>
                <a href="{{ route('admin.roles.show', $role) }}" 
                   class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                    </svg>
                    Retour
                </a>
            </div>
        </div>

        {{-- Formulaire --}}
        <form method="POST" action="{{ route('admin.roles.update', $role) }}">
            @csrf
            @method('PUT')
            
            <div class="bg-white shadow-lg rounded-lg overflow-hidden">
                {{-- Informations du rôle --}}
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">Informations du rôle</h2>
                </div>
                
                <div class="p-6 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Identifiant unique</label>
                        <input type="text" 
                               value="{{ $role->name }}"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100"
                               disabled>
                        <p class="mt-1 text-sm text-gray-500">L'identifiant ne peut pas être modifié</p>
                    </div>

                    <div>
                        <label for="display_name" class="block text-sm font-medium text-gray-700">Nom d'affichage *</label>
                        <input type="text" 
                               name="display_name" 
                               id="display_name" 
                               value="{{ old('display_name', $role->display_name) }}"
                               class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('display_name') border-red-500 @enderror"
                               required>
                        @error('display_name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
                        <textarea name="description" 
                                  id="description" 
                                  rows="3"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 @error('description') border-red-500 @enderror">{{ old('description', $role->description) }}</textarea>
                        @error('description')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                {{-- Permissions --}}
                <div class="px-6 py-4 border-t border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900 mb-4">Permissions</h2>
                    
                    <div class="space-y-6">
                        @foreach($categories as $categoryKey => $categoryName)
                            @if(isset($permissions[$categoryKey]) && $permissions[$categoryKey]->count() > 0)
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h3 class="text-sm font-medium text-gray-900 mb-3 flex items-center">
                                    <input type="checkbox" 
                                           class="category-checkbox h-4 w-4 text-blue-600 rounded mr-2"
                                           data-category="{{ $categoryKey }}">
                                    {{ $categoryName }}
                                </h3>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    @foreach($permissions[$categoryKey] as $permission)
                                    <label class="flex items-start">
                                        <input type="checkbox" 
                                               name="permissions[]" 
                                               value="{{ $permission->id }}"
                                               class="permission-checkbox category-{{ $categoryKey }} h-4 w-4 text-blue-600 rounded mt-0.5"
                                               {{ in_array($permission->id, old('permissions', $rolePermissions)) ? 'checked' : '' }}>
                                        <div class="ml-2">
                                            <div class="text-sm font-medium text-gray-700">{{ $permission->display_name }}</div>
                                            @if($permission->description)
                                            <div class="text-xs text-gray-500">{{ $permission->description }}</div>
                                            @endif
                                        </div>
                                    </label>
                                    @endforeach
                                </div>
                            </div>
                            @endif
                        @endforeach
                    </div>
                </div>

                {{-- Actions --}}
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-end space-x-3">
                    <a href="{{ route('admin.roles.show', $role) }}" 
                       class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Annuler
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        Enregistrer les modifications
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
{{-- Même script que create.blade.php --}}
<script>
// Gestion des checkboxes de catégorie
document.querySelectorAll('.category-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const category = this.dataset.category;
        const isChecked = this.checked;
        
        document.querySelectorAll(`.category-${category}`).forEach(permissionCheckbox => {
            permissionCheckbox.checked = isChecked;
        });
    });
});

// Mettre à jour l'état des checkboxes de catégorie
document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const categoryClass = Array.from(this.classList).find(c => c.startsWith('category-'));
        if (categoryClass) {
            const category = categoryClass.replace('category-', '');
            const categoryCheckbox = document.querySelector(`[data-category="${category}"]`);
            const allPermissions = document.querySelectorAll(`.${categoryClass}`);
            const checkedPermissions = document.querySelectorAll(`.${categoryClass}:checked`);
            
            categoryCheckbox.checked = allPermissions.length === checkedPermissions.length;
            categoryCheckbox.indeterminate = checkedPermissions.length > 0 && checkedPermissions.length < allPermissions.length;
        }
    });
});

// Initialiser l'état des checkboxes au chargement
window.addEventListener('load', function() {
    document.querySelectorAll('.category-checkbox').forEach(checkbox => {
        const category = checkbox.dataset.category;
        const allPermissions = document.querySelectorAll(`.category-${category}`);
        const checkedPermissions = document.querySelectorAll(`.category-${category}:checked`);
        
        checkbox.checked = allPermissions.length === checkedPermissions.length && allPermissions.length > 0;
        checkbox.indeterminate = checkedPermissions.length > 0 && checkedPermissions.length < allPermissions.length;
    });
});
</script>
@endpush
@endsection