<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\OperatorActiveSiteService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorAttendanceService;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Modules\Operator\Application\Services\OperatorWorklistService;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Throwable;

final class PortalController extends Controller
{
    public function dashboard(
        OperatorAuthorization $authorization,
        OperatorActiveSiteService $sites,
        OperatorShiftAssignmentService $assignments,
        OperatorWorklistService $worklist,
    ): View {
        $portal = $authorization->portal();
        $activeSite = null;
        try {
            $activeSite = $authorization->portalSite($portal);
        } catch (OperatorException) {
        }

        return view('operator.dashboard', [
            'operatorName' => $portal['profile']->display_name ?: $portal['user']->getFilamentName(),
            'sites' => $sites->assignedSites(),
            'activeSite' => $activeSite,
            'shifts' => $activeSite === null ? [] : $assignments->assignedToCurrentOperator(),
            'arrivals' => $activeSite === null ? [] : $worklist->current(),
        ]);
    }

    public function site(OperatorAuthorization $authorization, OperatorActiveSiteService $sites): View
    {
        $portal = $authorization->portal();

        return view('operator.site', [
            'sites' => $sites->assignedSites(),
            'activeSite' => rescue(fn () => $authorization->portalSite($portal), null, false),
        ]);
    }

    public function selectSite(Request $request, OperatorActiveSiteService $sites): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['site_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $site = $sites->select((string) $validator->validated()['site_id']);

            return redirect()->route('operator.dashboard')->with('status', 'Active site selected: '.$site->display_name.'.');
        } catch (Throwable $exception) {
            return back()->withErrors(['site_id' => $exception instanceof OperatorException ? $exception->getMessage() : 'The active site could not be selected.']);
        }
    }

    public function eligible(
        OperatorAuthorization $authorization,
        OperatorShiftAssignmentService $assignments,
    ): View {
        $portal = $authorization->portal();
        $activeSite = $authorization->portalSite($portal);

        return view('operator.eligible-shifts', [
            'activeSite' => $activeSite,
            'shifts' => $assignments->assignedToCurrentOperator(),
        ]);
    }

    public function attendance(string $schedule, Request $request, OperatorAttendanceService $attendance, OperatorAuthorization $authorization): View|RedirectResponse
    {
        $at = (string) $request->query('at', now()->format(DATE_ATOM));
        try {
            $site = $authorization->portalSite($authorization->portal());

            return view('operator.attendance', [
                'site' => $site,
                'scheduleId' => $schedule,
                'at' => $at,
                'rows' => $attendance->query($schedule, $at),
            ]);
        } catch (Throwable $exception) {
            return redirect()->route('operator.dashboard')->withErrors(['attendance' => $exception instanceof OperatorException ? $exception->getMessage() : 'The attendance list is unavailable.']);
        }
    }

    public function recordArrival(Request $request, OperatorArrivalService $arrivals): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'uuid'],
            'occurrence_at' => ['required', 'string', 'max:64'],
            'idempotency_key' => ['required', 'string', 'max:191'],
            'schedule_id' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $result = $arrivals->record(
                (string) $validator->validated()['booking_id'],
                (string) $validator->validated()['occurrence_at'],
                (string) $validator->validated()['idempotency_key'],
            );

            return redirect()->route('operator.attendance', $validator->validated()['schedule_id'])->with('status', 'Arrival recorded for '.$result['booking_id'].'.');
        } catch (Throwable $exception) {
            return back()->withErrors(['arrival' => $exception instanceof OperatorException ? $exception->getMessage() : 'The arrival could not be recorded.'])->withInput();
        }
    }

    public function worklist(OperatorWorklistService $worklist): View
    {
        return view('operator.verification-worklist', ['arrivals' => $worklist->current()]);
    }
}
