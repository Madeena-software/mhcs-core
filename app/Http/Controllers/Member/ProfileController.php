<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Member\Application\Services\MemberContextResolver;
use App\Modules\Member\Application\Services\MemberProfileService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(Request $request, MemberContextResolver $members): View|RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $member = $members->resolveForUserId((string) $user->getAuthIdentifier());
        if ($member === null) {
            return redirect()->route('login');
        }

        return view('member.profile.edit', [
            'user' => $user,
            'member' => $member,
            'completionPercentage' => $members->completionPercentage($member),
        ]);
    }

    public function update(Request $request, MemberContextResolver $members, MemberProfileService $profile): RedirectResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        $input = $this->normalizedInput($request);
        $validator = Validator::make($input, [
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->getAuthIdentifier())],
            'phone' => ['nullable', 'string', 'max:32'],
            'current_address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
        ], [
            'email.email' => 'Masukkan alamat email yang valid.',
            'email.unique' => 'Email tersebut sudah digunakan.',
            '*.max' => 'Nilai yang dimasukkan terlalu panjang.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput($input);
        }

        $member = $members->requireForUserId((string) $user->getAuthIdentifier());
        $attributes = array_replace([
            'email' => $user->email,
            'phone' => $member->phone,
            'current_address' => $member->current_address,
            'emergency_contact_name' => $member->emergency_contact_name,
            'emergency_contact_relationship' => $member->emergency_contact_relationship,
            'emergency_contact_phone' => $member->emergency_contact_phone,
        ], $validator->validated());

        try {
            $percentage = $profile->update((string) $user->getAuthIdentifier(), $attributes);
        } catch (\Throwable) {
            return back()->withErrors(['profile' => 'Profil belum dapat disimpan.'])->withInput($input);
        }

        if ($percentage === 100) {
            return redirect()->route('member.dashboard')->with('status', 'Profil berhasil diperbarui.');
        }

        return redirect()->route('member.profile')->with('status', 'Profil berhasil disimpan. Lengkapi data yang masih diperlukan.');
    }

    /** @return array<string, ?string> */
    private function normalizedInput(Request $request): array
    {
        $trim = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }

            $value = trim((string) $value);

            return $value === '' ? null : $value;
        };

        $fields = ['email', 'phone', 'current_address', 'emergency_contact_name', 'emergency_contact_relationship', 'emergency_contact_phone'];
        $input = [];

        foreach ($fields as $field) {
            if (! array_key_exists($field, $request->all())) {
                continue;
            }

            $value = $trim($request->input($field));
            $input[$field] = $field === 'email' && $value !== null ? strtolower($value) : $value;
        }

        return $input;
    }
}
