<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Doctor\Application\Contracts\ClinicalSourceInventory;
use App\Modules\Doctor\Application\Services\RadiologistWorkspace;
use App\Modules\Member\Application\Data\VerificationAssetInput;
use App\Modules\Member\Application\Services\MemberAuthorization;
use App\Modules\Member\Application\Services\MemberResultAccess;
use App\Modules\Member\Application\Services\MemberVerificationAssetService;
use App\Modules\Member\Domain\Enums\RegistrationSource;
use App\Modules\Member\Domain\Enums\VerificationAssetType;
use App\Modules\Member\Domain\Models\Member;
use App\Shared\Storage\PrivateObjectStore;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MemberResultFixtures
{
    public static function prepare(bool $converted = false, ?array $base = null): array
    {
        $f = $base ?? RadiologistFixtures::create($converted);
        foreach (['publish', 'hold', 'suspend', 'revoke'] as $action) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $f['admin']->id, 'permission' => 'member.result-publication.'.$action, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('member_result_managers')->insert(['user_id' => $f['admin']->id, 'site_id' => $f['operatorSiteId'], 'active' => true]);
        $memberUser = User::query()->findOrFail(DB::table('members')->where('id', $f['member'])->value('user_id'));
        Auth::login($f['admin']);
        foreach (['member.registration.manage', 'member.identity.verify'] as $permission) {
            DB::table('authorization_permission_assignments')->insert(['id' => (string) Str::uuid(), 'user_id' => $f['admin']->id,
                'permission' => $permission, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
        $context = app(MemberAuthorization::class)->registration(RegistrationSource::Administrator, true);
        $member = Member::query()->findOrFail($f['member']);
        // Synthetic evidence traverses the existing private-object and approved verification lifecycle.
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jL1kAAAAASUVORK5CYII=', true);
        foreach ([VerificationAssetType::Ktp, VerificationAssetType::ProfilePhoto] as $type) {
            $object = app(PrivateObjectStore::class)->put($png, $context, 'member.registration');
            app(MemberVerificationAssetService::class)->recordForRegistration($member,
                new VerificationAssetInput($type, $object, 'image/png'), $context);
        }

        return $f + ['memberUser' => $memberUser];
    }

    public static function finalize(array $f): array
    {
        Auth::login($f['admin']);
        request()->session()->start();
        $s = app(RadiologistWorkspace::class);
        $s->verifyEligibility($f['handoff'], ['user' => $f['doctor']->id, 'status' => 'verified', 'evidence' => 'SYNTHETIC', 'valid_until' => now()->addDays(10)->toAtomString(), 'password' => 'password']);
        $w = $s->manage($f['handoff'], 'issue', ['user' => $f['doctor']->id, 'password' => 'password']);
        Auth::login($f['doctor']);
        $s->entry($w['id'], 1);
        $p = ['binding' => $f['binding'], 'handoff' => $f['handoff']];
        $s->command($w['id'], 'quality', $p + ['revision' => 0, 'decision' => 'usable'], 1);
        $s->command($w['id'], 'draft', $p + ['revision' => 1, 'findings' => 'Synthetic final findings.', 'conclusion' => 'Synthetic final conclusion.', 'recommendations' => 'Synthetic recommendation.'], 1);
        $s->command($w['id'], 'finalize', $p + ['revision' => 2, 'attest' => true, 'key' => 'synthetic-final', 'password' => 'password'], 1);
        $inventory = ClosedSyntheticClinicalInventory::seal($f);
        app()->instance(ClinicalSourceInventory::class, $inventory);
        Auth::login($f['admin']);

        return $f + ['workspace' => $w['id'], 'report' => DB::table('doctor_finalizations')->value('report_id'), 'inventory' => $inventory];
    }

    public static function publish(array $f, int $revision = 0, string $key = 'synthetic-publish'): array
    {
        return app(MemberResultAccess::class)->manage($f['i']['booking'], 'publish', ['revision' => $revision, 'report' => $f['report'], 'member' => $f['member'], 'confirm' => true, 'key' => $key]);
    }
}
