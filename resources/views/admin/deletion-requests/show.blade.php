{{-- resources/views/admin/deletion-requests/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Détails de la demande #' . $deletionRequest->id)

@section('content')
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {{-- En-tête avec fil d'Ariane --}}
        <div class="bg-white rounded-xl shadow-sm px-6 py-5 mb-8 border border-gray-100">
            <nav class="flex items-center text-sm">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de Bord
                </a>
                <span class="mx-3 text-gray-300">/</span>
                <a href="{{ route('admin.deletion-requests.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Demandes de suppression
                </a>
                <span class="mx-3 text-gray-300">/</span>
                <span class="text-gray-700 font-medium">#{{ $deletionRequest->id }}</span>
            </nav>
        </div>

        {{-- En-tête de la page --}}
        <div class="mb-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
                <div class="mb-6 lg:mb-0">
                    <h1 class="text-3xl font-bold text-gray-900 mb-2">Demande de suppression #{{ $deletionRequest->id }}</h1>
                    <p class="text-lg text-gray-600">
                        Créée le {{ $deletionRequest->created_at->format('d/m/Y à H:i') }}
                    </p>
                </div>
                <div class="flex flex-col sm:flex-row gap-3">
                    <a href="{{ route('admin.deletion-requests.index') }}"
                       class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 text-sm font-medium rounded-lg text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                        <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Retour à la liste
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            {{-- Informations principales --}}
            <div class="lg:col-span-2 space-y-8">
                {{-- Détails de la demande --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Informations de la demande</h3>
                    </div>
                    <div class="px-6 py-6">
                        <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <div>
                                <dt class="text-sm font-medium text-gray-500 mb-1">Type d'entité</dt>
                                <dd>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                        {{ ucfirst($deletionRequest->entity_type) }}
                                    </span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 mb-1">Statut</dt>
                                <dd>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                        @if($deletionRequest->status === 'pending') bg-yellow-100 text-yellow-800
                                        @elseif($deletionRequest->status === 'approved') bg-green-100 text-green-800
                                        @elseif($deletionRequest->status === 'rejected') bg-red-100 text-red-800
                                        @elseif($deletionRequest->status === 'completed') bg-blue-100 text-blue-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ ucfirst($deletionRequest->status) }}
                                    </span>
                                </dd>
                            </div>

                            <div>
                                <dt class="text-sm font-medium text-gray-500 mb-1">Demandé par</dt>
                                <dd class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center">
                                            <span class="text-xs font-medium text-gray-600">
                                                {{ substr($deletionRequest->requestedBy->name ?? 'N', 0, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $deletionRequest->requestedBy->name ?? 'N/A' }}
                                        </p>
                                    </div>
                                </dd>
                            </div>

                            @if($deletionRequest->approvedBy)
                            <div>
                                <dt class="text-sm font-medium text-gray-500 mb-1">Approuvé par</dt>
                                <dd class="flex items-center">
                                    <div class="flex-shrink-0 h-8 w-8">
                                        <div class="h-8 w-8 rounded-full bg-green-100 flex items-center justify-center">
                                            <span class="text-xs font-medium text-green-600">
                                                {{ substr($deletionRequest->approvedBy->name ?? 'N', 0, 2) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $deletionRequest->approvedBy->name }}
                                        </p>
                                        <p class="text-xs text-gray-500">
                                            {{ $deletionRequest->approved_at->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                </dd>
                            </div>
                            @endif
                        </dl>

                        <div class="mt-8 pt-6 border-t border-gray-200">
                            <dt class="text-sm font-medium text-gray-500 mb-3">Raison de la demande</dt>
                            <dd class="bg-gray-50 rounded-lg p-4">
                                <p class="text-sm text-gray-900">{{ $deletionRequest->reason }}</p>
                            </dd>
                        </div>

                        @if($deletionRequest->rejection_reason)
                        <div class="mt-6">
                            <dt class="text-sm font-medium text-gray-500 mb-3">Raison du rejet</dt>
                            <dd class="bg-red-50 border border-red-200 rounded-lg p-4">
                                <p class="text-sm text-red-900">{{ $deletionRequest->rejection_reason }}</p>
                            </dd>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Entité concernée --}}
                @if($entity)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Entité concernée</h3>
                    </div>
                    <div class="px-6 py-6">
                        @if($deletionRequest->entity_type === 'distributeur')
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                                <div class="flex items-start">
                                    <div class="flex-shrink-0">
                                        <div class="h-12 w-12 rounded-full bg-blue-200 flex items-center justify-center">
                                            <span class="text-lg font-medium text-blue-700">
                                                {{ substr($entity->pnom_distributeur ?? 'N', 0, 1) }}{{ substr($entity->nom_distributeur ?? 'A', 0, 1) }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="ml-4 flex-1">
                                        <h4 class="text-lg font-medium text-gray-900">
                                            {{ $entity->full_name }}
                                        </h4>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Matricule: #{{ $entity->distributeur_id }}
                                        </p>
                                        <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Grade:</span>
                                                <span class="font-medium text-gray-900 ml-1">{{ $entity->etoiles_id }} ⭐</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Téléphone:</span>
                                                <span class="font-medium text-gray-900 ml-1">{{ $entity->tel_distributeur ?? 'N/A' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Email:</span>
                                                <span class="font-medium text-gray-900 ml-1">{{ $entity->email_distributeur ?? 'N/A' }}</span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Enfants directs:</span>
                                                <span class="font-medium text-gray-900 ml-1">{{ $entity->children()->count() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @elseif($deletionRequest->entity_type === 'achat')
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                                <p class="text-sm text-blue-900">
                                    <strong>ID Achat:</strong> #{{ $entity->id }}<br>
                                    <strong>Montant:</strong> {{ number_format($entity->montant_total_ligne, 2) }} €<br>
                                    <strong>Date:</strong> {{ $entity->created_at->format('d/m/Y H:i') }}
                                </p>
                            </div>
                        @endif
                    </div>
                </div>
                @endif

                {{-- Analyse de validation --}}
                @if($deletionRequest->validation_data)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Analyse de validation</h3>
                    </div>
                    <div class="px-6 py-6 space-y-6">
                        @if(isset($deletionRequest->validation_data['blockers']) && count($deletionRequest->validation_data['blockers']) > 0)
                        <div>
                            <h4 class="text-sm font-medium text-red-900 mb-3">❌ Blockers</h4>
                            <ul class="space-y-2">
                                @foreach($deletionRequest->validation_data['blockers'] as $blocker)
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="ml-2 text-sm text-gray-700">{{ $blocker }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        @if(isset($deletionRequest->validation_data['warnings']) && count($deletionRequest->validation_data['warnings']) > 0)
                        <div>
                            <h4 class="text-sm font-medium text-yellow-900 mb-3">⚠️ Avertissements</h4>
                            <ul class="space-y-2">
                                @foreach($deletionRequest->validation_data['warnings'] as $warning)
                                    <li class="flex items-start">
                                        <svg class="h-5 w-5 text-yellow-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                        <span class="ml-2 text-sm text-gray-700">{{ $warning }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        @if(isset($deletionRequest->validation_data['impact_summary']))
                        <div>
                            <h4 class="text-sm font-medium text-gray-900 mb-3">📊 Résumé de l'impact</h4>
                            <div class="bg-gray-50 rounded-lg p-4">
                                <pre class="text-xs text-gray-700 whitespace-pre-wrap">{{ json_encode($deletionRequest->validation_data['impact_summary'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                @endif
            </div>

            {{-- Sidebar avec actions et timeline --}}
            <div class="lg:col-span-1 space-y-8">
                {{-- Actions disponibles --}}
                @if($deletionRequest->isPending() && Auth::user()->hasPermission('approve_deletions'))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Actions disponibles</h3>
                    </div>
                    <div class="px-6 py-6 space-y-3">
                        <form action="{{ route('admin.deletion-requests.approve', $deletionRequest) }}" method="POST">
                            @csrf
                            <input type="hidden" name="execute_immediately" value="0">
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all duration-200">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Approuver la demande
                            </button>
                        </form>

                        <form action="{{ route('admin.deletion-requests.approve', $deletionRequest) }}" method="POST">
                            @csrf
                            <input type="hidden" name="execute_immediately" value="1">
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-orange-600 to-orange-700 hover:from-orange-700 hover:to-orange-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500 transition-all duration-200"
                                    onclick="return confirm('Approuver et exécuter immédiatement la suppression ?\n\nCette action est irréversible.')">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                                Approuver et exécuter
                            </button>
                        </form>

                        <form action="{{ route('admin.deletion-requests.reject', $deletionRequest) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center px-4 py-3 border border-red-300 rounded-lg shadow-sm text-sm font-medium text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                    onclick="return confirm('Rejeter cette demande ?')">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Rejeter la demande
                            </button>
                        </form>
                    </div>
                </div>
                @endif

                @if($deletionRequest->isApproved() && !$deletionRequest->isCompleted() && Auth::user()->hasPermission('execute_deletions'))
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Exécution</h3>
                    </div>
                    <div class="px-6 py-6">
                        <form action="{{ route('admin.deletion-requests.execute', $deletionRequest) }}" method="POST">
                            @csrf
                            <button type="submit"
                                    class="w-full inline-flex justify-center items-center px-4 py-3 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                    onclick="return confirm('Exécuter la suppression ?\n\nCette action est irréversible.')">
                                <svg class="-ml-1 mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                                Exécuter la suppression
                            </button>
                        </form>
                    </div>
                </div>
                @endif

                {{-- Timeline --}}
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Historique</h3>
                    </div>
                    <div class="px-6 py-6">
                        <div class="flow-root">
                            <ul class="-mb-8">
                                {{-- Création --}}
                                <li>
                                    <div class="relative pb-8">
                                        <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <p class="text-sm text-gray-900">
                                                    Demande créée par <span class="font-medium">{{ $deletionRequest->requestedBy->name ?? 'N/A' }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    {{ $deletionRequest->created_at->format('d/m/Y à H:i') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </li>

                                {{-- Approbation/Rejet --}}
                                @if($deletionRequest->approved_at)
                                <li>
                                    <div class="relative pb-8">
                                        @if(!$deletionRequest->completed_at)
                                            <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200" aria-hidden="true"></span>
                                        @endif
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full {{ $deletionRequest->status === 'rejected' ? 'bg-red-500' : 'bg-green-500' }} flex items-center justify-center ring-8 ring-white">
                                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        @if($deletionRequest->status === 'rejected')
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        @else
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        @endif
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <p class="text-sm text-gray-900">
                                                    {{ $deletionRequest->status === 'rejected' ? 'Rejetée' : 'Approuvée' }} par
                                                    <span class="font-medium">{{ $deletionRequest->approvedBy->name ?? 'N/A' }}</span>
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    {{ $deletionRequest->approved_at->format('d/m/Y à H:i') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @endif

                                {{-- Exécution --}}
                                @if($deletionRequest->completed_at)
                                <li>
                                    <div class="relative">
                                        <div class="relative flex space-x-3">
                                            <div>
                                                <span class="h-8 w-8 rounded-full bg-blue-500 flex items-center justify-center ring-8 ring-white">
                                                    <svg class="h-5 w-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                </span>
                                            </div>
                                            <div class="min-w-0 flex-1 pt-1.5">
                                                <p class="text-sm text-gray-900">
                                                    Suppression exécutée
                                                </p>
                                                <p class="text-xs text-gray-500 mt-1">
                                                    {{ $deletionRequest->completed_at->format('d/m/Y à H:i') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>

                {{-- Informations backup --}}
                @if($deletionRequest->backup_info)
                <div class="bg-white rounded-xl shadow-sm border border-gray-100">
                    <div class="px-6 py-5 border-b border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900">Backup</h3>
                    </div>
                    <div class="px-6 py-6">
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-700">
                                        Backup créé avec succès
                                    </p>
                                    @if(isset($deletionRequest->backup_info['backup_id']))
                                        <p class="text-xs text-green-600 mt-1">
                                            ID: {{ $deletionRequest->backup_info['backup_id'] }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
