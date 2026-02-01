<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Angebot {{ $project->reference ?: $project->id }}</title>
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

        /* Top accent bar - teal for offers to distinguish from invoices */
        .accent-bar {
            height: 4mm;
            background-color: #0f766e;
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
            background-color: #f0fdfa;
            border: 0.5pt solid #99f6e4;
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

        .meta-highlight {
            color: #0f766e;
            text-align: right;
            font-weight: bold;
        }

        /* Document title */
        .document-title {
            font-size: 22pt;
            font-weight: bold;
            color: #0f766e;
            letter-spacing: -0.5pt;
            margin: 6mm 0 2mm 0;
        }

        .project-title {
            font-size: 12pt;
            color: #475569;
            margin-bottom: 6mm;
            font-style: italic;
        }

        /* Description box */
        .description-box {
            background-color: #f8fafc;
            border-left: 3pt solid #0f766e;
            padding: 5mm;
            margin-bottom: 8mm;
        }

        .description-text {
            font-size: 9pt;
            color: #334155;
            white-space: pre-wrap;
            line-height: 1.6;
        }

        /* Hourly rate notice */
        .hourly-notice {
            background-color: #fefce8;
            border: 0.5pt solid #fde047;
            border-left: 3pt solid #eab308;
            padding: 4mm 5mm;
            margin-bottom: 8mm;
            font-size: 8.5pt;
        }

        .hourly-notice strong {
            color: #a16207;
        }

        .hourly-rate {
            color: #854d0e;
            font-weight: bold;
        }

        /* Items table */
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6mm;
        }

        table.items th {
            background-color: #0f766e;
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
            color: #0f766e;
        }

        .totals-table tr.total .value {
            color: #0f766e;
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

        /* Validity notice */
        .validity-box {
            background-color: #f0fdf4;
            border: 0.5pt solid #86efac;
            border-left: 3pt solid #22c55e;
            padding: 4mm 5mm;
            margin-bottom: 8mm;
            font-size: 9pt;
            color: #166534;
        }

        .validity-date {
            font-weight: bold;
            color: #15803d;
        }

        /* Notes section */
        .notes-box {
            background-color: #f8fafc;
            border-left: 3pt solid #94a3b8;
            padding: 4mm 5mm;
            margin-bottom: 8mm;
        }

        .notes-title {
            font-size: 8pt;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.3pt;
            margin-bottom: 2mm;
        }

        .notes-text {
            font-size: 9pt;
            color: #475569;
        }

        /* Terms section */
        .terms-section {
            margin-top: 8mm;
            padding-top: 5mm;
            border-top: 0.5pt solid #e2e8f0;
        }

        .terms-title {
            font-size: 9pt;
            font-weight: bold;
            color: #475569;
            margin-bottom: 2mm;
        }

        .terms-text {
            font-size: 8pt;
            color: #64748b;
            line-height: 1.6;
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
                    @if($project->client->type->value === 'company' && $project->client->company_name)
                        <div class="recipient-name">{{ $project->client->company_name }}</div>
                        @if($project->client->contact_name)
                            <div class="recipient-line">{{ $project->client->contact_name }}</div>
                        @endif
                    @else
                        <div class="recipient-name">{{ $project->client->contact_name }}</div>
                    @endif
                    <div class="recipient-line">{{ $project->client->street }}</div>
                    <div class="recipient-line">{{ $project->client->postal_code }} {{ $project->client->city }}</div>
                    @if($project->client->country && $project->client->country !== 'DE')
                        <div class="recipient-line">{{ $project->client->country }}</div>
                    @endif
                </div>
            </td>
            <td class="document-meta">
                <div class="meta-box">
                    <table class="meta-table">
                        @if($project->reference)
                        <tr>
                            <td class="meta-label">Referenz</td>
                            <td class="meta-value-bold">{{ $project->reference }}</td>
                        </tr>
                        @endif
                        <tr>
                            <td class="meta-label">Datum</td>
                            <td class="meta-value">{{ $project->offer_date ? $project->offer_date->format('d.m.Y') : now()->format('d.m.Y') }}</td>
                        </tr>
                        @if($project->offer_valid_until)
                        <tr>
                            <td class="meta-label">Gültig bis</td>
                            <td class="meta-highlight">{{ $project->offer_valid_until->format('d.m.Y') }}</td>
                        </tr>
                        @endif
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <h1 class="document-title">Angebot</h1>
    <p class="project-title">{{ $project->title }}</p>

    @if($project->description)
    <div class="description-box">
        <div class="description-text">{{ $project->description }}</div>
    </div>
    @endif

    @if($project->type->value === 'hourly')
    <div class="hourly-notice">
        <strong>Hinweis:</strong> Dieses Projekt wird auf Stundenbasis abgerechnet.
        Der Stundensatz beträgt <span class="hourly-rate">{{ number_format($project->hourly_rate, 2, ',', '.') }} €</span> (netto).
        Die endgültige Summe ergibt sich aus dem tatsächlichen Zeitaufwand.
    </div>
    @endif

    @if($project->items->count() > 0)
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
            @php $subtotal = 0; @endphp
            @foreach($project->items as $item)
            @php $lineTotal = $item->quantity * $item->unit_price; $subtotal += $lineTotal; @endphp
            <tr>
                <td class="pos">{{ $loop->iteration }}</td>
                <td class="desc">{{ $item->description }}</td>
                <td class="qty">{{ number_format($item->quantity, 2, ',', '.') }}@if($item->unit) {{ $item->unit }}@endif</td>
                <td class="price">{{ number_format($item->unit_price, 2, ',', '.') }} €</td>
                <td class="total">{{ number_format($lineTotal, 2, ',', '.') }} €</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $vatAmount = $vatRate > 0 ? $subtotal * ($vatRate / 100) : 0;
        $total = $subtotal + $vatAmount;
    @endphp

    <table class="totals-wrapper">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-content">
                <table class="totals-table">
                    @if($vatRate > 0)
                    <tr class="subtotal">
                        <td class="label">Nettobetrag</td>
                        <td class="value">{{ number_format($subtotal, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="vat">
                        <td class="label">MwSt. {{ number_format($vatRate, 0) }} %</td>
                        <td class="value">{{ number_format($vatAmount, 2, ',', '.') }} €</td>
                    </tr>
                    @endif
                    <tr class="total">
                        <td class="label">Gesamtbetrag</td>
                        <td class="value">{{ number_format($total, 2, ',', '.') }} €</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($vatRate == 0)
    <div class="small-business-notice">
        Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerregelung).
    </div>
    @endif

    <div class="page-break"></div>

    @elseif($project->type->value === 'fixed' && $project->fixed_price)
    @php
        $subtotal = $project->fixed_price;
        $vatAmount = $vatRate > 0 ? $subtotal * ($vatRate / 100) : 0;
        $total = $subtotal + $vatAmount;
    @endphp

    <table class="totals-wrapper">
        <tr>
            <td class="totals-spacer"></td>
            <td class="totals-content">
                <table class="totals-table">
                    @if($vatRate > 0)
                    <tr class="subtotal">
                        <td class="label">Festpreis (netto)</td>
                        <td class="value">{{ number_format($subtotal, 2, ',', '.') }} €</td>
                    </tr>
                    <tr class="vat">
                        <td class="label">MwSt. {{ number_format($vatRate, 0) }} %</td>
                        <td class="value">{{ number_format($vatAmount, 2, ',', '.') }} €</td>
                    </tr>
                    @endif
                    <tr class="total">
                        <td class="label">Gesamtbetrag</td>
                        <td class="value">{{ number_format($total, 2, ',', '.') }} €</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($vatRate == 0)
    <div class="small-business-notice">
        Gemäß § 19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmerregelung).
    </div>
    @endif

    <div class="page-break"></div>
    @endif

    @if($project->offer_valid_until)
    <div class="validity-box">
        Dieses Angebot ist gültig bis zum <span class="validity-date">{{ $project->offer_valid_until->format('d.m.Y') }}</span>.
        Bei Fragen stehe ich Ihnen gerne zur Verfügung.
    </div>
    @endif

    @if($project->notes)
    <div class="notes-box">
        <div class="notes-title">Anmerkungen</div>
        <div class="notes-text">{!! nl2br(e($project->notes)) !!}</div>
    </div>
    @endif

    <div class="terms-section">
        <div class="terms-title">Allgemeine Bedingungen</div>
        <div class="terms-text">
            @if($vatRate > 0)
            Alle Preise verstehen sich in Euro zuzüglich der gesetzlichen Mehrwertsteuer.
            @else
            Alle Preise verstehen sich in Euro. Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.
            @endif
            Zahlungen sind innerhalb von 14 Tagen nach Rechnungsstellung fällig.
            Mit Ihrer Auftragserteilung akzeptieren Sie diese Bedingungen.
        </div>
    </div>

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
