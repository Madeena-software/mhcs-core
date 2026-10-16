<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Explicit inventory of a fresh, closed synthetic fixture; never bound by application boot. */
final readonly class ClosedSyntheticClinicalInventory implements ClinicalSourceInventory
{
    public function __construct(private array $inventory) {}

    public static function seal(array $f): self
    {
        if (! app()->environment('testing') || ! in_array(DB::getDatabaseName(), [':memory:', 'mhcs_rad_synthetic'], true)
            && ! str_ends_with(DB::getDatabaseName(), '/radiologist-browser.sqlite') && ! str_ends_with(DB::getDatabaseName(), '/member-races.sqlite')) {
            throw new RuntimeException('Disposable synthetic database required');
        }
        $case = $f['i']['booking'];
        $s = DB::table('image_gateway_studies')->where('id', $f['study'])->first();
        if ($s->checksum !== hash('sha256', $f['bytes']) || DB::table('bookings')->count() !== 1
            || DB::table('image_gateway_studies')->count() !== 1 || DB::table('doctor_finalizations')->count() !== 1
            || DB::table('image_gateway_ai_jobs')->count() !== 0 || ! str_contains($f['bytes'], 'SYNTHETIC')) {
            throw new RuntimeException('Closed synthetic source inventory not established');
        }
        $inventory = ['database' => DB::getDatabaseName(), 'case' => $case, 'handoff' => $f['handoff'], 'binding' => $f['binding'],
            'study' => $s->id, 'source' => $s->checksum, 'source_key' => $s->object_key,
            'capture' => $f['capture'], 'report' => DB::table('doctor_finalizations')->where('case_id', $case)->value('report_id')];
        $inventory['evidence'] = self::evidence($inventory);

        return new self($inventory);
    }

    public function export(): array
    {
        return $this->inventory;
    }

    private static function evidence(array $i): string
    {
        $data = [];
        foreach (['image_gateway_studies' => ['id', $i['study']], 'image_gateway_capture_sets' => ['id', $i['capture']],
            'image_gateway_capture_objects' => ['capture_set_id', $i['capture']], 'operator_radiographer_handoffs' => ['id', $i['handoff']],
            'operator_workspace_protocols' => ['study_id', $i['study']], 'doctor_report_versions' => ['workspace_id', DB::table('doctor_finalizations')->where('case_id', $i['case'])->value('workspace_id')],
            'doctor_finalizations' => ['case_id', $i['case']]] as $table => [$key, $value]) {
            $data[$table] = DB::table($table)->where($key, $value)->orderBy('id')->when(DB::transactionLevel() > 0, fn ($q) => $q->lockForUpdate())->get()->all();
        }

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function assertClosed(string $case, string $handoff, string $binding, string $report): void
    {
        $i = $this->inventory;
        if (! app()->environment('testing') || $i['database'] !== DB::getDatabaseName()
            || DB::table('bookings')->count() !== 1 || DB::table('image_gateway_studies')->count() !== 1
            || DB::table('doctor_finalizations')->count() !== 1 || DB::table('doctor_workspaces')->count() !== 1
            || DB::table('image_gateway_ai_jobs')->count() !== 0 || $case !== $i['case'] || $handoff !== $i['handoff']
            || $binding !== $i['binding'] || $report !== $i['report'] || ! hash_equals($i['evidence'], self::evidence($i))
            || ! hash_equals($i['source'], hash('sha256', Storage::disk(config('mhcs.private_object_disk'))->get($i['source_key'])))) {
            throw new RuntimeException('Synthetic closed inventory changed');
        }
    }
}
