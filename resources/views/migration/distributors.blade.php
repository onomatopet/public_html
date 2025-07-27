

<div class="container">
    <h1>Migration des Distributeurs</h1>

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">Générer le Script SQL d'Insertion</div>
        <div class="card-body">
            <p>
                Cliquez sur le bouton ci-dessous pour générer un fichier SQL contenant
                les instructions <code>INSERT</code> pour copier les distributeurs et leur hiérarchie
                depuis la base de données <strong>{{ config('database.connections.db_first.database') }}</strong>
                vers la table <strong>DB_second.distributeurs</strong>.
            </p>
            <p><strong>Attention :</strong> Ce processus peut prendre du temps si la hiérarchie est grande.</p>

            <form method="POST" action="{{ route('archive.levelTests') }}">
                @csrf
                <button type="submit" class="btn btn-primary">
                    Générer le Fichier SQL (.sql)
                </button>
            </form>
        </div>
    </div>
</div>

