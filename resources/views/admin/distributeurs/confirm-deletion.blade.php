@extends('layouts.admin')

@section('content')
<div class="max-w-4xl mx-auto py-6">
    <!-- Header -->
    <div class="mb-8">
        <nav class="flex" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('admin.distributeurs.index') }}" class="text-gray-700 hover:text-gray-900">
                        Distributeurs
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-500">Confirmation de suppression</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="mt-4">
            <h1 class="text-2xl font-bold text-gray-900">Confirmation de suppression</h1>
            <p class="mt-2 text-sm text-gray-600">
                Analyse de l'impact et validation de la suppression du distributeur
            </p>
        </div>
    </div>

    <!-- Informations du distributeur -->
    <div class="bg-white shadow-lg rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Distributeur à supprimer</h2>
        </div>
        <div class="px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Nom complet</label>
                    <p class="text-sm text-gray-900">{{ $distributeur->full_name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Matricule</label>
                    <p class="text-sm text-gray-900">#{{ $distributeur->distributeur_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Grade actuel</label>
                    <p class="text-sm text-gray-900">
                        @if($distributeur->etoiles_id)
                            @for($i = 0; $i < $distributeur->etoiles_id; $i++)⭐@endfor
                            ({{ $distributeur->etoiles_id }} étoiles)
                        @else
                            Aucun grade
                        @endif
                    </p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Téléphone</label>
                    <p class="text-sm text-gray-900">{{ $distributeur->tel_distributeur ?? 'Non renseigné' }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Analyse de validation -->
    <div class="space-y-6">

        <!-- Statut général de validation -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Analyse de validation</h2>
            </div>
            <div class="px-6 py-4">
                @if($validationResult['can_delete'])
                    <div class="rounded-md bg-green-50 p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Suppression possible</h3>
                                <p class="mt-1 text-sm text-green-700">Le distributeur peut être supprimé en toute sécurité.</p>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="rounded-md bg-red-50 p-4">
                        <div class="flex">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                            </svg>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-red-800">Suppression bloquée</h3>
                                <p class="mt-1 text-sm text-red-700">Des problèmes doivent être résolus avant la suppression.</p>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Problèmes bloquants -->
        @if(!empty($validationResult['blockers']))
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-red-600">⚠️ Problèmes bloquants</h2>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-4">
                    @foreach($validationResult['blockers'] as $blocker)
                    <div class="border-l-4 border-red-400 pl-4">
                        {{-- Vérifier si $blocker est un tableau ou une chaîne --}}
                        @if(is_array($blocker))
                            <h4 class="text-sm font-medium text-red-800">{{ $blocker['message'] ?? $blocker[0] ?? 'Problème non spécifié' }}</h4>
                            @if(isset($blocker['suggested_action']))
                                <p class="text-sm text-red-600 mt-1">{{ $blocker['suggested_action'] }}</p>
                            @endif
                            @if(isset($blocker['details']) && is_array($blocker['details']))
                            <div class="mt-2">
                                <details class="text-xs text-gray-600">
                                    <summary class="cursor-pointer hover:text-gray-800">Voir les détails</summary>
                                    <div class="mt-2 bg-gray-50 p-2 rounded">
                                        <pre class="whitespace-pre-wrap">{{ json_encode($blocker['details'], JSON_PRETTY_PRINT) }}</pre>
                                    </div>
                                </details>
                            </div>
                            @endif
                        @else
                            {{-- Si c'est une chaîne simple --}}
                            <h4 class="text-sm font-medium text-red-800">{{ $blocker }}</h4>
                        @endif
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        {{-- Faire la même chose pour les warnings --}}
        <!-- Avertissements -->
        @if(!empty($validationResult['warnings']))
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-yellow-600">⚠️ Avertissements</h2>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-3">
                    @foreach($validationResult['warnings'] as $warning)
                    <div class="flex items-start">
                        <svg class="h-5 w-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                        <p class="ml-3 text-sm text-gray-700">
                            {{-- Vérifier si c'est un tableau ou une chaîne --}}
                            @if(is_array($warning))
                                {{ $warning['message'] ?? $warning[0] ?? 'Avertissement non spécifié' }}
                            @else
                                {{ $warning }}
                            @endif
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Actions de nettoyage suggérées -->
        @if(!empty($cleanupActions))
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-blue-600">🔧 Actions de nettoyage suggérées</h2>
            </div>
            <div class="px-6 py-4">
                <div class="space-y-3">
                    @foreach($cleanupActions as $action)
                    <div class="border rounded-lg p-4 hover:bg-gray-50">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                @php
                                    $iconColor = match($action['priority'] ?? 'medium') {
                                        'high' => 'text-red-500',
                                        'low' => 'text-green-500',
                                        default => 'text-yellow-500'
                                    };
                                @endphp
                                <svg class="h-6 w-6 {{ $iconColor }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div class="ml-3 flex-1">
                                <h4 class="text-sm font-medium text-gray-900">
                                    {{ $action['description'] ?? 'Action non spécifiée' }}
                                </h4>
                                <div class="mt-1 text-xs text-gray-500 space-y-1">
                                    @if(isset($action['estimated_time']))
                                        <p>Temps estimé: {{ $action['estimated_time'] }}</p>
                                    @endif
                                    @if(isset($action['action']))
                                        <p>Code action: <code class="bg-gray-100 px-1 rounded">{{ $action['action'] }}</code></p>
                                    @endif
                                    <p>Priorité:
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                            @if(($action['priority'] ?? 'medium') == 'high') bg-red-100 text-red-800
                                            @elseif(($action['priority'] ?? 'medium') == 'low') bg-green-100 text-green-800
                                            @else bg-yellow-100 text-yellow-800
                                            @endif">
                                            {{ ucfirst($action['priority'] ?? 'medium') }}
                                        </span>
                                    </p>
                                </div>
                                @if(isset($action['details']))
                                <div class="mt-2">
                                    <details class="text-xs text-gray-600">
                                        <summary class="cursor-pointer hover:text-gray-800">Plus de détails</summary>
                                        <div class="mt-2 bg-gray-50 p-2 rounded">
                                            @if(is_array($action['details']))
                                                <pre class="whitespace-pre-wrap">{{ json_encode($action['details'], JSON_PRETTY_PRINT) }}</pre>
                                            @else
                                                <p>{{ $action['details'] }}</p>
                                            @endif
                                        </div>
                                    </details>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        <!-- Formulaire de demande de suppression -->
        <div class="bg-white shadow-lg rounded-lg">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Demande de suppression</h2>
            </div>
            <div class="px-6 py-4">
                <form action="{{ route('admin.distributeurs.request-deletion', $distributeur) }}" method="POST">
                    @csrf

                    <!-- Raison de la suppression -->
                    <div class="mb-4">
                        <label for="reason" class="block text-sm font-medium text-gray-700 mb-2">
                            Raison de la suppression <span class="text-red-500">*</span>
                        </label>
                        <textarea
                            name="reason"
                            id="reason"
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('reason') border-red-500 @enderror"
                            placeholder="Expliquez pourquoi ce distributeur doit être supprimé..."
                            required
                        >{{ old('reason') }}</textarea>
                        @error('reason')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                        <p class="mt-1 text-xs text-gray-500">Minimum 10 caractères. Cette information sera conservée dans les logs.</p>
                    </div>

                    <!-- Options avancées -->
                    @if(auth()->user()->hasPermission('force_delete'))
                    <div class="mb-4">
                        <div class="flex items-center">
                            <input
                                type="checkbox"
                                name="force_immediate"
                                id="force_immediate"
                                value="1"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                            >
                            <label for="force_immediate" class="ml-2 block text-sm text-gray-700">
                                Forcer la suppression immédiate (bypass du workflow d'approbation)
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">
                            ⚠️ Disponible uniquement pour les super-administrateurs. Un backup sera toujours créé.
                        </p>
                    </div>
                    @endif

                    <!-- Actions -->
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                        <a
                            href="{{ route('admin.distributeurs.index') }}"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                        >
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                            </svg>
                            Annuler
                        </a>

                        <div class="flex space-x-3">
                            @if($validationResult['can_delete'])
                                <button
                                    type="submit"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    Demander la suppression
                                </button>
                            @else
                                <button
                                    type="button"
                                    disabled
                                    class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-400 bg-gray-100 cursor-not-allowed"
                                >
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.732-.833-2.5 0L4.314 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                                    </svg>
                                    Suppression bloquée
                                </button>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Informations sur le backup -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex">
            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
            </svg>
            <div class="ml-3">
                <h3 class="text-sm font-medium text-blue-800">Système de backup automatique</h3>
                <p class="mt-1 text-sm text-blue-700">
                    Un backup complet sera automatiquement créé avant toute suppression, incluant toutes les données liées.
                    Les backups sont conservés de manière sécurisée et permettent une restauration complète si nécessaire.
                </p>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Avertissement avant soumission
    document.querySelector('form').addEventListener('submit', function(e) {
        const forceImmediate = document.getElementById('force_immediate')?.checked;

        if (forceImmediate) {
            if (!confirm('⚠️ ATTENTION: Vous êtes sur le point de forcer une suppression immédiate.\n\nCette action est irréversible (bien qu\'un backup soit créé).\n\nÊtes-vous absolument certain de vouloir continuer ?')) {
                e.preventDefault();
                return false;
            }
        } else {
            if (!confirm('Confirmer la demande de suppression ?\n\nUn backup sera créé et la demande sera soumise pour approbation si nécessaire.')) {
                e.preventDefault();
                return false;
            }
        }
    });
</script>
@endpush
@endsection
