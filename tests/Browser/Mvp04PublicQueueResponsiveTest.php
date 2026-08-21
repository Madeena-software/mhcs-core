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

it('keeps public queue tickets inside their cards across LCD viewports', function (): void {
    $page = visit(route('lcd.show', $this->fixture['siteLocalId']));

    $page->script(<<<'JS'
        window.__mvpQueue = { current: [], recent_calls: [] };
        window.fetch = async () => new Response(JSON.stringify(window.__mvpQueue), {
            headers: { 'content-type': 'application/json' },
        });
    JS);
    $page->wait(6)->assertSee('Menunggu panggilan berikutnya');

    $page->script(<<<'JS'
        window.__mvpQueue = {
            current: [
                { ticket_number: 'T-002', destination: 'PEMERIKSAAN DASAR' },
                { ticket_number: 'RAD-123456789', destination: 'SESI FOTO RADIOGRAFI' },
            ],
            recent_calls: [
                { ticket_number: 'T-002', destination: 'PEMERIKSAAN DASAR' },
                { ticket_number: 'RAD-123456789', destination: 'SESI FOTO RADIOGRAFI' },
                { ticket_number: 'T-002', destination: 'PEMERIKSAAN DASAR' },
                { ticket_number: 'RAD-123456789', destination: 'SESI FOTO RADIOGRAFI' },
                { ticket_number: 'T-002', destination: 'PEMERIKSAAN DASAR' },
            ],
        };
    JS);
    $page->wait(6)->assertSee('RAD-123456789');

    foreach ([
        [1280, 720], [1366, 768], [1536, 960], [1920, 1080],
        [2560, 1080], [2560, 1440], [3840, 2160], [1080, 1920],
    ] as [$width, $height]) {
        $page->page()->setViewportSize($width, $height);
        $geometry = $page->script(<<<'JS'
            (() => {
                const shell = document.querySelector('.lcd-shell').getBoundingClientRect();
                const header = document.querySelector('.lcd-header').getBoundingClientRect();
                const clock = document.querySelector('.lcd-clock').getBoundingClientRect();
                const current = document.querySelector('.current-hero').getBoundingClientRect();
                const recent = document.querySelector('.recent-panel').getBoundingClientRect();
                const tickets = [...document.querySelectorAll('.ticket-number')].map((node) => {
                    const card = node.closest('.call-card').getBoundingClientRect();
                    const box = node.getBoundingClientRect();
                    return {
                        singleLine: node.getClientRects().length === 1,
                        insideCard: box.left >= card.left && box.right <= card.right && box.top >= card.top && box.bottom <= card.bottom,
                    };
                });
                return {
                    noOverflow: document.documentElement.scrollWidth <= document.documentElement.clientWidth,
                    shellFits: shell.right <= window.innerWidth && shell.bottom <= window.innerHeight,
                    clockFitsHeader: clock.right <= header.right && clock.bottom <= header.bottom,
                    panelsFit: current.right <= window.innerWidth && recent.right <= window.innerWidth,
                    tickets,
                    stacked: getComputedStyle(document.querySelector('.queue-layout')).gridTemplateColumns.split(' ').length === 1,
                };
            })()
        JS);

        expect($geometry['noOverflow'])->toBeTrue("{$width}x{$height} overflows horizontally");
        expect($geometry['shellFits'])->toBeTrue("{$width}x{$height} shell exceeds viewport");
        expect($geometry['clockFitsHeader'])->toBeTrue("{$width}x{$height} header clock collides");
        expect($geometry['panelsFit'])->toBeTrue("{$width}x{$height} panel exceeds viewport");
        expect($geometry['tickets'])->not->toBeEmpty();
        foreach ($geometry['tickets'] as $ticket) {
            expect($ticket['singleLine'])->toBeTrue("{$width}x{$height} ticket wraps");
            expect($ticket['insideCard'])->toBeTrue("{$width}x{$height} ticket leaves card");
        }
        if ($width === 1080) {
            expect($geometry['stacked'])->toBeTrue('portrait layout is not stacked');
        }
    }
});
