<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rechnung {{ $invoice->number }}</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 25mm !important;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            line-height: 1.5;
            color: #1e293b;
        }

        /* Top accent bar */
        .accent-bar {
            height: 4mm;
            background-color: #1e3a5f;
            margin-bottom: 8mm;
        }

        /* Page break utility */
        .page-break {
            page-break-after: always;
        }

        /* Header layout using table */
        .header-table {
            width: 100%;
            margin-bottom: 12mm;
        }

        .header-table td {
            vertical-align: top;
        }

        .sender-info {
            width: 55%;
        }

        .document-meta {
            width: 45%;
            text-align: right;
        }

        /* Sender line - compact return address */
        .sender-line {
            font-size: 7pt;
            color: #64748b;
            letter-spacing: 0.3pt;
            text-transform: uppercase;
            padding-bottom: 3mm;
            margin-bottom: 4mm;
            border-bottom: 0.5pt solid #cbd5e1;
        }

        /* Recipient address */
        .recipient {
            padding-top: 2mm;
        }

        .recipient-name {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 1.5mm;
        }

        .recipient-line {
            font-size: 9.5pt;
            color: #334155;
            margin-bottom: 1mm;
        }

        /* Document meta info box */
        .meta-box {
            background-color: #f8fafc;
            border: 0.5pt solid #e2e8f0;
            padding: 4mm;
            margin-left: auto;
            width: 70mm;
        }

        .meta-table {
            width: 100%;
        }

        .meta-table td {
            padding: 1.2mm 0;
            font-size: 8.5pt;
        }

        .meta-label {
            color: #64748b;
            text-align: left;
        }

        .meta-value {
            color: #1e293b;
            text-align: right;
            font-weight: normal;
        }

        .meta-value-bold {
            color: #0f172a;
            text-align: right;
            font-weight: bold;
        }

        /* Document title */
        .document-title {
            font-size: 22pt;
            font-weight: bold;
            color: #1e3a5f;
            letter-spacing: -0.5pt;
            margin: 6mm 0 2mm 0;
        }

        .service-period {
            font-size: 9pt;
            color: #64748b;
            margin-bottom: 8mm;
        }

        /* Items table */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }

        table.items th {
            background-color: #1e3a5f;
            color: #ffffff;
            padding: 3mm 3mm;
            text-align: left;
            font-weight: bold;
            font-size: 8pt;
            letter-spacing: 0.3pt;
            text-transform: uppercase;
        }

        table.items th.pos {
            width: 8%;
            text-align: center;
        }

        table.items th.desc {
            width: 42%;
        }

        table.items th.qty {
            width: 15%;
            text-align: center;
        }

        table.items th.price {
            width: 17%;
            text-align: right;
        }

        table.items th.total {
            width: 18%;
            text-align: right;
        }

        table.items td {
            padding: 3.5mm 3mm;
            vertical-align: top;
            border-bottom: 0.5pt solid #e2e8f0;
        }

        table.items td.pos {
            text-align: center;
            color: #64748b;
            font-size: 9pt;
        }

        table.items td.desc {
            color: #1e293b;
        }

        table.items td.qty {
            text-align: center;
            color: #475569;
        }

        table.items td.price {
            text-align: right;
            color: #475569;
        }

        table.items td.total {
            text-align: right;
            color: #1e293b;
            font-weight: bold;
        }

        table.items tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Totals section */
        .totals-wrapper {
            width: 100%;
            margin-bottom: 8mm;
        }

        .totals-spacer {
            width: 50%;
        }

        .totals-content {
            width: 50%;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table td {
            padding: 2mm 0;
            font-size: 9.5pt;
        }

        .totals-table .label {
            color: #64748b;
            text-align: left;
        }

        .totals-table .value {
            color: #1e293b;
            text-align: right;
        }

        .totals-table tr.subtotal td {
            padding-bottom: 3mm;
        }

        .totals-table tr.vat td {
            border-bottom: 0.5pt solid #e2e8f0;
            padding-bottom: 3mm;
        }

        .totals-table tr.total td {
            padding-top: 3mm;
            font-size: 13pt;
            font-weight: bold;
        }

        .totals-table tr.total .label {
            color: #1e3a5f;
        }

        .totals-table tr.total .value {
            color: #1e3a5f;
        }

        /* Small business notice */
        .small-business-notice {
            background-color: #f0f9ff;
            border-left: 3pt solid #0284c7;
            padding: 4mm 5mm;
            margin-bottom: 8mm;
            font-size: 8.5pt;
            color: #0369a1;
        }

        /* Payment info */
        .payment-section {
            background-color: #f8fafc;
            border: 0.5pt solid #e2e8f0;
            padding: 5mm;
            margin-bottom: 8mm;
        }

        .payment-title {
            font-size: 10pt;
            font-weight: bold;
            color: #1e3a5f;
            margin-bottom: 3mm;
            padding-bottom: 2mm;
            border-bottom: 0.5pt solid #e2e8f0;
        }

        .payment-table {
            width: 100%;
        }

        .payment-table td {
            padding: 1.5mm 0;
            font-size: 9pt;
        }

        .payment-table .pay-label {
            width: 25%;
            color: #64748b;
        }

        .payment-table .pay-value {
            color: #1e293b;
        }

        .payment-table .pay-value-bold {
            color: #1e3a5f;
            font-weight: bold;
        }

        /* Notes section */
        .notes {
            background-color: #fffbeb;
            border-left: 3pt solid #f59e0b;
            padding: 4mm 5mm;
            margin-bottom: 8mm;
            font-size: 9pt;
            color: #92400e;
        }

        /* Footer text */
        .footer-text {
            font-size: 8.5pt;
            color: #64748b;
            margin-top: 6mm;
        }

        /* Page footer */
        .page-footer {
            position: fixed;
            bottom: -20mm;
            left: 0;
            right: 0;
            font-size: 7.5pt;
            color: #94a3b8;
            border-top: 0.5pt solid #e2e8f0;
            padding-top: 3mm;
        }

        .footer-table {
            width: 100%;
        }

        .footer-table td {
            vertical-align: top;
        }

        .footer-left {
            width: 40%;
            text-align: left;
        }

        .footer-center {
            width: 30%;
            text-align: center;
        }

        .footer-right {
            width: 30%;
            text-align: right;
        }

        .footer-label {
            font-size: 6pt;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
        }

        .footer-value {
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="accent-bar"></div>

    <table class="header-table">
        <tr>
            <td class="sender-info">
                <div class="sender-line">
                    {{ $business['name'] }} · {{ $business['street'] }} · {{ $business['postal_code'] }} {{ $business['city'] }}
                </div>
                <div class="recipient">
                    @if($invoice->client->type->value === 'company' && $invoice->client->company_name)
                        <div class="recipient-name">{{ $invoice->client->company_name }}</div>
                        @if($invoice->client->contact_name)
                            <div class="recipient-line">{{ $invoice->client->contact_name }}</div>
                        @endif
                    @else
                        <div class="recipient-name">{{ $invoice->client->contact_name }}</div>
                    @endif
                    <div class="recipient-line">{{ $invoice->client->street }}</div>
                    <div class="recipient-line">{{ $invoice->client->postal_code }} {{ $invoice->client->city }}</div>
                    @if($invoice->client->country && $invoice->client->country !== 'DE')
                        <div class="recipient-line">{{ $invoice->client->country }}</div>
                    @endif
                </div>
            </td>
            <td class="document-meta">
                <div class="meta-box">
                    <table class="meta-table">
                        <tr>
                            <td class="meta-label">Rechnungsnr.</td>
                            <td class="meta-value-bold">{{ $invoice->number }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Datum</td>
                            <td class="meta-value">{{ $invoice->issued_at->format('d.m.Y') }}</td>
                        </tr>
                        @if($invoice->project && $invoice->project->reference)
                        <tr>
                            <td class="meta-label">Projekt</td>
                            <td class="meta-value">{{ $invoice->project->reference }}</td>
                        </tr>
                        @endif
                        @if($invoice->client->vat_id)
                        <tr>
                            <td class="meta-label">USt-IdNr.</td>
                            <td class="meta-value">{{ $invoice->client->vat_id }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <h1 class="document-title">Rechnung</h1>

    @if($invoice->service_period_start || $invoice->service_period_end)
    <p class="service-period">
        Leistungszeitraum:
        @if($invoice->service_period_start && $invoice->service_period_end)
            {{ $invoice->service_period_start->format('d.m.Y') }} – {{ $invoice->service_period_end->format('d.m.Y') }}
        @elseif($invoice->service_period_start)
            ab {{ $invoice->service_period_start->format('d.m.Y') }}
        @else
            bis {{ $invoice->service_period_end->format('d.m.Y') }}
        @endif
    </p>
    @endif

    <table class="items">
        <thead>
            <tr>
                <th class="pos">Pos.</th>
                <th class="desc">Beschreibung</th>
                <th class="qty">Menge</th>
                <th class="price">Einzelpreis</th>
                <th class="total">Gesamt</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td class="pos">{{ $loop->iteration }}</td>
                <td class="desc">{{ $item->description }}</td>
                <td class="qty">{{ number_format($item->quantity, 2, ',', '.') }}@if($item->unit) {{ $item->unit }}@endif</td>
                <td class="price">{{ number_format($item->unit_price, 2, ',', '.') }} €</td>
                <td class="total">{{ number_format($item->total, 2, ',', '.') }} €</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-wrapper">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-content">
                <table class="totals-table">
                    @if($invoice->vat_rate > 0)
                    <tr class="subtotal">
                        <td class="label">Nettobetrag</td>
                        <td class="value">{{ number_format($invoice->subtotal, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="vat">
                        <td class="label">MwSt. {{ number_format($invoice->vat_rate, 0) }} %</td>
                        <td class="value">{{ number_format($invoice->vat_amount, 2, ',', '.') }} €</td>
                    </tr>
                    @endif
                    <tr class="total">
                        <td class="label">Gesamtbetrag</td>
                        <td class="value">{{ number_format($invoice->total, 2, ',', '.') }} €</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($invoice->vat_rate == 0)
    <div class="small-business-notice">
        Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerregelung).
    </div>
    @endif

    <div class="page-break"></div>

    <div class="payment-section">
        <div class="payment-title">Zahlungsinformationen</div>
        <table class="payment-table">
            <tr>
                <td class="pay-label">Fällig bis</td>
                <td class="pay-value-bold">{{ $invoice->due_at->format('d.m.Y') }}</td>
            </tr>
            @if($business['bank_name'])
            <tr>
                <td class="pay-label">Bank</td>
                <td class="pay-value">{{ $business['bank_name'] }}</td>
            </tr>
            @endif
            @if($business['iban'])
            <tr>
                <td class="pay-label">IBAN</td>
                <td class="pay-value">{{ $business['iban'] }}</td>
            </tr>
            @endif
            @if($business['bic'])
            <tr>
                <td class="pay-label">BIC</td>
                <td class="pay-value">{{ $business['bic'] }}</td>
            </tr>
            @endif
        </table>
    </div>

    @if($invoice->notes)
    <div class="notes">
        {!! nl2br(e($invoice->notes)) !!}
    </div>
    @endif

    @if($invoice->footer_text)
    <div class="footer-text">
        {!! nl2br(e($invoice->footer_text)) !!}
    </div>
    @endif

    <div class="page-footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    <span class="footer-value">{{ $business['name'] }}</span><br>
                    @if($business['tax_number'])<span class="footer-label">Steuernr.</span> <span class="footer-value">{{ $business['tax_number'] }}</span>@endif
                    @if($business['vat_id']) · <span class="footer-label">USt-IdNr.</span> <span class="footer-value">{{ $business['vat_id'] }}</span>@endif
                </td>
                <td class="footer-center">
                    @if($business['email'])<span class="footer-value">{{ $business['email'] }}</span>@endif
                </td>
                <td class="footer-right">
                    @if($business['phone'])<span class="footer-value">{{ $business['phone'] }}</span>@endif
                    @if($business['website'])<br><span class="footer-value">{{ $business['website'] }}</span>@endif
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
