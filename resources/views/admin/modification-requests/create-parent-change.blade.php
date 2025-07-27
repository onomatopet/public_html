{{-- resources/views/admin/modification-requests/create-grade-change.blade.php --}}
@extends('layouts.admin')

@section('title', 'Demande de changement de grade')

@section('content')
<div class="container-fluid max-w-4xl">
    <div class="bg-white shadow rounded-lg">
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-semibold text-gray-900">Demande de changement de grade</h1>
        </div>

        {{-- Informations du distributeur --}}
        <div class="p-6 bg-gray-50">
            <h2 class="text-lg font-medium text-gray-900 mb-4">Distributeur concerné</h2>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-sm text-gray-600">Nom</p>
                    <p class="font-medium">{{ $distributeur->full_name }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Matricule</p>
                    <p class="font-medium">{{ $distributeur->distributeur_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Grade actuel</p>
                    <p class="font-medium">Grade {{ $distributeur->etoiles_id }}</p>
                </div>
                <div>
                    <p class="text-sm text-gray-600">Cumul individuel</p>
                    <p class="font-medium">
                        @php
                            $currentLevel = \App\Models\LevelCurrent::where('distributeur_id', $distributeur->id)
                                                                   ->where('period', date('Y-m'))
                                                                   ->first();
                        @endphp
                        {{ $currentLevel ? number_format($currentLevel->cumul_individuel) : 'N/A' }} points
                    </p>
                </div>
            </div>
        </div>

        {{-- Formulaire --}}
        <form method="POST" action="{{ route('admin.modification-requests.store.grade-change', $distributeur) }}" class="p-6">
            @csrf

            {{-- Nouveau grade --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Nouveau grade <span class="text-red-500">*</span>
                </label>
                <select name="new_grade" id="new_grade" class="form-select w-full" required>
                    <option value="">Sélectionnez un grade</option>
                    @foreach($grades as $grade)
                        @if($grade !== $distributeur->etoiles_id)
                            <option value="{{ $grade }}" {{ old('new_grade') == $grade ? 'selected' : '' }}>
                                Grade {{ $grade }}
                            </option>
                        @endif
                    @endforeach
                </select>
                @error('new_grade')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Raison --}}
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Raison du changement <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" class="form-textarea w-full" required
                          placeholder="Expliquez pourquoi ce changement de grade est nécessaire...">{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Justification (conditionnelle) --}}
            <div id="justification-field" class="mb-6" style="display: none;">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Justification détaillée <span class="text-red-500">*</span>
                </label>
                <textarea name="justification" rows="5" class="form-textarea w-full"
                          placeholder="Ce changement de grade important nécessite une justification détaillée...">{{ old('justification') }}</textarea>
                <p class="mt-1 text-sm text-gray-600">
                    Un changement de plus de 2 grades nécessite une justification approfondie.
                </p>
                @error('justification')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Validation en temps réel --}}
            <div id="validation-result" class="mb-6" style="display: none;">
                <div class="border rounded-lg p-4">
                    <h3 class="font-medium mb-2">Analyse du changement</h3>
                    <div id="validation-content"></div>
                </div>
            </div>

            {{-- Avertissements --}}
            <div class="mb-6">
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-yellow-800 mb-2">Impact du changement</h3>
                    <ul class="text-sm text-yellow-700 space-y-1">
                        <li>• Le grade sera immédiatement mis à jour</li>
                        <li>• Les bonus futurs seront calculés avec le nouveau grade</li>
                        <li>• L'historique du changement sera conservé</li>
                        <li>• Une approbation de niveau supérieur peut être nécessaire</li>
                    </ul>
                </div>
            </div>

            {{-- Boutons --}}
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.distributeurs.show', $distributeur) }}" class="btn btn-secondary">
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
    const gradeSelect = document.getElementById('new_grade');
    const justificationField = document.getElementById('justification-field');
    const validationResult = document.getElementById('validation-result');
    const validationContent = document.getElementById('validation-content');
    const currentGrade = {{ $distributeur->etoiles_id }};

    gradeSelect.addEventListener('change', function() {
        if (!this.value) {
            validationResult.style.display = 'none';
            justificationField.style.display = 'none';
            return;
        }

        const newGrade = parseInt(this.value);
        const gradeDiff = Math.abs(newGrade - currentGrade);

        // Afficher le champ justification si nécessaire
        if (gradeDiff > 2) {
            justificationField.style.display = 'block';
            justificationField.querySelector('textarea').required = true;
        } else {
            justificationField.style.display = 'none';
            justificationField.querySelector('textarea').required = false;
        }

        // Validation en temps réel
        fetch('{{ route('admin.modification-requests.validate') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({
                type: 'grade_change',
                entity_id: {{ $distributeur->id }},
                new_value: newGrade
            })
        })
        .then(response => response.json())
        .then(data => {
            validationResult.style.display = 'block';

            let html = '';

            if (data.warnings && data.warnings.length > 0) {
                html += '<div class="text-orange-600 mb-2">';
                html += '<strong>Avertissements:</strong><ul class="list-disc list-inside">';
                data.warnings.forEach(warning => {
                    html += `<li>${warning}</li>`;
                });
                html += '</ul></div>';
            }

            if (data.impact) {
                html += '<div class="text-blue-600">';
                html += '<strong>Impact:</strong><ul class="list-disc list-inside">';
                if (data.impact.children_with_higher_grade) {
                    html += `<li>${data.impact.children_with_higher_grade} enfant(s) ont un grade supérieur</li>`;
                }
                if (data.impact.bonus_recalculation) {
                    html += '<li>Les bonus devront être recalculés</li>';
                }
                html += '</ul></div>';
            }

            if (data.justification_required) {
                html += '<div class="text-red-600 mt-2">';
                html += '<strong>Justification requise pour ce changement important</strong>';
                html += '</div>';
            }

            validationContent.innerHTML = html;
        })
        .catch(error => {
            console.error('Erreur validation:', error);
            validationResult.style.display = 'none';
        });
    });
});
</script>
@endpush
@endsection
