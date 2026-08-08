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
        } catch (OperatorException) {
            abort(403);
        } catch (Throwable $exception) {
            return redirect()->route('operator.dashboard')->withErrors(['queue' => $exception instanceof OperatorException ? $exception->getMessage() : 'The basic-examination worklist is unavailable.']);
        }
    }

    public function xrayReadinessWorklist(OperatorWorklistService $worklist): View|RedirectResponse
    {
        try {
            return view('operator.xray-readiness-worklist', [
                'entries' => $worklist->xrayReadiness(),
            ]);
        } catch (OperatorException) {
            abort(403);
        } catch (Throwable) {
            return redirect()->route('operator.dashboard')->withErrors(['queue' => 'The X-ray readiness worklist is unavailable.']);
        }
    }

    public function claimBasicExamination(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->claimBasicExamination($admission, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.basic-examination-worklist')->with('status', 'Queue admission claimed.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'queue_claim_conflict') {
                abort(409);
            }
            if ($exception->category === 'queue_claim_failure') {
                return back()->withErrors(['queue' => 'The queue admission could not be claimed.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['queue' => 'The queue admission could not be claimed.']);
        }
    }

    public function claimXray(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->claimXray($admission, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.xray-readiness-worklist')->with('status', 'X-ray admission claimed.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'xray_claim_conflict') {
                abort(409);
            }
            if ($exception->category === 'xray_claim_failure') {
                return back()->withErrors(['queue' => 'The X-ray admission could not be claimed.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['queue' => 'The X-ray admission could not be claimed.']);
        }
    }

    public function callXray(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->callXray($admission, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.xray-readiness-worklist')->with('status', 'X-ray admission called.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'xray_call_conflict') {
                abort(409);
            }
            if ($exception->category === 'xray_call_failure') {
                return back()->withErrors(['queue' => 'The X-ray admission could not be called.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['queue' => 'The X-ray admission could not be called.']);
        }
    }

    public function callBasicExamination(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->callBasicExamination($admission, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.basic-examination-worklist')->with('status', 'Queue admission called.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'queue_call_conflict') {
                abort(409);
            }
            if ($exception->category === 'queue_call_failure') {
                return back()->withErrors(['queue' => 'The queue admission could not be called.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['queue' => 'The queue admission could not be called.']);
        }
    }

    public function startBasicExamination(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->startBasicExamination($admission, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.basic-examination-worklist')->with('status', 'Queue admission started.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'queue_start_conflict') {
                abort(409);
            }
            if ($exception->category === 'queue_start_failure') {
                return back()->withErrors(['queue' => 'The queue admission could not be started.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['queue' => 'The queue admission could not be started.']);
        }
    }

    public function basicExaminationVitalSigns(string $admission, OperatorWorklistService $worklist): View|RedirectResponse
    {
        try {
            return view('operator.basic-examination-vital-signs', $worklist->vitalSignsForm($admission));
        } catch (OperatorException) {
            abort(403);
        } catch (Throwable) {
            return redirect()->route('operator.dashboard')->withErrors(['vital_signs' => 'The vital-signs form is unavailable.']);
        }
    }

    public function recordBasicExaminationVitalSigns(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'operation_id' => ['required', 'uuid'],
            'systolic_bp_value' => ['nullable', 'numeric'],
            'systolic_bp_missing_reason' => ['nullable', 'in:unavailable,refused,not_applicable'],
            'diastolic_bp_value' => ['nullable', 'numeric'],
            'diastolic_bp_missing_reason' => ['nullable', 'in:unavailable,refused,not_applicable'],
            'temperature_value' => ['nullable', 'numeric'],
            'temperature_missing_reason' => ['nullable', 'in:unavailable,refused,not_applicable'],
            'height_value' => ['nullable', 'numeric'],
            'height_missing_reason' => ['nullable', 'in:unavailable,refused,not_applicable'],
            'weight_value' => ['nullable', 'numeric'],
            'weight_missing_reason' => ['nullable', 'in:unavailable,refused,not_applicable'],
            'bmi_missing_reason' => ['nullable', 'in:unavailable,refused,not_applicable'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            foreach (['systolic_bp', 'diastolic_bp', 'temperature', 'height', 'weight'] as $field) {
                $hasValue = $this->hasInput($request->input($field.'_value'));
                $hasReason = $this->hasInput($request->input($field.'_missing_reason'));
                if ($hasValue === $hasReason) {
                    $validator->errors()->add($field.'_value', 'Provide a value or a missing reason.');
                }
                if (in_array($field, ['height', 'weight'], true) && $hasValue) {
                    $value = $request->input($field.'_value');
                    if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value <= 0) {
                        $validator->errors()->add($field.'_value', 'Provide a finite positive measurement.');
                    }
                }
            }

            $hasHeight = $this->hasInput($request->input('height_value'));
            $hasWeight = $this->hasInput($request->input('weight_value'));
            $hasBmiReason = $this->hasInput($request->input('bmi_missing_reason'));
            if ($hasHeight && $hasWeight && $hasBmiReason) {
                $validator->errors()->add('bmi_missing_reason', 'BMI is calculated from height and weight.');
            }
            if ((! $hasHeight || ! $hasWeight) && ! $hasBmiReason) {
                $validator->errors()->add('bmi_missing_reason', 'Provide a missing reason when BMI cannot be calculated.');
            }
        });
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->recordBasicExaminationVitalSigns(
                $admission,
                (string) $validator->validated()['operation_id'],
                $validator->validated(),
            );

            return redirect()->route('operator.basic-examination-worklist')->with('status', 'Vital signs recorded.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'vital_signs_conflict') {
                abort(409);
            }
            if ($exception->category === 'vital_signs_failure') {
                return back()->withErrors(['vital_signs' => 'The vital-signs record could not be saved.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['vital_signs' => 'The vital-signs record could not be saved.']);
        }
    }

    public function completeBasicExamination(Request $request, string $admission, OperatorWorklistService $worklist): RedirectResponse
    {
        $validator = Validator::make($request->all(), ['operation_id' => ['required', 'uuid']]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $worklist->completeBasicExamination($admission, (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.basic-examination-worklist')->with('status', 'Basic examination completed. X-ray is ready.');
        } catch (OperatorException $exception) {
            if ($exception->category === 'queue_completion_conflict') {
                abort(409);
            }
            if ($exception->category === 'queue_completion_failure') {
                return back()->withErrors(['queue' => 'The basic examination could not be completed.']);
            }

            abort(403);
        } catch (Throwable) {
            return back()->withErrors(['queue' => 'The basic examination could not be completed.']);
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

    private function hasInput(mixed $value): bool
    {
        return $value !== null && trim((string) $value) !== '';
    }
}
