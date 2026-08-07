<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\Operator\Application\Services\OperatorActiveSiteService;
use App\Modules\Operator\Application\Services\OperatorArrivalService;
use App\Modules\Operator\Application\Services\OperatorAttendanceService;
use App\Modules\Operator\Application\Services\OperatorAuthorization;
use App\Modules\Operator\Application\Services\OperatorCheckInTicketService;
use App\Modules\Operator\Application\Services\OperatorIdentityVerificationService;
use App\Modules\Operator\Application\Services\OperatorPaperConsentConfirmationService;
use App\Modules\Operator\Application\Services\OperatorShiftAssignmentService;
use App\Modules\Operator\Application\Services\OperatorWorklistService;
use App\Modules\Operator\Domain\OperatorException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function confirmArrival(Request $request, OperatorArrivalService $arrivals): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => ['required', 'uuid'],
            'occurrence_at' => ['required', 'string', 'max:64'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $result = $arrivals->confirm(
                (string) $validator->validated()['booking_id'],
                (string) $validator->validated()['occurrence_at'],
            );

            return view('operator.arrival-confirmation', $result);
        } catch (Throwable $exception) {
            return back()->withErrors(['arrival' => $exception instanceof OperatorException ? $exception->getMessage() : 'The arrival could not be confirmed.'])->withInput();
        }
    }

    public function recordArrival(Request $request, OperatorArrivalService $arrivals): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['confirmation_token' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $result = $arrivals->recordConfirmed((string) $validator->validated()['confirmation_token']);

            return redirect()->route('operator.attendance', $result['schedule_id'])->with('status', 'Arrival recorded for '.$result['booking_id'].'.');
        } catch (Throwable $exception) {
            return back()->withErrors(['arrival' => $exception instanceof OperatorException ? $exception->getMessage() : 'The arrival could not be recorded.'])->withInput();
        }
    }

    public function cancelArrival(Request $request, OperatorArrivalService $arrivals): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['confirmation_token' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $arrivals->cancelConfirmation((string) $validator->validated()['confirmation_token']);

            return redirect()->route('operator.dashboard');
        } catch (Throwable $exception) {
            return back()->withErrors(['arrival' => $exception instanceof OperatorException ? $exception->getMessage() : 'The arrival confirmation could not be cancelled.']);
        }
    }

    public function worklist(OperatorWorklistService $worklist, OperatorAuthorization $authorization): View
    {
        $portal = $authorization->portal();

        return view('operator.verification-worklist', [
            'arrivals' => $worklist->current(),
            'canVerify' => $authorization->has($portal['context'], OperatorAuthorization::IDENTITY_VERIFY),
        ]);
    }

    public function basicExaminationWorklist(OperatorWorklistService $worklist): View|RedirectResponse
    {
        try {
            return view('operator.basic-examination-worklist', [
                'entries' => $worklist->basicExamination(),
            ]);
        } catch (Throwable $exception) {
            return redirect()->route('operator.dashboard')->withErrors(['queue' => $exception instanceof OperatorException ? $exception->getMessage() : 'The basic-examination worklist is unavailable.']);
        }
    }

    public function startIdentityVerification(Request $request, OperatorIdentityVerificationService $identity): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'arrival_id' => ['required', 'uuid'],
            'operation_id' => ['required', 'uuid'],
            'reclaim' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $case = $identity->start(
                (string) $validator->validated()['arrival_id'],
                (string) $validator->validated()['operation_id'],
                (bool) ($validator->validated()['reclaim'] ?? false),
            );

            return redirect()->route('operator.identity-verification.show', $case['case_id']);
        } catch (Throwable $exception) {
            return back()->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'The verification case could not be started.']);
        }
    }

    public function identityVerification(string $case, OperatorIdentityVerificationService $identity): View|RedirectResponse
    {
        try {
            return view('operator.identity-verification', $identity->view($case));
        } catch (Throwable $exception) {
            return redirect()->route('operator.verification-worklist')->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'The verification case is unavailable.']);
        }
    }

    public function lookupIdentity(Request $request, string $case, OperatorIdentityVerificationService $identity): View|RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'nik' => ['required', 'string', 'max:20'],
            'at' => ['required', 'string', 'max:64'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            return view('operator.identity-verification', $identity->lookupByNik(
                $case,
                (string) $validator->validated()['nik'],
                (string) $validator->validated()['at'],
            ));
        } catch (Throwable $exception) {
            return back()->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'The identity lookup is unavailable.']);
        }
    }

    public function revealPreviousPhotos(Request $request, string $case, OperatorIdentityVerificationService $identity): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:500'],
            'operation_id' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $identity->revealPreviousPhotos($case, (string) $validator->validated()['reason'], (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.identity-verification.show', $case);
        } catch (Throwable $exception) {
            return back()->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'Previous profile photos are unavailable.']);
        }
    }

    public function retrieveIdentityAsset(string $case, string $asset, OperatorIdentityVerificationService $identity): Response|RedirectResponse
    {
        try {
            $result = $identity->retrieveAsset($case, $asset);

            return response($result['contents'], 200, [
                'Content-Type' => $result['format'],
                'Content-Disposition' => 'inline',
                'Cache-Control' => 'no-store, private',
                'Pragma' => 'no-cache',
            ]);
        } catch (Throwable $exception) {
            return redirect()->route('operator.identity-verification.show', $case)->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'The verification asset is unavailable.']);
        }
    }

    public function decideIdentity(Request $request, string $case, OperatorIdentityVerificationService $identity): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'state' => ['required', 'in:matched,mismatch_reported,insufficient_evidence'],
            'reason' => ['nullable', 'string', 'max:500'],
            'operation_id' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $identity->decide(
                $case,
                (string) $validator->validated()['state'],
                isset($validator->validated()['reason']) ? (string) $validator->validated()['reason'] : null,
                (string) $validator->validated()['operation_id'],
            );

            return redirect()->route('operator.identity-verification.show', $case)->with('status', 'Verification decision recorded.');
        } catch (Throwable $exception) {
            return back()->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'The verification decision could not be recorded.']);
        }
    }

    public function cancelIdentity(Request $request, string $case, OperatorIdentityVerificationService $identity): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:500'],
            'operation_id' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $identity->cancel($case, (string) $validator->validated()['reason'], (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.verification-worklist')->with('status', 'Verification case cancelled.');
        } catch (Throwable $exception) {
            return back()->withErrors(['identity' => $exception instanceof OperatorException ? $exception->getMessage() : 'The verification case could not be cancelled.']);
        }
    }

    public function paperConsent(string $case, OperatorPaperConsentConfirmationService $consent): View|RedirectResponse
    {
        try {
            return view('operator.paper-consent', $consent->view($case));
        } catch (Throwable $exception) {
            return redirect()->route('operator.verification-worklist')->withErrors(['consent' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper-consent case is unavailable.']);
        }
    }

    public function recordPaperConsent(Request $request, string $case, OperatorPaperConsentConfirmationService $consent): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'form_name' => ['required', 'string', 'max:64'],
            'form_version' => ['required', 'string', 'max:32'],
            'signer_type' => ['required', 'string', 'max:32'],
            'signature_confirmed' => ['accepted'],
            'signed_at' => ['required', 'string', 'max:64'],
            'operation_id' => ['required', 'uuid'],
            'scan' => ['nullable', 'file', 'max:10240'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $input = $validator->validated();
        try {
            $consent->confirm(
                $case,
                (string) $input['form_name'],
                (string) $input['form_version'],
                (string) $input['signer_type'],
                true,
                (string) $input['signed_at'],
                (string) $input['operation_id'],
                $input['scan'] ?? null,
            );

            return redirect()->route('operator.paper-consent.show', $case)->with('status', 'Paper consent confirmed.');
        } catch (Throwable $exception) {
            return back()->withErrors(['consent' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper consent could not be confirmed.'])->withInput();
        }
    }

    public function checkInTicket(string $case, OperatorCheckInTicketService $tickets): View|RedirectResponse
    {
        try {
            return view('operator.check-in-ticket', $tickets->view($case));
        } catch (Throwable $exception) {
            return redirect()->route('operator.verification-worklist')->withErrors(['ticket' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper ticket case is unavailable.']);
        }
    }

    public function issueTicket(Request $request, string $case, OperatorCheckInTicketService $tickets): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_number' => ['required', 'string', 'max:64'],
            'operation_id' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $input = $validator->validated();
        try {
            $result = $tickets->issue($case, (string) $input['ticket_number'], (string) $input['operation_id']);

            return redirect()->route('operator.paper-ticket.show', $result['ticket_id']);
        } catch (Throwable $exception) {
            return back()->withErrors(['ticket' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper ticket could not be issued.'])->withInput();
        }
    }

    public function ticketResult(string $ticket, OperatorCheckInTicketService $tickets): View|RedirectResponse
    {
        try {
            return view('operator.paper-ticket-result', ['ticket' => $tickets->show($ticket)]);
        } catch (Throwable $exception) {
            return redirect()->route('operator.dashboard')->withErrors(['ticket' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper ticket is unavailable.']);
        }
    }

    public function printTicket(string $ticket, OperatorCheckInTicketService $tickets): View|RedirectResponse
    {
        try {
            return view('operator.paper-ticket-print', ['ticket' => $tickets->show($ticket)]);
        } catch (Throwable $exception) {
            return redirect()->route('operator.dashboard')->withErrors(['ticket' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper ticket is unavailable.']);
        }
    }

    public function reprintTicket(Request $request, string $ticket, OperatorCheckInTicketService $tickets): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $tickets->reprint($ticket, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.paper-ticket.print', $ticket);
        } catch (Throwable $exception) {
            return back()->withErrors(['ticket' => $exception instanceof OperatorException ? $exception->getMessage() : 'The paper ticket reprint could not be requested.']);
        }
    }
}
