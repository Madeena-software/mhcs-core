<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Clinic queue') }}</title>
    <style>
        :root {
            --ink: #123d49;
            --muted: #6f8a8b;
            --surface: #f4f8f6;
            --teal: #0d5360;
            --teal-dark: #0a3e4b;
            --yellow: #f6cf43;
            --red: #ef3b3b;
        }

        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100dvh; background: #dcebea; color: var(--ink); font-family: Arial, sans-serif; }
        .lcd-shell { width: 100%; height: 100dvh; min-height: 100dvh; margin: 0 auto; padding: clamp(0.75rem, 1.5vw, 2rem); display: flex; flex-direction: column; gap: clamp(0.75rem, 1.5vw, 1.5rem); }
        .lcd-header { display: flex; align-items: center; justify-content: space-between; gap: clamp(1rem, 2vw, 2rem); min-width: 0; padding: clamp(0.75rem, 1.5vw, 1.5rem) clamp(1rem, 2.5vw, 2.5rem); background: var(--teal-dark); color: #fff; border-radius: 1rem; box-shadow: 0 0.75rem 2rem rgb(18 61 73 / 16%); }
        .brand { display: flex; align-items: center; gap: 1rem; }
        .brand-mark { display: grid; place-items: center; width: 3.5rem; height: 3.5rem; border: 0.15rem solid #fff; border-radius: 50%; font-size: 0.7rem; font-weight: 800; letter-spacing: 0.08em; }
        .brand-kicker { margin: 0 0 0.25rem; color: #a9d9d4; font-size: 0.8rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; }
        h1, h2, p { margin: 0; }
        h1 { font-size: clamp(1.4rem, 3vw, 2.5rem); }
        .lcd-clock { text-align: right; font-size: clamp(1.4rem, 3vw, 2.7rem); font-weight: 800; letter-spacing: 0.04em; white-space: nowrap; }
        .lcd-date { margin-top: 0.3rem; color: #a9d9d4; font-size: clamp(0.75rem, 1.2vw, 1rem); text-transform: capitalize; }
        .status { margin: 0; padding: 0.75rem 1rem; border-radius: 0.6rem; background: #fff2f0; color: #a52a2a; font-size: clamp(0.85rem, 1.4vw, 1.1rem); font-weight: 700; }
        .queue-layout { display: grid; flex: 1; grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.6fr); gap: clamp(0.75rem, 1.5vw, 1.5rem); min-height: 0; }
        .current-hero, .recent-panel { min-width: 0; min-height: 0; padding: clamp(0.75rem, 1.5vw, 1.5rem); border-radius: 1rem; box-shadow: 0 0.75rem 2rem rgb(18 61 73 / 12%); }
        .current-hero { display: flex; flex-direction: column; overflow: hidden; background: var(--surface); }
        .recent-panel { overflow: hidden; background: var(--teal); color: #fff; }
        .section-heading { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: 1rem; }
        h2 { font-size: clamp(1rem, 1.8vw, 1.5rem); text-transform: uppercase; letter-spacing: 0.06em; }
        .section-heading::after { content: ''; display: block; width: 3rem; height: 0.35rem; background: var(--yellow); border-radius: 1rem; }
        .current-calls { display: grid; flex: 1; gap: 0.9rem; grid-template-rows: minmax(0, 1fr); }
        .current-calls:not(:has(.call-card-secondary)) { min-height: 0; }
        .call-card { container-type: inline-size; display: flex; align-items: center; justify-content: space-between; gap: 1rem; min-width: 0; min-height: 0; overflow: hidden; padding: clamp(0.75rem, 1.5vw, 1.5rem); border-radius: 0.75rem; }
        .call-card-primary { flex-direction: column; align-items: flex-start; justify-content: center; background: var(--red); color: #fff; }
        .call-card-secondary { background: var(--yellow); color: var(--ink); }
        .ticket-number { max-width: 100%; overflow: visible; font-size: clamp(0.75rem, calc(140cqw / var(--ticket-length, 5)), 8rem); font-weight: 900; line-height: 0.95; letter-spacing: 0.03em; white-space: nowrap; }
        .call-card-secondary .ticket-number { font-size: clamp(0.75rem, calc(100cqw / var(--ticket-length, 5)), 3.5rem); }
        .call-destination { font-size: clamp(0.95rem, 1.8vw, 1.45rem); font-weight: 700; }
        .empty { display: grid; place-items: center; min-height: 10rem; color: var(--muted); text-align: center; font-size: clamp(1rem, 1.8vw, 1.4rem); font-weight: 700; }
        .recent-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: clamp(0.6rem, 1.2vw, 1rem); }
        .recent-grid .call-card { min-height: clamp(7rem, 11vw, 10rem); flex-direction: column; align-items: flex-start; justify-content: space-between; }
        .recent-grid .ticket-number { font-size: clamp(0.75rem, calc(100cqw / var(--ticket-length, 5)), 3.8rem); }
        .recent-grid .call-destination { font-size: clamp(0.75rem, 1.2vw, 1rem); }
        .recent-grid .call-card:nth-child(3n + 1) { background: var(--yellow); color: var(--ink); }
        .recent-grid .call-card:nth-child(3n + 2) { background: #f4f8f6; color: var(--ink); }
        .recent-grid .call-card:nth-child(3n) { background: var(--teal-dark); color: #fff; }
        .recent-grid .empty { grid-column: 1 / -1; color: #d4eeee; }

        @media (max-width: 900px) {
            .queue-layout { grid-template-columns: 1fr; }
        }

        @media (orientation: portrait) {
            .queue-layout { grid-template-columns: 1fr; overflow: auto; }
        }

        @media (max-width: 560px) {
            .lcd-header { align-items: flex-start; flex-direction: column; }
            .lcd-clock { text-align: left; }
            .recent-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
<main class="lcd-shell">
    <header class="lcd-header">
        <div class="brand">
            <div class="brand-mark" aria-hidden="true">{{ __('MHCS Core') }}</div>
            <div>
                <p class="brand-kicker">{{ __('MHCS Core') }}</p>
                <h1>{{ __('Clinic queue') }}</h1>
            </div>
        </div>
        <div>
            <div id="lcd-clock" class="lcd-clock" aria-label="{{ __('Current time') }}"></div>
            <p id="lcd-date" class="lcd-date"></p>
        </div>
    </header>

    <p id="queue-status" class="status" role="status" hidden>{{ __('Queue disconnected — shown calls may be stale.') }}</p>

    <div class="queue-layout">
        <section class="current-hero" aria-labelledby="current-calls-title">
            <div class="section-heading">
                <h2 id="current-calls-title">{{ __('Current calls') }}</h2>
            </div>
            <div id="current-calls" class="current-calls" aria-live="polite">
                <p class="empty">{{ __('Waiting for the next call') }}</p>
            </div>
        </section>

        <section class="recent-panel" aria-labelledby="recent-calls-title">
            <div class="section-heading">
                <h2 id="recent-calls-title">{{ __('Recent calls') }}</h2>
            </div>
            <div id="recent-calls" class="recent-grid" aria-live="polite">
                <p class="empty">{{ __('No calls yet') }}</p>
            </div>
        </section>
    </div>
</main>
<script>
    const endpoint = @json(route('lcd.queue', $siteId));
    const currentCalls = document.getElementById('current-calls');
    const recentCalls = document.getElementById('recent-calls');
    const status = document.getElementById('queue-status');
    const formatters = {
        time: new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
        date: new Intl.DateTimeFormat('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }),
    };

    const updateClock = () => {
        const now = new Date();
        document.getElementById('lcd-clock').textContent = formatters.time.format(now);
        document.getElementById('lcd-date').textContent = formatters.date.format(now);
    };
    const setConnectionState = (connected) => {
        status.hidden = connected;
    };
    const text = (className, value) => {
        const node = document.createElement('span');
        node.className = className;
        node.textContent = value;
        return node;
    };
    const renderCalls = (element, entries, empty, primary = false) => {
        element.replaceChildren();
        if (!entries.length) {
            const item = document.createElement('p');
            item.className = 'empty';
            item.textContent = empty;
            element.append(item);
            return;
        }
        entries.forEach((entry, index) => {
            const item = document.createElement('article');
            item.className = `call-card ${primary && index === 0 ? 'call-card-primary' : 'call-card-secondary'}`;
            item.style.setProperty('--ticket-length', String(entry.ticket_number.length));
            item.append(text('ticket-number', entry.ticket_number), text('call-destination', entry.destination));
            element.append(item);
        });
    };
    const refresh = async () => {
        try {
            const response = await fetch(endpoint, { cache: 'no-store' });
            if (!response.ok) throw new Error('queue_refresh_failed');
            const queue = await response.json();
            renderCalls(currentCalls, queue.current, @json(__('Waiting for the next call')), true);
            renderCalls(recentCalls, queue.recent_calls, @json(__('No calls yet')));
            setConnectionState(true);
        } catch (_) {
            setConnectionState(false);
        }
    };

    updateClock();
    setInterval(updateClock, 1000);
    refresh();
    setInterval(refresh, 5000);
</script>
</body>
</html>
