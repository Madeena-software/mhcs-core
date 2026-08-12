<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Paper ticket') }}</title>
    <style>
        body { margin: 0; padding: 24px; color: #000; background: #fff; font-family: sans-serif; }
        main { width: 280px; margin: 0 auto; text-align: center; }
        h1 { font-size: 18px; margin: 0 0 18px; }
        p { margin: 12px 0; }
        .ticket-number { font-size: 42px; font-weight: 700; letter-spacing: .08em; margin-top: 22px; }
        @media print { body { padding: 0; } }
    </style>
</head>
<body>
<main>
    <h1>{{ $ticket['site_name'] }}</h1>
    <p>{{ $ticket['schedule_starts_at'] }} – {{ $ticket['schedule_ends_at'] }}</p>
    <p class="ticket-number">{{ $ticket['ticket_number'] }}</p>
</main>
<script>window.addEventListener('load', () => window.print());</script>
</body>
</html>
