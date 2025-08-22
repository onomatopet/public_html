{{-- resources/views/admin/network/imprimable.blade.php --}}

<!doctype html>
<html lang="fr">
    <head>

        <title>{{ $mainDistributor->distributeur_id }}_{{ strtoupper($mainDistributor->nom_distributeur) }}_NETWORK_STRUCTURE </title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no"/>
        <!-- Styles -->
        <link type="text/css" rel="stylesheet" href="{{ asset('public/assets/css/materialize.min.css') }}"/>
        <link href="{{ asset('public/assets/css/jquery.tabfinalTables.min.css') }}" rel="stylesheet">

    <style>

        @media print {
            html,
            body {
                margin: 0pt -10pt 0pt -10pt;
            }
        }
        html,
        body {
            margin: 0pt 5pt 0pt 5pt;
        }

        table .border td {
            border-bottom:1px solid #ccc;
            font-size:8pt;
            padding: 6pt;
            margin: 0pt;
            line-height: 1em
        }

        table .border th {
            font-size: 9pt;
            padding: 6pt;
            margin: 0pt;
            line-height: 1em;
            border-bottom:2px solid #aaa;
        }

        table h4 {
            font-family:'Times New Roman', Times, serif;
            font-size: 19pt;
            font-weight: 900;
            margin-bottom: -10pt
        }

        table h5 {
            font-family:'Times New Roman', Times, serif;
            font-size: 14pt;
            font-weight: 900;
            margin-bottom: -10pt
        }

        table h6 {
            font-family:'Times New Roman', Times, serif;
            font-size: 9pt;
            font-weight: 900;
            margin-bottom: -10pt
        }

        /* Styles pour les sous-totaux */
        .sous-total {
            background-color: #f0f0f0;
            font-weight: bold;
            border-top: 1px solid #666;
        }

        .double-ligne {
            border-bottom: 2px solid #333;
            height: 2px;
        }

    @media print{
        .boutonPrint
            {display: none;}
        }
    </style>

    </head>
    <body class="white">
<body>

<div class="col s12 m-t-sm boutonPrint">
    <button onclick="print();" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white text-sm font-medium rounded-lg hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-colors duration-200">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
        </svg>
        Imprimer
    </button>
</div>

<table align="center">
    <tr>
        <td align="center" colspan="2">
            <p>
            <h4 class="center">
                {{ strtoupper($mainDistributor->nom_distributeur) }} {{ strtoupper($mainDistributor->pnom_distributeur) }} ( {{ $mainDistributor->distributeur_id }} ) [Row: 1--{{ collect($distributeurs)->where('type', 'distributeur')->count() }}]
            </h4>
            <h5 class="center">ETERNAL Details Network Structure ({{ $period }})</h5>
            <h5 class="center">eternalcongo.com - contact@eternalcongo.com</h5>
            <h5 class="center">(Time: {{ $period }})</h5>
            </p>

        </td>
    </tr>
    <tr>
        <td align="left">
            <h6 class="left">Print time : {{ \Carbon\Carbon::now()->format('d-m-Y') }}</h6>
        </td>
        <td align="right"><h6 class="right">Details Network Structure</h6></td>
    </tr>
    <tr>
        <td colspan="2">
            <table id="example" class="border">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th></th>
                        <th>Nom & Prénom</th>
                        <th>Rang</th>
                        <th>New PV</th>
                        <th>Total PV</th>
                        <th>Cumulative PV</th>
                        <th>ID references</th>
                        <th>References Name</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($distributeurs as $item)
                        @if($item['type'] === 'distributeur')
                            <tr>
                                <td>{{ $item['distributeur_id'] }}</td>
                                <td>{{ $item['rang'] }}</td>
                                <td>{{ $item['nom_distributeur'].' '.$item['pnom_distributeur'] }}</td>
                                <td>{{ $item['etoiles'] }}</td>
                                <td>${{ $item['new_cumul'] }}</td>
                                <td>${{ $item['cumul_total'] }}</td>
                                <td>${{ $item['cumul_collectif'] }}</td>
                                <td>{{ $item['id_distrib_parent'] }}</td>
                                <td>{{ strtoupper($item['nom_parent']) }} {{ strtoupper($item['pnom_parent']) }}</td>
                            </tr>
                        @elseif($item['type'] === 'sous_total')
                            {{-- Ligne de sous-total --}}
                            {{-- Double ligne de séparation --}}
                        @endif
                    @endforeach
                </tbody>
            </table>
        </td>
    </tr>
</table>
