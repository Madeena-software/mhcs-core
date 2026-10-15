<?php

declare(strict_types=1);

namespace Tests\Feature\Radiographer;

use App\Modules\Operator\Application\Services\RadiographerWorkspace;
use App\Shared\Audit\AuditEvent;
use App\Shared\Audit\AuditStore;
use App\Shared\Time\Clock;
use App\Shared\Time\FrozenClock;
use Illuminate\Database\Events\TransactionCommitting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\RadiographerFixtures;
use Tests\TestCase;

final class RadiographerDisclosureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->app->instance(Clock::class, new FrozenClock(now()->toDateTimeImmutable()));
        $this->artisan('migrate:fresh', ['--force' => true])->assertExitCode(0);
        Storage::fake('local');
    }

    private function prepared(): array
    {
        $f = RadiographerFixtures::create();
        $this->actingAs($f['admin']);
        $w = app(RadiographerWorkspace::class)->manage($f['reference'], 'issue', ['user' => $f['worker']->id]);
        $this->actingAs($f['worker']);
        $this->get($w['url'])->assertRedirect();

        return [$f, $w];
    }

    public function test_only_emitting_original_bytes_records_dicom_delivery(): void
    {
        [$f, $w] = $this->prepared();
        $service = app(RadiographerWorkspace::class);
        $r = $service->read($w['id'], 1);
        $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
        $service->handoff($w['id'], ['revision' => 1, 'binding' => $r['binding'], 'key' => 'audit-control'], 1);
        $html = $this->get('/radiographer/workspaces/'.$w['id'])->assertOk()->baseResponse;
        $this->assertStringContainsString('SYNTHETIC-MRN', $this->emit($html));
        $image = $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->assertOk()->baseResponse;
        $deliveries = fn () => DB::table('audit_events')->where('action', 'radiographer-workspace.dicom-delivery-authorized')->count();
        $this->assertSame(0, $deliveries());
        $this->assertGreaterThan(0, DB::table('audit_events')->where('action', 'radiographer-workspace.dicom-inspected')->count());
        $this->assertSame($f['bytes'], $this->emit($image));
        $this->assertSame(1, $deliveries());
    }

    public static function disclosures(): array
    {
        return [[''], ['/dicom'], ['/save'], ['/handoff']];
    }

    #[DataProvider('disclosures')]
    public function test_prepared_protected_response_is_withheld_after_revocation(string $suffix): void
    {
        [$f, $w] = $this->prepared();
        $service = app(RadiographerWorkspace::class);
        $url = '/radiographer/workspaces/'.$w['id'].$suffix;
        $r = $service->read($w['id'], 1);
        if ($suffix === '/handoff') {
            $service->save($w['id'], ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()], 1);
            $response = $this->postJson($url, ['revision' => 1, 'binding' => $r['binding'], 'key' => 'prepared'])->assertOk()->baseResponse;
        } elseif ($suffix === '/save') {
            $response = $this->postJson($url, ['revision' => 0, 'binding' => $r['binding'], 'overlay' => RadiographerFixtures::overlay(), 'qc' => RadiographerFixtures::qc()])->assertOk()->baseResponse;
        } else {
            $response = $this->get($url)->assertOk()->baseResponse;
        }
        $protected = $response->getContent();
        $this->actingAs($f['admin']);
        $service->manage($f['reference'], 'revoke', []);
        $this->actingAs($f['worker']);
        $body = $this->emit($response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringNotContainsString($protected, $body);
        $this->assertStringNotContainsString('SYNTHETIC-MRN', $body);
        $this->assertStringNotContainsString('DICM', $body);
        $this->assertFalse($response->headers->has('Content-Disposition'));
        $this->assertSame(0, DB::table('audit_events')->where('action', 'radiographer-workspace.dicom-delivery-authorized')->count());
    }

    public static function failures(): array
    {
        return [['append'], ['commit']];
    }

    public function test_head_inspects_without_a_dicom_delivery_and_range_requests_still_use_the_gate(): void
    {
        [$f, $w] = $this->prepared();
        $url = '/radiographer/workspaces/'.$w['id'].'/dicom';
        $head = $this->head($url)->assertOk()->baseResponse;
        $this->assertSame('', $this->emit($head));
        $this->assertSame(0, DB::table('audit_events')->where('action', 'radiographer-workspace.dicom-delivery-authorized')->count());
        $pendingHead = $this->head($url)->assertOk()->baseResponse;
        $range = $this->get($url, ['Range' => 'bytes=0-200', 'If-None-Match' => '*'])->assertOk()->baseResponse;
        $this->actingAs($f['admin']);
        app(RadiographerWorkspace::class)->manage($f['reference'], 'revoke', []);
        $this->actingAs($f['worker']);
        $this->assertSame('', $this->emit($pendingHead));
        $this->assertSame(403, $pendingHead->getStatusCode());
        $this->assertStringNotContainsString('DICM', $this->emit($range));
        $this->assertSame(403, $range->getStatusCode());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'radiographer-workspace.dicom-delivery-authorized')->count());
    }

    public function test_manager_response_is_withheld_after_capability_revocation(): void
    {
        [$f] = $this->prepared();
        $this->actingAs($f['admin']);
        $response = $this->get('/admin/radiographer-workspaces/'.$f['reference'])->assertOk()->baseResponse;
        DB::table('authorization_permission_assignments')->where('user_id', $f['admin']->id)->where('permission', 'like', 'operator.radiographer-workspace.%')->update(['active' => false]);
        $this->assertStringNotContainsString($f['worker']->email, $this->emit($response));
        $this->assertSame(403, $response->getStatusCode());
    }

    #[DataProvider('failures')]
    public function test_final_disclosure_audit_failure_withholds_prepared_bytes(string $failure): void
    {
        [, $w] = $this->prepared();
        $response = $this->get('/radiographer/workspaces/'.$w['id'].'/dicom')->assertOk()->baseResponse;
        if ($failure === 'append') {
            $this->app->instance(AuditStore::class, new class implements AuditStore
            {
                public function append(AuditEvent $event): void
                {
                    throw new \RuntimeException('synthetic-audit-failure');
                }
            });
        } else {
            $this->app['events']->listen(TransactionCommitting::class, function (): void {
                throw new \RuntimeException('synthetic-commit-failure');
            });
        }
        $this->assertStringNotContainsString('DICM', $this->emit($response));
        $this->assertSame($failure === 'append' ? 503 : 403, $response->getStatusCode());
        $this->assertSame(0, DB::table('audit_events')->where('action', 'radiographer-workspace.dicom-delivery-authorized')->count());
    }

    private function emit($response): string
    {
        ob_start();
        try {
            $response->sendContent();

            return ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
