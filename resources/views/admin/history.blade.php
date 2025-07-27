{{-- resources/views/admin/some_view.blade.php --}}

@extends('layouts.app') {{-- Adaptez à votre layout --}}

@section('content')
<div class="container">
    <h1>Administration - Archivage</h1>

    {{-- Affichage des messages Flash (Succès, Erreur, Info) --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif
     @if(session('warning'))
        <div class="alert alert-warning">
            {{ session('warning') }}
        </div>
    @endif
    @if(session('info'))
        <div class="alert alert-info">
            {{ session('info') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">Archiver les Données de Performance</div>
        <div class="card-body">
            <p>
                Cliquez sur le bouton ci-dessous pour copier les données des périodes les plus récentes
                de la table <code>level_current_tests</code> vers la table d'historique
                <code>level_current_test_history</code>.
            </p>
            <p>
                Seules les périodes plus récentes que la dernière période déjà archivée seront traitées.
            </p>

            {{-- Le formulaire pour envoyer la requête POST --}}
            <form method="POST" action="{{ route('archive.levelTests') }}" onsubmit="return confirm('Êtes-vous sûr de vouloir lancer l\'archivage ? Cette action peut prendre du temps.');">
                @csrf {{-- Protection CSRF essentielle --}}
                <button type="submit" class="btn btn-primary">
                    Lancer l'Archivage
                </button>
            </form>
        </div>
    </div>

</div>
@endsection
