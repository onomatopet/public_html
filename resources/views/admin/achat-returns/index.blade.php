{{-- resources/views/admin/achat-returns/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Gestion des Retours et Annulations')

@section('content')
<div class="container-fluid">
    {{-- En-tête avec statistiques --}}
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-gray-900">Gestion des Retours et Annulations</h1>
            </div>
        </div>
        
        {{-- Statistiques --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 p-6">
            <div class="bg-yellow-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-yellow-800">En attente</h3>
                        <p class="text-2xl font-semibold text-yellow-900">{{ $stats['pending'] }}</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-green-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-green-800">Approuvées</h3>
                        <p class="text-2xl font-semibold text-green-900">{{ $stats['approved'] }}</p>
                    </div>
                </div>
            </div>
            
            <div class="bg-blue-50 rounded-lg p-4">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-8 w-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-sm font-medium text-blue-800">Ce mois</h3>
                        <p class="text-2xl font-semibold text-blue-900">{{ $stats['total_month'] }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filtres --}}
    <div class="bg-white shadow rounded-lg mb-6 p-6">
        <form method="GET" action="{{ route('admin.achat-returns.index') }}" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Statut</label>
                <select name="status" class="form-select w-full">
                    <option value="">Tous les statuts</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>En attente</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approuvée</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejetée</option>
                    <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Exécutée</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Type</label>
                <select name="type" class="form-select w-full">
                    <option value="">Tous les types</option>
                    <option value="cancellation" {{ request('type') === 'cancellation' ? 'selected' : '' }}>Annulation</option>
                    <option value="return" {{ request('type') === 'return' ? 'selected' : '' }}>Retour complet</option>
                    <option value="partial_return" {{ request('type') === 'partial_return' ? 'selected' : '' }}>Retour partiel</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Période</label>
                <input type="month" name="period" value="{{ request('period') }}" class="form-input w-full">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="btn btn-primary w-full">Filtrer</button>
            </div>
        </form>
    </div>

    {{-- Liste des demandes --}}
    <div class="bg-white shadow rounded-lg">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Achat</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Statut</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Montant</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Demandé par</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($requests as $request)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            #{{ $request->id }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <a href="{{ route('admin.achats.show', $request->achat) }}" class="text-blue-600 hover:text-blue-800">
                                Achat #{{ $request->achat->id }}
                            </a>
                            <br>
                            <span class="text-xs">{{ $request->achat->distributeur->full_name ?? 'N/A' }}</span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                {{ $request->type === 'cancellation' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $request->type === 'return' ? 'bg-orange-100 text-orange-800' : '' }}
                                {{ $request->type === 'partial_return' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                {{ $request->getTypeLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $request->getStatusColor() }}-100 text-{{ $request->getStatusColor() }}-800">
                                {{ $request->getStatusLabel() }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ number_format($request->amount_to_refund, 2) }} FCFA
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $request->requestedBy->name }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            {{ $request->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                            <a href="{{ route('admin.achat-returns.show', $request) }}" class="text-indigo-600 hover:text-indigo-900">
                                Voir
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="px-6 py-4 text-center text-gray-500">
                            Aucune demande de retour/annulation trouvée.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        {{-- Pagination --}}
        <div class="px-6 py-4 border-t border-gray-200">
            {{ $requests->links() }}
        </div>
    </div>
</div>
@endsection

{{-- resources/views/admin/achat-returns/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Demande de Retour/Annulation')

@section('content')
<div class="container-fluid max-w-4xl">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-900">Demande de Retour/Annulation</h1>
        </div>
        
        {{-- Informations de l'achat --}}
        <div class="p-6 bg-gray-50">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Informations de l'achat</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Référence</p>
                    <p class="font-medium">Achat #{{ $achat->id }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Distributeur</p>
                    <p class="font-medium">{{ $achat->distributeur->full_name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Produit</p>
                    <p class="font-medium">{{ $achat->product->nom_produit }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Quantité</p>
                    <p class="font-medium">{{ $achat->qt }} unités</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Montant total</p>
                    <p class="font-medium">{{ number_format($achat->montant_total_ligne, 2) }} FCFA</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Date d'achat</p>
                    <p class="font-medium">{{ $achat->created_at->format('d/m/Y H:i') }}</p>
                </div>
            </div>
        </div>
        
        {{-- Formulaire --}}
        <form method="POST" action="{{ route('admin.achat-returns.store', $achat) }}" class="p-6">
            @csrf
            
            {{-- Type de demande --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">Type de demande</label>
                <div class="space-y-4">
                    <label class="flex items-start cursor-pointer">
                        <input type="radio" name="type" value="cancellation" class="mt-1 mr-3" required>
                        <div>
                            <p class="font-medium">Annulation complète</p>
                            <p class="text-sm text-gray-600">Annuler l'intégralité de l'achat</p>
                        </div>
                    </label>
                    
                    <label class="flex items-start cursor-pointer">
                        <input type="radio" name="type" value="return" class="mt-1 mr-3" required>
                        <div>
                            <p class="font-medium">Retour complet</p>
                            <p class="text-sm text-gray-600">Retourner tous les produits</p>
                        </div>
                    </label>
                    
                    <label class="flex items-start cursor-pointer">
                        <input type="radio" name="type" value="partial_return" class="mt-1 mr-3" required>
                        <div>
                            <p class="font-medium">Retour partiel</p>
                            <p class="text-sm text-gray-600">Retourner une partie des produits</p>
                        </div>
                    </label>
                </div>
            </div>
            
            {{-- Quantité pour retour partiel --}}
            <div class="mb-6" id="quantity_field" style="display: none;">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Quantité à retourner
                </label>
                <input type="number" name="quantity_to_return" min="1" max="{{ $achat->qt - $achat->qt_retournee }}" 
                       class="form-input w-full" placeholder="Nombre d'unités à retourner">
                <p class="mt-1 text-sm text-gray-600">
                    Maximum : {{ $achat->qt - $achat->qt_retournee }} unités
                </p>
            </div>
            
            {{-- Raison --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Raison de la demande <span class="text-red-500">*</span>
                </label>
                <select name="reason" class="form-select w-full mb-2" required>
                    <option value="">Sélectionnez une raison</option>
                    <option value="Produit défectueux">Produit défectueux</option>
                    <option value="Erreur de commande">Erreur de commande</option>
                    <option value="Client insatisfait">Client insatisfait</option>
                    <option value="Doublon de commande">Doublon de commande</option>
                    <option value="Problème de livraison">Problème de livraison</option>
                    <option value="Autre">Autre (préciser dans les notes)</option>
                </select>
            </div>
            
            {{-- Notes --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Notes additionnelles
                </label>
                <textarea name="notes" rows="3" class="form-textarea w-full" 
                          placeholder="Informations complémentaires..."></textarea>
            </div>
            
            {{-- Avertissements --}}
            <div class="mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-yellow-800 mb-2">Avertissements</h3>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• Cette action peut affecter les calculs de bonus et les grades</li>
                        <li>• Une approbation sera nécessaire avant l'exécution</li>
                        <li>• Les points et cumuls seront ajustés automatiquement</li>
                    </ul>
                </div>
            </div>
            
            {{-- Boutons --}}
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.achats.show', $achat) }}" class="btn btn-secondary">
                    Annuler
                </a>
                <button type="submit" class="btn btn-primary">
                    Soumettre la demande
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const quantityField = document.getElementById('quantity_field');
    
    typeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'partial_return') {
                quantityField.style.display = 'block';
                quantityField.querySelector('input').required = true;
            } else {
                quantityField.style.display = 'none';
                quantityField.querySelector('input').required = false;
            }
        });
    });
});
</script>
@endpush
@endsection