@extends('layouts.admin')

@section('title', 'Détails du log')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        {{-- En-tête --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Détails du log #{{ $log->id }}</h1>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ $log->created_at->format('d/m/Y à H:i:s') }}
                    </p>
                </div>
                <a href="{{ route('admin.logs.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Retour à la liste
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {{-- Informations principales --}}
            <div class="lg:col-span-2 space-y-6">
                {{-- Détails de l'action --}}
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-medium text-gray-900">Détails de l'action</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div>
                            <label class="text-sm font-medium text-gray-500">Action</label>
                            <div class="mt-1 flex items-center">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium
                                    @if($log->action == 'create') bg-green-100 text-green-800
                                    @elseif($log->action == 'update') bg-blue-100 text-blue-800
                                    @elseif($log->action == 'delete') bg-red-100 text-red-800
                                    @elseif($log->action == 'login') bg-indigo-100 text-indigo-800
                                    @else bg-gray-100 text-gray-800
                                    @endif">
                                    {{ $log->getFormattedActionAttribute() }}
                                </span>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">Description</label>
                            <p class="mt-1 text-gray-900">{{ $log->description }}</p>
                        </div>

                        @if($log->subject)
                            <div>
                                <label class="text-sm font-medium text-gray-500">Objet concerné</label>
                                <div class="mt-1">
                                    <span class="text-gray-900">{{ class_basename($log->subject_type) }}</span>
                                    <span class="text-gray-500">#{{ $log->subject_id }}</span>
                                    @if($log->subject)
                                        <a href="#" class="ml-2 text-blue-600 hover:text-blue-500 text-sm">
                                            Voir l'objet →
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Données techniques --}}
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-medium text-gray-900">Données techniques</h3>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="text-sm font-medium text-gray-500">Adresse IP</label>
                                <p class="mt-1 text-gray-900 font-mono text-sm">{{ $log->ip_address ?? 'N/A' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-500">Session ID</label>
                                <p class="mt-1 text-gray-900 font-mono text-sm truncate">{{ $log->session_id ?? 'N/A' }}</p>
                            </div>
                        </div>

                        <div>
                            <label class="text-sm font-medium text-gray-500">User Agent</label>
                            <p class="mt-1 text-gray-600 text-sm break-all">{{ $log->user_agent ?? 'N/A' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Propriétés additionnelles --}}
                @if($log->properties && count($log->properties) > 0)
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-medium text-gray-900">Propriétés additionnelles</h3>
                        </div>
                        <div class="px-6 py-4">
                            <div class="bg-gray-100 rounded-md p-4 overflow-x-auto">
                                <pre class="text-sm text-gray-800">{{ json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Sidebar --}}
            <div class="space-y-6">
                {{-- Informations utilisateur --}}
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                        <h3 class="text-lg font-medium text-gray-900">Utilisateur</h3>
                    </div>
                    <div class="px-6 py-4">
                        @if($log->user)
                            <div class="flex items-center">
                                <div class="flex-shrink-0">
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-gray-600 font-medium">{{ substr($log->user->name, 0, 1) }}</span>
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm font-medium text-gray-900">{{ $log->user->name }}</p>
                                    <p class="text-sm text-gray-500">{{ $log->user->email }}</p>
                                </div>
                            </div>
                            <div class="mt-4">
                                <a href="#" class="text-sm text-blue-600 hover:text-blue-500">
                                    Voir le profil →
                                </a>
                            </div>
                        @else
                            <p class="text-gray-500">Système</p>
                        @endif
                    </div>
                </div>

                {{-- Logs similaires --}}
                @if($similarLogs->count() > 0)
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                            <h3 class="text-lg font-medium text-gray-900">Actions similaires</h3>
                            <p class="text-sm text-gray-500 mt-1">Dans les 24h</p>
                        </div>
                        <div class="divide-y divide-gray-200">
                            @foreach($similarLogs as $similar)
                                <a href="{{ route('admin.logs.show', $similar) }}" class="block px-6 py-3 hover:bg-gray-50">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-gray-900">{{ Str::limit($similar->description, 40) }}</p>
                                            <p class="text-xs text-gray-500 mt-1">{{ $similar->created_at->format('H:i:s') }}</p>
                                        </div>
                                        <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="px-6 py-4">
                        <button type="button" onclick="window.print()" class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                            </svg>
                            Imprimer
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
// Impression personnalisée
window.addEventListener('beforeprint', function() {
    document.body.classList.add('printing');
});

window.addEventListener('afterprint', function() {
    document.body.classList.remove('printing');
});
</script>
@endsection

@section('styles')
<style>
@media print {
    .printing nav,
    .printing aside,
    .printing button,
    .printing a[href] {
        display: none !important;
    }

    .printing .bg-gray-50 {
        background: white !important;
    }

    .printing .shadow-sm {
        box-shadow: none !important;
    }
}
</style>
@endsection
