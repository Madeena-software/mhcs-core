<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operator;

use App\Http\Controllers\Controller;
use App\Modules\Member\Domain\MemberIdentityException;
use App\Modules\Member\Domain\Mvp03Exception;
use App\Modules\Operator\Application\Services\OperatorFrontDeskService;
use App\Modules\Operator\Domain\OperatorException;
use App\Shared\Infrastructure\Idempotency\IdempotencyConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

final class FrontDeskController extends Controller
{
    public function schedules(OperatorFrontDeskService $frontDesk): View|RedirectResponse
    {
        try {
            return view('operator.front-desk.schedules.index', $frontDesk->schedules());
        } catch (Throwable $exception) {
            return $this->failure($exception, 'schedule', 'The schedule list is unavailable.');
        }
    }

    public function createSchedule(OperatorFrontDeskService $frontDesk): View|RedirectResponse
    {
        try {
            return view('operator.front-desk.schedules.create', $frontDesk->scheduleForm());
        } catch (Throwable $exception) {
            return $this->failure($exception, 'schedule', 'The schedule form is unavailable.');
        }
    }

    public function storeSchedule(Request $request, OperatorFrontDeskService $frontDesk): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'service_offering_id' => ['required', 'uuid'],
            'starts_at' => ['required', 'string', 'max:64'],
            'ends_at' => ['required', 'string', 'max:64'],
            'quota' => ['required', 'integer', 'min:1', 'max:500'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $schedule = $frontDesk->createSchedule($validator->validated());

            return redirect()->route('operator.schedules.show', $schedule['id'])->with('status', __('Schedule :reference created.', ['reference' => $schedule['display_reference']]));
        } catch (Throwable $exception) {
            return back()->withErrors(['schedule' => $this->message($exception, 'The schedule could not be created.')])->withInput();
        }
    }

    public function showSchedule(Request $request, string $schedule, OperatorFrontDeskService $frontDesk): View|RedirectResponse
    {
        try {
            return view('operator.front-desk.schedules.show', $frontDesk->schedule($schedule, $request->query('q')));
        } catch (Throwable $exception) {
            if ($exception instanceof OperatorException && in_array($exception->category, ['active_site_required', 'active_site_forbidden'], true)) {
                return redirect()->route('operator.site')->withErrors(['site' => __($exception->getMessage())]);
            }
            if ($exception instanceof Mvp03Exception) {
                abort(404);
            }

            return redirect()->route('operator.schedules.index')->withErrors(['schedule' => $this->message($exception, 'The schedule is unavailable.')]);
        }
    }

    public function searchMembers(Request $request, OperatorFrontDeskService $frontDesk): JsonResponse
    {
        $validator = Validator::make($request->all(), ['q' => ['required', 'string', 'min:2', 'max:100']]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Provide at least two characters to search for a Member.', 'errors' => $validator->errors()], 422);
        }

        try {
            return response()->json(['data' => $frontDesk->searchMembers((string) $validator->validated()['q'])]);
        } catch (Throwable $exception) {
            if ($exception instanceof OperatorException && in_array($exception->category, ['active_site_required', 'active_site_forbidden'], true)) {
                return response()->json(['message' => $exception->getMessage()], 403);
            }

            return response()->json(['message' => $this->message($exception, 'Member search is unavailable.')], 422);
        }
    }

    public function createMember(Request $request, OperatorFrontDeskService $frontDesk): View|RedirectResponse
    {
        try {
            $frontDesk->assertActiveSite();
        } catch (Throwable $exception) {
            return $this->failure($exception, 'member', 'The Member registration form is unavailable.');
        }

        return view('operator.front-desk.members.create', [
            'scheduleId' => (string) $request->query('schedule_id', ''),
            'operationId' => (string) Str::uuid(),
            'bookingOperationId' => (string) Str::uuid(),
        ]);
    }

    public function storeMember(Request $request, OperatorFrontDeskService $frontDesk): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'operation_id' => ['required', 'uuid'],
            'schedule_id' => ['nullable', 'uuid'],
            'booking_operation_id' => ['nullable', 'uuid'],
        ]);
        $validator->after(function ($validator) use ($request): void {
            if (trim((string) $request->input('email', '')) === '' && trim((string) $request->input('phone', '')) === '') {
                $validator->errors()->add('email', 'Provide an email address or phone number.');
            }
        });
        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $input = $validator->validated();
        try {
            $member = $frontDesk->registerMember(
                (string) $input['name'],
                isset($input['email']) ? (string) $input['email'] : null,
                isset($input['phone']) ? (string) $input['phone'] : null,
                (string) $input['operation_id'],
            );
            if (isset($input['schedule_id']) && $input['schedule_id'] !== '') {
                $frontDesk->bookMember(
                    (string) $input['schedule_id'],
                    (string) $member['member_id'],
                    (string) ($input['booking_operation_id'] ?? Str::uuid()),
                );

                return redirect()->route('operator.schedules.show', $input['schedule_id'])->with('status', __('Member :mrn registered and added to the schedule.', ['mrn' => $member['medical_record_number']]));
            }

            return redirect()->route('operator.members.create')->with('status', __('Member registered with MRN :mrn.', ['mrn' => $member['medical_record_number']]));
        } catch (Throwable $exception) {
            return back()->withErrors(['member' => $this->message($exception, 'The Member could not be registered.')])->withInput();
        }
    }

    public function storeParticipant(Request $request, string $schedule, OperatorFrontDeskService $frontDesk): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'member_id' => ['required', 'uuid'],
            'operation_id' => ['required', 'uuid'],
        ]);
        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $result = $frontDesk->bookMember($schedule, (string) $validator->validated()['member_id'], (string) $validator->validated()['operation_id']);

            return redirect()->route('operator.schedules.show', $schedule)->with('status', __('Member :mrn added to the schedule.', ['mrn' => $result['medical_record_number']]));
        } catch (Throwable $exception) {
            return back()->withErrors(['participant' => $this->message($exception, 'The Member could not be added to the schedule.')])->withInput();
        }
    }

    private function failure(Throwable $exception, string $field, string $fallback): RedirectResponse
    {
        if ($exception instanceof OperatorException && in_array($exception->category, ['active_site_required', 'active_site_forbidden'], true)) {
            return redirect()->route('operator.site')->withErrors(['site' => __($exception->getMessage())]);
        }

        return redirect()->route('operator.dashboard')->withErrors([$field => $this->message($exception, $fallback)]);
    }

    private function message(Throwable $exception, string $fallback): string
    {
        return $exception instanceof OperatorException
            || $exception instanceof Mvp03Exception
            || $exception instanceof MemberIdentityException
            || $exception instanceof IdempotencyConflict
            ? __($exception->getMessage())
            : __($fallback);
    }
}
