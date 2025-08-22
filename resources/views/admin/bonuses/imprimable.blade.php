<!doctype html>
<html class="no-js" lang="">

<head>
  <meta charset="utf-8">
  <title>BONUS - {{ $bonus->distributeur->distributeur_id.' '.strtoupper($bonus->distributeur->full_name) }}</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link rel="stylesheet" href="{{ asset('public/assets/invoice/web/modern-normalize.css') }}">
  <link rel="stylesheet" href="{{ asset('public/assets/invoice/web/web-base.css') }}">
  <link rel="stylesheet" href="{{ asset('public/assets/invoice//invoice.css') }}">
  <script type="text/javascript" src="{{ asset('public/assets/invoice/web/scripts.js') }}"></script>


<style>
    @media print {
        /* Cache tout sauf la zone d'impression */
        body * {
            visibility: hidden;
        }

        .print-area, .print-area * {
            visibility: visible;
        }

        .print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
        }

        /* Cache spécifiquement les éléments non désirés */
        .no-print {
            display: none !important;
        }

        /* Reset des marges pour l'impression */
        @page {
            margin: 0;
            size: A4;
        }

        body {
            margin: 0;
            padding: 0;
        }

        .min-h-screen {
            min-height: auto;
        }

        .bg-gray-50 {
            background-color: white;
        }

        .px-4, .sm\:px-6, .lg\:px-8 {
            padding: 0;
        }

        .py-8 {
            padding: 0;
        }
    }
</style>

</head>
<body>

<button onclick="printReceipt();"
        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-sm hover:bg-blue-700 transition-colors duration-200">
    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"/>
    </svg>
    Imprimer
</button>

{{-- Container du reçu --}}
<div class="web-container print-area" style="max-width: 1200px; margin: 0 auto;">

    <style>
        @media print{
            .boutonPrint {display: none;}
        }
    </style>

    <div class="logo-container">
        <table class="invoice-info-container">
            <tr>
                <td>
                    <img style="height: 38px" src="{{ asset('assets/invoice/img/logo.jpg') }}" onerror="this.onerror=null; this.src='https://eternalcongo.com/public/assets/img/logo.jpg';">
                </td>
                <td>
                    {{-- Bouton d'impression retiré d'ici --}}
                </td>
            </tr>
        </table>
    </div>

    <table class="invoice-info-container">
        <tr>
            <td colspan="3" class="large total">
                <strong>COPIE CONFORME</strong>
            </td>
        </tr>
        <tr>
            <td rowspan="2" class="client-name">
                Bulletin Bonus<br/>
                {{ strtoupper($bonus->distributeur->full_name) }}<br/>
                ID : {{ $bonus->distributeur->distributeur_id }}
            </td>
            <td>
                ETERNAL CONGO SARL
            </td>
        </tr>
        <tr>
            <td>
                45, rue BAYAS POTO-POTO
            </td>
        </tr>
        <tr>
            <td>
                Date: {{ $bonus->created_at->format('Y-m-d H:i:s') }}
            </td>
            <td>
                Tel : 04 403 16 16
            </td>
        </tr>
        <tr>
            <td>
                No: <strong>{{ $bonus->num }}</strong>
            </td>
            <td>
                contact@eternalcongo.com
            </td>
        </tr>
    </table>

    <table class="line-items-container">
        <thead>
            <tr>
                <th class="heading-quantity">#</th>
                <th class="heading-description">Période</th>
                <th class="heading-price">Bonus Direct</th>
                <th class="heading-price">Bonus Indirect</th>
                <th class="heading-price">Total $</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $bonus->period }}</td>
                <td class="right">$ {{ number_format($bonus->bonus_direct, 2, '.', '') }}</td>
                <td class="right">$ {{ number_format($bonus->bonus_indirect, 2, '.', '') }}</td>
                <td class="bold">$ {{ number_format($bonus->bonus, 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="line-items-container has-bottom-border">
        <thead>
            <tr>
                <th>Montant en XAF</th>
                <th width="20"></th>
                <th class="right">TOTAL A PAYER</th>
                <th>Montant en Dollars</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="large total">{{ number_format($bonus->montant_total, 0, ',', ' ') }} xaf</td>
                <td class="large"></td>
                <td class="large"></td>
                <td class="large total">${{ number_format($bonus->bonus, 2, '.', '') }}</td>
            </tr>
        </tbody>
    </table>

    <table class="line-items-container has-bottom-border">
        <thead>
            <tr>
                <th>VISA DU CAISSIER</th>
                <th width="20"></th>
                <th></th>
                <th>VISA DE L'AYANT DROIT</th>
            </tr>
        </thead>
    </table>

    <div class="footer">
        <div class="footer-info">
            <span>contact@eternalcongo.com</span> |
            <span>Tel : 04 403 16 16</span> |
            <span>eternalcongo.com</span>
        </div>
        <div class="footer-thanks">
            <div class="text-left text-gray-500" style="font-size:12px">
                <p><span>Ce document est généré automatiquement et ne nécessite pas de signature manuscrite.</span></p>
                <p class="mt-1"><span>{{ config('app.name') }} - Tous droits réservés {{ date('Y') }}</span></p>
            </div>
        </div>
    </div>
</div>
<script type="text/javascript">
    function printReceipt() {
        window.print();
    }
</script>
