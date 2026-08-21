<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Tests\Operator\Mvp04Fixtures;

uses(Mvp04Fixtures::class);

beforeEach(function (): void {
    $database = storage_path('framework/testing/mhcs-public-lcd-browser.sqlite');
    @unlink($database);
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
    ]);
    putenv('DB_DATABASE='.$database);
    DB::purge('sqlite');
    $this->artisan('migrate:fresh', ['--quiet' => true]);
    $this->fixture = $this->operatorFixture(false);
});

it('keeps public queue states inside their cards across LCD viewports', function (): void {
    $page = visit(route('lcd.show', $this->fixture['siteLocalId']));

    $page->script(<<<'JS'
        window.__mvpQueue = { current: [], recent_calls: [] };
        window.fetch = async () => new Response(JSON.stringify(window.__mvpQueue), {
            headers: { 'content-type': 'application/json' },
        });
    JS);
    $page->wait(6)->assertSee('Menunggu panggilan berikutnya');

    $render = function (array $queue) use ($page): void {
        $page->script('window.__mvpQueue = '.json_encode($queue, JSON_THROW_ON_ERROR).';');
        $page->wait(6);
    };

    $measure = function (int $width, int $height) use ($page): array {
        $page->page()->setViewportSize($width, $height);

        return $page->script(<<<'JS'
            (() => {
                const shell = document.querySelector('.lcd-shell').getBoundingClientRect();
                const brand = document.querySelector('.brand').getBoundingClientRect();
                const clock = document.querySelector('.lcd-clock').getBoundingClientRect();
                const current = document.querySelector('.current-hero').getBoundingClientRect();
                const recent = document.querySelector('.recent-panel').getBoundingClientRect();
                const primaryCard = document.querySelector('.current-calls .call-card-primary');
                const primaryTicket = primaryCard?.querySelector('.ticket-number');
                const primaryDestination = primaryCard?.querySelector('.call-destination');
                const tickets = [...document.querySelectorAll('.ticket-number')].map((node) => {
                    const card = node.closest('.call-card').getBoundingClientRect();
                    const box = node.getBoundingClientRect();
                    const destination = node.parentElement.querySelector('.call-destination').getBoundingClientRect();
                    return {
                        fullTextFits: node.scrollWidth <= node.clientWidth,
                        ticketInsideCard: box.left >= card.left && box.right <= card.right && box.top >= card.top && box.bottom <= card.bottom,
                        destinationInsideCard: destination.left >= card.left && destination.right <= card.right && destination.top >= card.top && destination.bottom <= card.bottom,
                    };
                });
                return {
                    noOverflow: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
                    shellFits: shell.right <= window.innerWidth && shell.bottom <= window.innerHeight,
                    headerSidesDoNotIntersect: brand.right <= clock.left || clock.right <= brand.left || brand.bottom <= clock.top || clock.bottom <= brand.top,
                    panelsFit: current.right <= window.innerWidth && recent.right <= window.innerWidth,
                    primary: primaryCard && primaryTicket && primaryDestination ? (() => {
                        const card = primaryCard.getBoundingClientRect();
                        const ticket = primaryTicket.getBoundingClientRect();
                        const destination = primaryDestination.getBoundingClientRect();
                        return {
                            visibleText: primaryTicket.textContent,
                            singleLine: primaryTicket.getClientRects().length === 1,
                            fullTextFits: primaryTicket.scrollWidth <= primaryTicket.clientWidth,
                            ticketInsideCard: ticket.left >= card.left && ticket.right <= card.right && ticket.top >= card.top && ticket.bottom <= card.bottom,
                            destinationInsideCard: destination.left >= card.left && destination.right <= card.right && destination.top >= card.top && destination.bottom <= card.bottom,
                        };
                    })() : null,
                    tickets,
                    stacked: getComputedStyle(document.querySelector('.queue-layout')).gridTemplateColumns.split(' ').length === 1,
                };
            })()
        JS);
    };

    $render(['current' => [['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR']], 'recent_calls' => []]);
    $page->assertSee('T-002')->assertSee('PEMERIKSAAN DASAR');
    $primaryGeometry = $measure(1536, 960);
    expect($primaryGeometry['primary']['visibleText'])->toBe('T-002');
    expect($primaryGeometry['primary']['singleLine'])->toBeTrue();
    expect($primaryGeometry['primary']['fullTextFits'])->toBeTrue();
    expect($primaryGeometry['primary']['ticketInsideCard'])->toBeTrue();
    expect($primaryGeometry['primary']['destinationInsideCard'])->toBeTrue();
    expect($primaryGeometry['noOverflow'])->toBeTrue();
    expect($primaryGeometry['panelsFit'])->toBeTrue();
    expect($primaryGeometry['headerSidesDoNotIntersect'])->toBeTrue();

    $render(['current' => [['ticket_number' => 'RAD-123456789', 'destination' => 'SESI FOTO RADIOGRAFI']], 'recent_calls' => []]);
    $page->assertSee('RAD-123456789')->assertSee('SESI FOTO RADIOGRAFI');

    $render([
        'current' => [
            ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
            ['ticket_number' => 'RAD-123456789', 'destination' => 'SESI FOTO RADIOGRAFI'],
        ],
        'recent_calls' => [
            ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
            ['ticket_number' => 'RAD-123456789', 'destination' => 'SESI FOTO RADIOGRAFI'],
            ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
            ['ticket_number' => 'RAD-123456789', 'destination' => 'SESI FOTO RADIOGRAFI'],
            ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
        ],
    ]);
    $page->assertSee('RAD-123456789');
    expect($page->script("document.querySelectorAll('#recent-calls .call-card').length"))->toBe(5);

    $render(['current' => [['ticket_number' => 'ABCD1234EFGH5678IJKL9012MNOP3456', 'destination' => 'PEMERIKSAAN DASAR']], 'recent_calls' => [
        ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
        ['ticket_number' => 'RAD-123456789', 'destination' => 'SESI FOTO RADIOGRAFI'],
        ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
        ['ticket_number' => 'RAD-123456789', 'destination' => 'SESI FOTO RADIOGRAFI'],
        ['ticket_number' => 'T-002', 'destination' => 'PEMERIKSAAN DASAR'],
    ]]);
    $page->assertSee('ABCD1234EFGH5678IJKL9012MNOP3456');

    foreach ([
        [1280, 720], [1366, 768], [1536, 960], [1920, 1080],
        [2560, 1080], [2560, 1440], [3840, 2160], [1080, 1920],
    ] as [$width, $height]) {
        $geometry = $measure($width, $height);
        expect($geometry['noOverflow'])->toBeTrue("{$width}x{$height} overflows horizontally");
        expect($geometry['shellFits'])->toBeTrue("{$width}x{$height} shell exceeds viewport");
        expect($geometry['headerSidesDoNotIntersect'])->toBeTrue("{$width}x{$height} header clock collides");
        expect($geometry['panelsFit'])->toBeTrue("{$width}x{$height} panel exceeds viewport");
        expect($geometry['tickets'])->not->toBeEmpty();
        foreach ($geometry['tickets'] as $ticket) {
            expect($ticket['fullTextFits'])->toBeTrue("{$width}x{$height} ticket text is clipped");
            expect($ticket['ticketInsideCard'])->toBeTrue("{$width}x{$height} ticket leaves card");
            expect($ticket['destinationInsideCard'])->toBeTrue("{$width}x{$height} destination leaves card");
        }
        if ($width === 1080) {
            expect($geometry['stacked'])->toBeTrue('portrait layout is not stacked');
        }
    }

    $page->script('window.fetch = async () => new Response("", { status: 503, statusText: "synthetic disconnect" });');
    $page->wait(6)->assertVisible('#queue-status')->assertSee('Koneksi antrian terputus');
    $staleGeometry = $measure(1536, 960);
    expect($staleGeometry['noOverflow'])->toBeTrue('stale state overflows horizontally');
    expect($staleGeometry['shellFits'])->toBeTrue('stale state exceeds viewport');
    expect($staleGeometry['panelsFit'])->toBeTrue('stale state pushes panels out of bounds');
});
