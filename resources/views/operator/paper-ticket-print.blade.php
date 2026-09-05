<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Paper ticket') }}</title>
    <style>
        /* Thermal roll 57x47P profile: 57mm nominal paper width, ~48mm safe printable area */
        @page {
            size: 57mm auto;
            margin: 0;
        }
        *, *::before, *::after {
            box-sizing: border-box;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #fff;
            color: #000;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, monospace, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        body {
            display: flex;
            justify-content: center;
        }
        .ticket-roll-container {
            width: 57mm;
            max-width: 57mm;
            margin: 0 auto;
            padding: 2mm 4.5mm 4mm 4.5mm; /* 48mm safe printable width: 57mm - (4.5mm * 2) = 48mm */
            text-align: center;
            overflow: hidden;
            word-wrap: break-word;
        }
        .ticket-site {
            font-size: 13px;
            font-weight: 700;
            line-height: 1.25;
            margin: 0 0 4px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }
        .ticket-window {
            font-size: 11px;
            line-height: 1.3;
            margin: 2px 0 6px;
        }
        .ticket-divider {
            border: none;
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .ticket-number-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin: 4px 0 0;
        }
        .ticket-number {
            font-size: 34px;
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: 0.06em;
            margin: 4px 0 8px;
        }
        .ticket-issued {
            font-size: 9px;
            line-height: 1.2;
            color: #333;
            margin: 4px 0 0;
        }
        /* Visual separator & manual-tear margin: preserves content readability without auto-cutter */
        .ticket-tear-margin {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px dashed #666;
            font-size: 8px;
            letter-spacing: 0.15em;
            text-transform: uppercase;
            color: #666;
            height: 14mm; /* dynamic content clearance ensuring manual tear does not clip ticket text */
        }
        @media print {
            html, body {
                width: 57mm;
                margin: 0;
                padding: 0;
            }
            .ticket-roll-container {
                width: 57mm;
                max-width: 57mm;
                padding: 1.5mm 4.5mm 2mm 4.5mm;
            }
            .ticket-issued {
                color: #000;
            }
            .ticket-tear-margin {
                color: #000;
                border-top: 1px dashed #000;
            }
        }
    </style>
</head>
<body>
<main class="ticket-roll-container">
    <h1 class="ticket-site">{{ $ticket['site_name'] }}</h1>
    <p class="ticket-window">{{ $ticket['schedule_starts_at'] }} – {{ $ticket['schedule_ends_at'] }}</p>
    <hr class="ticket-divider" aria-hidden="true">
    <div class="ticket-number-label">{{ __('Queue Ticket') }}</div>
    <div class="ticket-number">{{ $ticket['ticket_number'] }}</div>
    <hr class="ticket-divider" aria-hidden="true">
    @if (!empty($ticket['issued_at']))
        <p class="ticket-issued">{{ __('Issued at') }}: {{ $ticket['issued_at'] }}</p>
    @endif
    <div class="ticket-tear-margin" aria-hidden="true">-- {{ __('Tear here') }} --</div>
</main>
<script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
