
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        .header {
            border-bottom: 2px solid #123DA6;
            padding-bottom: 14px;
            margin-bottom: 20px;
        }

        h1 {
            margin: 0 0 8px;
            font-size: 22px;
            color: #123DA6;
        }

        h2 {
            margin: 24px 0 10px;
            font-size: 14px;
            color: #123DA6;
        }

        .muted {
            color: #6b7280;
            line-height: 1.6;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .summary td {
            border: 1px solid #e5e7eb;
            padding: 9px;
        }

        .summary td:first-child {
            width: 65%;
            font-weight: bold;
            background: #f9fafb;
        }

        table.report-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }

        .report-table th {
            background: #123DA6;
            color: #ffffff;
            text-align: left;
            font-weight: bold;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #e5e7eb;
            padding: 7px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .report-table tr:nth-child(even) td {
            background: #f9fafb;
        }

        .footer {
            margin-top: 24px;
            padding-top: 10px;
            border-top: 1px solid #e5e7eb;
            color: #6b7280;
            font-size: 9px;
        }

        .page-number {
            position: fixed;
            bottom: -15px;
            right: 0;
            color: #6b7280;
            font-size: 9px;
        }

        .page-number:after {
            content: "Page " counter(page);
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>

        <div class="muted">
            <strong>Period:</strong> {{ $period }}<br>
            <strong>Generated:</strong> {{ $generatedAt }}<br>
            <strong>Currency:</strong> Lesotho loti (M)
        </div>
    </div>

    @if (!empty($summary))
        <h2>Report Summary</h2>

        <table class="summary">
            <tbody>
                @foreach ($summary as $label => $value)
                    <tr>
                        <td>{{ $label }}</td>
                        <td>{{ $value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @foreach ($sections as $section)
        <h2>{{ $section['heading'] }}</h2>

        <table class="report-table">
            @if (!empty($section['headers']))
                <thead>
                    <tr>
                        @foreach ($section['headers'] as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
            @endif

            <tbody>
                @forelse ($section['rows'] as $row)
                    <tr>
                        @foreach ($row as $value)
                            <td>{{ $value }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ max(count($section['headers']), 1) }}">
                            No records found for this period.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    @endforeach

    <div class="footer">
        Net result is income minus expenses. It does not necessarily represent
        cash currently available in your accounts. Opening balances are separate
        from income.
    </div>

    <div class="page-number"></div>
</body>
</html>
