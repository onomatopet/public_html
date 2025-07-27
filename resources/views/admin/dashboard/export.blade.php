{{-- resources/views/admin/dashboard/export.blade.php --}}

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard Export - {{ $period }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .kpi-box {
            border: 1px solid #ddd;
            padding: 10px;
            margin-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Rapport Dashboard MLM</h1>
        <h2>Période : {{ \Carbon\Carbon::createFromFormat('Y-m', $period)->format('F Y') }}</h2>
        <p>Généré le : {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <h3>Indicateurs Clés de Performance</h3>
    @foreach($dashboardData['kpis'] as $key => $kpi)
        <div class="kpi-box">
            <strong>
                @if($key === 'total_revenue') Chiffre d'affaires
                @elseif($key === 'active_distributors') Distributeurs actifs
                @elseif($key === 'average_basket') Panier moyen
                @elseif($key === 'total_points') Points totaux
                @endif
            </strong>: {{ $kpi['formatted'] }}
            ({{ $kpi['change'] > 0 ? '+' : '' }}{{ $kpi['change'] }}% vs mois précédent)
        </div>
    @endforeach

    <!-- Ajouter d'autres sections selon les besoins -->
</body>
</html>
