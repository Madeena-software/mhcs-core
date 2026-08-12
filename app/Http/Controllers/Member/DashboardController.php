<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Member\Application\Services\MemberContextResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class DashboardController extends Controller
{
    public function show(Request $request, MemberContextResolver $members): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $member = $members->resolveForUserId((string) $user->getAuthIdentifier());
        if ($member === null) {
            return redirect()->route('login');
        }

        if (! $members->isComplete($member)) {
            return redirect()->route('member.profile');
        }

        return view('member.dashboard', [
            'memberName' => $member->name,
            'medicalRecordNumber' => $member->medical_record_number,
            'completionPercentage' => $members->completionPercentage($member),
            'identityStatus' => $this->identityStatus($member->identity_status),
            'accountStatus' => $this->accountStatus($user->account_status),
        ]);
    }

    private function identityStatus(string $status): string
    {
        return match ($status) {
            'verified' => __('verified'),
            default => __('pending_verification'),
        };
    }

    private function accountStatus(string $status): string
    {
        return match ($status) {
            'active' => __('active'),
            'suspended' => __('suspended'),
            default => __('inactive'),
        };
    }
}
