<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clinic queue</title>
    <style>
        body { margin: 0; background: #0f172a; color: #f8fafc; font-family: sans-serif; }
        main { max-width: 1100px; margin: 0 auto; padding: 5vh 5vw; }
        h1 { font-size: clamp(2rem, 5vw, 4rem); margin: 0 0 2rem; }
        h2 { color: #93c5fd; font-size: clamp(1.2rem, 2vw, 2rem); }
        ul { list-style: none; padding: 0; }
        li { display: flex; justify-content: space-between; gap: 2rem; padding: 1rem 0; border-bottom: 1px solid #334155; font-size: clamp(1.3rem, 3vw, 2.5rem); }
        .empty { color: #cbd5e1; }
        .status { color: #fca5a5; font-size: 1.25rem; }
    </style>
</head>
<body>
<main>
    <h1>Clinic queue</h1>
    <p id="queue-status" class="status" role="status" hidden>Queue disconnected — shown calls may be stale.</p>
    <section aria-labelledby="current-calls-title">
        <h2 id="current-calls-title">Current calls</h2>
        <ul id="current-calls"><li class="empty">Waiting for the next call</li></ul>
    </section>
    <section aria-labelledby="recent-calls-title">
        <h2 id="recent-calls-title">Recent calls</h2>
        <ul id="recent-calls"><li class="empty">No calls yet</li></ul>
    </section>
</main>
<script>
    const endpoint = @json(route('lcd.queue', $siteId));
    const status = document.getElementById('queue-status');
    const setConnectionState = (connected) => {
        status.hidden = connected;
    };
    const render = (element, entries, empty) => {
        element.replaceChildren();
        if (!entries.length) {
            const item = document.createElement('li');
            item.className = 'empty';
            item.textContent = empty;
            element.append(item);
            return;
        }
        entries.forEach((entry) => {
            const item = document.createElement('li');
            const ticket = document.createElement('strong');
            const destination = document.createElement('span');
            ticket.textContent = entry.ticket_number;
            destination.textContent = entry.destination;
            item.append(ticket, destination);
            element.append(item);
        });
    };
    const refresh = async () => {
        try {
            const response = await fetch(endpoint, { cache: 'no-store' });
            if (!response.ok) throw new Error('queue_refresh_failed');
            const queue = await response.json();
            render(document.getElementById('current-calls'), queue.current, 'Waiting for the next call');
            render(document.getElementById('recent-calls'), queue.recent_calls, 'No calls yet');
            setConnectionState(true);
        } catch (_) {
            setConnectionState(false);
        }
    };
    refresh();
    setInterval(refresh, 5000);
</script>
</body>
</html>
