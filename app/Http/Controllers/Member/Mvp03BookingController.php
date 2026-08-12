<?php

declare(strict_types=1);

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Member\Application\Services\MemberContextResolver;
use App\Modules\Member\Application\Services\Mvp03BookingService;
use App\Modules\Member\Application\Services\Mvp03CatalogueService;
use App\Modules\Member\Domain\Models\Booking;
use App\Modules\Member\Domain\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class Mvp03BookingController extends Controller
{
    public function services(Request $request, MemberContextResolver $members, Mvp03CatalogueService $catalogue): View|RedirectResponse
    {
        $member = $this->completeMember($request, $members);
        if ($member instanceof RedirectResponse) {
            return $member;
        }

        return view('member.booking.services', ['member' => $member, 'offerings' => $catalogue->offerings()]);
    }

    public function service(string $service, Request $request, MemberContextResolver $members, Mvp03CatalogueService $catalogue): View|RedirectResponse
    {
        $member = $this->completeMember($request, $members);
        if ($member instanceof RedirectResponse) {
            return $member;
        }
        $offering = $catalogue->offering($service);
        if ($offering === null) {
            throw new NotFoundHttpException;
        }

        return view('member.booking.service', ['member' => $member, 'offering' => $offering, 'schedules' => $catalogue->schedules((string) $offering->getKey())]);
    }

    public function schedules(Request $request, MemberContextResolver $members, Mvp03CatalogueService $catalogue): View|RedirectResponse
    {
        $member = $this->completeMember($request, $members);
        if ($member instanceof RedirectResponse) {
            return $member;
        }

        return view('member.booking.schedules', ['member' => $member, 'schedules' => $catalogue->schedules()]);
    }

    public function store(Request $request, MemberContextResolver $members, Mvp03BookingService $bookings): RedirectResponse
    {
        $member = $this->completeMember($request, $members);
        if ($member instanceof RedirectResponse) {
            return $member;
        }

        $validator = Validator::make($request->all(), [
            'schedule_id' => ['required', 'string', 'size:36'],
            'point_cost' => ['nullable', 'string', 'regex:/\A[0-9]+(?:\.[0-9]{1,4})?\z/'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
            'confirmation' => ['accepted'],
        ], [
            'schedule_id.required' => __('Pilih jadwal Sesi Foto Radiografi.'),
            'confirmation.accepted' => __('Konfirmasi akhir diperlukan sebelum Madeena Points digunakan.'),
            'point_cost.regex' => __('Harga Madeena Points tidak valid.'),
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $result = $bookings->createForCurrentMember(
                $validator->validated()['schedule_id'],
                $validator->validated()['idempotency_key'] ?? null,
                $validator->validated()['point_cost'] ?? null,
            );
        } catch (\Throwable) {
            return back()->withErrors(['booking' => __('Jadwal belum berhasil dikonfirmasi. Pilihan Anda tidak berubah. Silakan coba kembali.')])->withInput();
        }

        return redirect()->route('member.bookings.show', $result['booking_id'])->with('status', __('Jadwal Sesi Foto Radiografi berhasil dikonfirmasi.'));
    }

    public function index(Request $request, MemberContextResolver $members): View|RedirectResponse
    {
        $member = $this->completeMember($request, $members);
        if ($member instanceof RedirectResponse) {
            return $member;
        }

        $bookings = Booking::query()->with(['schedule.site', 'service', 'imagingOrder'])->where('member_id', $member->getKey())->latest('created_at')->get();

        return view('member.booking.index', ['member' => $member, 'bookings' => $bookings]);
    }

    public function show(string $booking, Request $request, MemberContextResolver $members): View|RedirectResponse
    {
        $member = $this->completeMember($request, $members);
        if ($member instanceof RedirectResponse) {
            return $member;
        }
        $record = Booking::query()->with(['schedule.site', 'service', 'imagingOrder'])->whereKey($booking)->where('member_id', $member->getKey())->first();
        if ($record === null) {
            throw new NotFoundHttpException;
        }

        return view('member.booking.show', ['member' => $member, 'booking' => $record]);
    }

    private function completeMember(Request $request, MemberContextResolver $members): Member|RedirectResponse
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

        return $member;
    }
}
