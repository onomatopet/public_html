



{{-- resources/views/admin/bonuses/show.blade.php --}}

@extends('layouts.admin')

@section('title', 'Détails du bonus')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="px-4 sm:px-6 lg:px-8">
        {{-- En-tête avec fil d'Ariane --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6 no-print">
            <nav class="flex items-center text-sm">
                <a href="{{ route('admin.dashboard') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                    </svg>
                    Tableau de Bord
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <a href="{{ route('admin.bonuses.index') }}" class="text-gray-500 hover:text-gray-700 transition-colors duration-200">
                    Bonus
                </a>
                <span class="mx-2 text-gray-400">/</span>
                <span class="text-gray-700 font-medium">{{ $bonus->num }}</span>
            </nav>
        </div>

        {{-- Actions --}}
        <div class="bg-white rounded-lg shadow-sm px-6 py-4 mb-6 no-print">
            <div class="flex justify-between items-center">
                <h1 class="text-2xl font-bold text-gray-900">Reçu de bonus</h1>
                <div class="flex space-x-3">
                    <button onclick="printReceipt();" 
                            class="inline-flex items-center px-4 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-sm hover:bg-blue-700 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2z"/>
                        </svg>
                        Imprimer
                    </button>
                    <a href="{{ route('admin.bonuses.pdf', $bonus) }}"
                       target="_blank"
                       class="inline-flex items-center px-4 py-2 bg-green-600 text-white font-semibold rounded-lg shadow-sm hover:bg-green-700 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                        </svg>
                        Télécharger PDF
                    </a>
                    <a href="{{ route('admin.bonuses.index') }}"
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white font-semibold rounded-lg shadow-sm hover:bg-gray-700 transition-colors duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/>
                        </svg>
                        Retour à la liste
                    </a>
                </div>
            </div>
        </div>

        {{-- Container du reçu --}}
        <div class="web-container print-area" style="max-width: 1200px; margin: 0 auto;">
            <link rel="stylesheet" href="https://eternalcongo.com/public/assets/invoice/web/modern-normalize.css">
            <link rel="stylesheet" href="https://eternalcongo.com/public/assets/invoice/web/web-base.css">
            <link rel="stylesheet" href="https://eternalcongo.com/public/assets/invoice//invoice.css">
            
            <style>
                @media print{
                    .boutonPrint {display: none;}
                }
            </style>
            
            <div class="logo-container">
                <table class="invoice-info-container">
                    <tr>
                        <td>
                            <img style="height: 38px" src="{{ asset('assets/invoice/img/logo.jpg') }}" onerror="this.onerror=null; this.src='https://eternalcongo.com/public/assets/invoice/img/logo.jpg';">
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
                        <td class="right">$ {{ number_format($bonus->bonus_direct / 550, 2, '.', '') }}</td>
                        <td class="right">$ {{ number_format(($bonus->bonus_indirect + $bonus->bonus_leadership) / 550, 2, '.', '') }}</td>
                        <td class="bold">$ {{ number_format(($bonus->bonus - $bonus->epargne) / 550, 2, '.', '') }}</td>
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
                        <td class="large total">{{ number_format($bonus->bonusFinal, 0, ',', ' ') }} xaf</td>
                        <td class="large"></td>
                        <td class="large"></td>
                        <td class="large total">${{ number_format($bonus->bonusFinal / 550, 2, '.', '') }}</td>
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
                    <div class="text-left text-xs text-gray-500">
                        <p>Ce document est généré automatiquement et ne nécessite pas de signature manuscrite.</p>
                        <p class="mt-1">{{ config('app.name') }} - Tous droits réservés {{ date('Y') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

<script type="text/javascript">
    function printReceipt() {
        window.print();
    }
</script>
@endsection    