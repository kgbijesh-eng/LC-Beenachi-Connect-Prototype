<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #111827;
            margin: 28px;
        }

        .header {
            text-align: center;
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
            margin-bottom: 24px;
        }

        .club-name {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 4px;
        }

        .subtitle {
            font-size: 14px;
            color: #374151;
        }

        .receipt-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
            text-transform: uppercase;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
        }

        .label {
            width: 35%;
            font-weight: bold;
            background: #f3f4f6;
        }

        .amount {
            font-size: 17px;
            font-weight: bold;
        }

        .footer {
            margin-top: 32px;
            font-size: 11px;
            color: #4b5563;
            border-top: 1px solid #d1d5db;
            padding-top: 10px;
        }

        .signature {
            margin-top: 42px;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="club-name">{{ $club?->name ?? 'Lions Club' }}</div>
        <div class="subtitle">
            Club Code: {{ $club?->code ?? 'N/A' }}
            @if($club?->district)
                | District: {{ $club->district }}
            @endif
        </div>
    </div>

    <div class="receipt-title">Payment Receipt</div>

    <table>
        <tr>
            <td class="label">Receipt Number</td>
            <td>{{ $receipt->receipt_number }}</td>
        </tr>
        <tr>
            <td class="label">Receipt Date</td>
            <td>{{ $receipt->receipt_date?->toDateString() ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Financial Year</td>
            <td>{{ $financialYear ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Receipt Status</td>
            <td>{{ ucfirst((string) $receipt->status) }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="label">Member Name</td>
            <td>{{ $member?->full_name ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Member Code</td>
            <td>{{ $member?->member_code ?? 'N/A' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="label">Payment Amount</td>
            <td class="amount">INR {{ $payment ? number_format((float) $payment->amount, 2, '.', ',') : '0.00' }}</td>
        </tr>
        <tr>
            <td class="label">Payment Date</td>
            <td>{{ $payment?->payment_date?->toDateString() ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Payment Mode</td>
            <td>{{ $payment?->payment_mode ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Reference Number</td>
            <td>{{ $payment?->reference_number ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="label">Payment Notes</td>
            <td>{{ $payment?->notes ?? 'N/A' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="label">Issued By</td>
            <td>{{ $issuedBy?->name ?? ('User #'.$receipt->issued_by_user_id) }}</td>
        </tr>
        <tr>
            <td class="label">Generated At</td>
            <td>{{ $receipt->created_at?->toDateTimeString() ?? 'N/A' }}</td>
        </tr>
    </table>

    <div class="signature">
        Authorized Signatory
    </div>

    <div class="footer">
        This is a system-generated, non-editable receipt generated from LC Beenachi Connect backend records.
        It does not create a new payment, modify ledger entries, cancel payments, reverse payments, correct receipts, or reissue receipts.
    </div>
</body>
</html>