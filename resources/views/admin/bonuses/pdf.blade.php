<!doctype html>
<html class="no-js" lang="">
<head>
    <meta charset="utf-8">
    <title>BONUS - {{ $distributeur->distributeur_id }} {{ strtoupper($distributeur->full_name) }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <style>
        /* Styles de base */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
            background-color: #fff;
        }
        
        /* Container principal */
        .web-container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Logo container */
        .logo-container {
            background-color: #ffffff;
            padding: 24px;
            margin-bottom: 2px;
            border-radius: 12px 12px 0 0;
        }
        
        .logo-wrapper {
            display: block;
            width: 100%;
        }
        
        .logo-wrapper img {
            height: 38px;
        }
        
        /* Invoice info container */
        .invoice-info-container {
            background-color: #ffffff;
            padding: 24px;
            margin-bottom: 2px;
            font-size: 12px;
        }
        
        .invoice-info-container table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .invoice-info-container td {
            padding: 4px 0;
            vertical-align: top;
        }
        
        .client-name {
            font-size: 22px;
            font-weight: 700;
        }
        
        .large {
            font-size: 16px;
        }
        
        .total {
            color: #5bb4ff;
            font-weight: 700;
        }
        
        /* Line items container */
        .line-items-container {
            background-color: #ffffff;
            padding: 24px;
            margin-bottom: 2px;
        }
        
        .line-items-container table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .line-items-container thead th {
            color: #595959;
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 0.1em;
            padding: 12px 0;
            border-bottom: 2px solid #eae8e4;
            text-align: left;
        }
        
        .line-items-container tbody td {
            padding: 24px 0 12px 0;
            font-weight: 400;
        }
        
        .line-items-container tbody tr:last-child td {
            padding-bottom: 24px;
            border-bottom: 2px solid #eae8e4;
        }
        
        .line-items-container .right {
            text-align: right;
        }
        
        .line-items-container .bold {
            font-weight: 600;
        }
        
        .heading-quantity {
            width: 48px;
        }
        
        .heading-price {
            text-align: right;
            width: 96px;
        }
        
        .has-bottom-border {
            padding-top: 0;
            border-radius: 0;
        }
        
        .has-bottom-border table tbody {
            border-bottom: none;
        }
        
        .has-bottom-border table tbody td {
            border-bottom: none;
        }
        
        /* Footer */
        .footer {
            margin-top: 32px;
            text-align: center;
            font-size: 12px;
            color: #595959;
        }
        
        .footer-info {
            margin-bottom: 8px;
        }
        
        .footer-thanks {
            font-weight: 600;
        }
        
        .footer-thanks img {
            display: inline-block;
            position: relative;
            top: 1px;
            width: 16px;
            margin-right: 4px;
        }
        
        /* Impression */
        @media print {
            .boutonPrint {
                display: none !important;
            }
            .web-container {
                padding: 0;
            }
        }
        
        @page {
            margin: 20mm;
            size: A4;
        }
    </style>
</head>
<body>
    <div class="web-container">
        <div class="logo-container">
            <div class="logo-wrapper">
                <img src="{{ public_path('assets/invoice/img/logo.jpg') }}" alt="Logo">
            </div>
        </div>
        
        <div class="invoice-info-container">
            <table>
                <tr>
                    <td colspan="2" class="large total">
                        <strong>COPIE CONFORME</strong>
                    </td>
                </tr>
                <tr>
                    <td rowspan="2" class="client-name" style="width: 60%;">
                        Bulletin Bonus<br/>
                        {{ strtoupper($distributeur->full_name) }}<br/>
                        ID : {{ $distributeur->distributeur_id }}
                    </td>
                    <td style="width: 40%;">
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
                        Date: {{ $date_generation }} {{ now()->format('H:i:s') }}
                    </td>
                    <td>
                        Tel : 04 403 16 16
                    </td>
                </tr>
                <tr>
                    <td>
                        No: <strong>{{ $numero_recu }}</strong>
                    </td>
                    <td>
                        contact@eternalcongo.com
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="line-items-container">
            <table>
                <thead>
                    <tr>
                        <th class="heading-quantity">#</th>
                        <th>Période</th>
                        <th class="heading-price">Bonus Direct</th>
                        <th class="heading-price">Bonus Indirect</th>
                        <th class="heading-price">Total $</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>{{ $periode }}</td>
                        <td class="right">$ {{ number_format($details['bonus_direct'] / 550, 2, '.', '') }}</td>
                        <td class="right">$ {{ number_format(($details['bonus_indirect'] + $details['bonus_leadership']) / 550, 2, '.', '') }}</td>
                        <td class="bold right">$ {{ number_format(($details['total_brut'] - $details['epargne']) / 550, 2, '.', '') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="line-items-container has-bottom-border">
            <table>
                <thead>
                    <tr>
                        <th style="text-align: left;">Montant en XAF</th>
                        <th style="width: 20px;"></th>
                        <th style="text-align: right;">TOTAL A PAYER</th>
                        <th style="text-align: right;">Montant en Dollars</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="large total">{{ number_format($details['net_payer'], 0, ',', ' ') }} xaf</td>
                        <td></td>
                        <td></td>
                        <td class="large total right">${{ number_format($details['net_payer'] / 550, 2, '.', '') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        
        <div class="line-items-container has-bottom-border" style="border-radius: 0 0 12px 12px;">
            <table>
                <thead>
                    <tr>
                        <th style="text-align: left;">VISA DU CAISSIER</th>
                        <th style="width: 20px;"></th>
                        <th></th>
                        <th style="text-align: right;">VISA DE L'AYANT DROIT</th>
                    </tr>
                </thead>
            </table>
        </div>
        
        <div class="footer">
            <div class="footer-info">
                <span>contact@eternalcongo.com</span> |
                <span>Tel : 04 403 16 16</span> |
                <span>eternalcongo.com</span>
            </div>
            <div class="footer-thanks">
                <img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAABHNCSVQICAgIfAhkiAAAAAlwSFlzAAAA7AAAAOwBeShxvQAAABl0RVh0U29mdHdhcmUAd3d3Lmlua3NjYXBlLm9yZ5vuPBoAAAKsSURBVDiNpZPPaxNBFMc/s7ubTbLZJNukTX9YqS1UqLQgVLyIB0+CJ0H/Ai+ePHgS9OLRk+DBm3jw4kEQPOhJEDx48GAVqlhQW9Omv9L8aLqb3czuzHgIaWqCFnzwYN7Me9/PvPdmHlJKATB58eqINV49Lek+KaVAJMmnhqnV95ubQw+vzwJsCwRgMrywJJMrQjhBSAkgEEIglEQoKRO5n2tLzHxwdgJ2ATLOAgjHdnNttbmlprq1RigJiFpA1+HlQsCAyQCcpZWQ3d8nhW1LSdcKx5bCtqRk5qWwrdz3+ZUl0zQcGAOwMg6D4yZayk9m0oDCsqBjG5g2F4MdAKbF6fqQNGQmp2AqnwDNh2WDYXNx2rS9AOl5HDo7JDu3oGDR8O0TlgGlb19RSxYqwHZARIE4MB7gc0u1HnhzE3E1dXBShJNRxpNRJnwxjgOsB1ZQHQAU9AyLnhHRo5dJD6KnMaRnoGe0p6o1x5EAsD2BaFs9kwhGGYtEKUQiDCaiDCeiPBiKksz5oCXQt2yJBEAP6ND2eOsqD5/U6B/pZGy4g7u366iGRv/ePKqhkcp58GxqKzK9a13u6f1viwsU+pKMjxToDGnMLdZY/mbS/yrB/UqQ0LQfT6cPT/M7VEMTzgYA2OPJqEgJYu9WkP2TBfp2+1jfMJn/VGPhc5X3SzXCJT+zy0FCP/y4Oh2nUo/9DQCUkyN9YufQlJQS5r80uPOgzOP5CtFSnJOjIU6MhnizWOP2bIWqaVNqTFdVf/1i+9Pc2I+H2wFhOBQJRkXHjvNXM2F+r1s8eVHhyq0yiiI4dyLMhVNh9nT5sFsOt5oCQ6DHHW9jdPp2I3LlRsXwFH8K6Pd0+klHFJLTEQGEFNV+r6vF3t74hSsK8Afwm/8F/gDbrvuWy6rEnQAAAABJRU5ErkJggg==" alt="heart">
                <span>Thank you!</span>
            </div>
        </div>
    </div>
</body>
</html>