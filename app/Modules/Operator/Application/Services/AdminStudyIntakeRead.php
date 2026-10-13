<?php

declare(strict_types=1);

namespace App\Modules\Operator\Application\Services;

use App\Modules\Member\Application\Contracts\AdminStudyReferences;
use App\Modules\Operator\Application\Contracts\AdminStudyIntakes;
use App\Shared\Authorization\AdminOperationalRead;
use App\Shared\Context\AuthenticatedContext;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdminStudyIntakeRead implements AdminStudyIntakes
{
    public function authorize(): AuthenticatedContext
    {
        $context = app(OperatorAuthorization::class)->auditRead();
        foreach (['member.admin.access', 'member.schedule.read', 'member.booking.read', 'operator.audit.read'] as $permission) {
            if (! in_array($permission, $context->permissions, true)) {
                throw new \RuntimeException('access_unconfirmed');
            }
        }

        return $context;
    }

    public function read(string $facility, string $date, string $session, int $page, string $kind = 'initial'): array
    {
        $gate = app(AdminOperationalRead::class);
        $permission = fn () => $this->authorize();
        $query = fn () => throw new \RuntimeException('integrity_unavailable');
        $metadata = ['request_kind' => $kind];
        try {
            $gate->authorize($permission);
            if ($page < 1 || $page > intdiv(PHP_INT_MAX, 25)) {
                throw new \RuntimeException('invalid_selection');
            }
            $scope = app(AdminStudyReferences::class)->read($facility, $date, $session, kind: $kind);
            if ($scope['state'] !== 'available') {
                return ['state' => $scope['state'], 'values' => null, 'observed_at' => null];
            }
            $site = $scope['values']['operator_site'];
            $gate->authorize($permission);
            $rows = DB::table('operator_queue_admissions as a')->leftJoin('operator_paper_tickets as t', 't.id', '=', 'a.operator_paper_ticket_id')->where('a.stage', 'xray')->where(fn ($q) => $q->where('a.member_schedule_id', $session)->orWhere('t.member_schedule_id', $session))->orderBy('a.created_at')->orderBy('a.id')->offset(($page - 1) * 25)->limit(26)->get(['a.id as admission_id', 'a.state', 'a.operator_site_id', 'a.member_schedule_id', 't.id as ticket_id', 't.booking_id', 't.ticket_number', 't.operator_site_id as ticket_site', 't.member_schedule_id as ticket_session']);
            $tuples = [];
            foreach ($rows->take(25) as $row) {
                if (! in_array($row->state, ['waiting', 'called', 'in_service', 'completed', 'cancelled', 'skipped', 'awaiting_ai'], true) || ! $row->ticket_id || $row->operator_site_id !== $site || $row->ticket_site !== $site || $row->member_schedule_id !== $session || $row->ticket_session !== $session || ! is_string($row->ticket_number) || $row->ticket_number === '') {
                    throw new \RuntimeException('integrity_unavailable');
                }
                $tuples[] = ['admission_id' => $row->admission_id, 'booking_id' => $row->booking_id, 'member_schedule_id' => $session, 'operator_site_id' => $site, 'ticket_reference' => $row->ticket_number];
            }
            $validated = app(AdminStudyReferences::class)->read($facility, $date, $session, $tuples, $kind);
            if ($validated['state'] !== 'available') {
                return ['state' => $validated['state'], 'values' => null, 'observed_at' => null];
            }
            if ($validated['values']['operator_site'] !== $site) {
                throw new \RuntimeException('integrity_unavailable');
            }
            foreach ($tuples as &$tuple) {
                $tuple['member_id'] = $validated['values']['members'][$tuple['booking_id']];
            }
            unset($tuple);
            $values = ['facility' => $validated['values']['facility'], 'session' => $validated['values']['session'], 'tuples' => $tuples, 'more' => $rows->count() > 25];
            $metadata += ['facility_reference' => $values['facility']['reference'], 'operational_date' => $date, 'timezone' => $values['facility']['timezone'], 'session_reference' => $values['session']['reference']];
            $query = fn () => $values;
        } catch (Throwable $exception) {
            $reason = $exception->getMessage() === 'invalid_selection' ? 'invalid_selection' : 'integrity_unavailable';
            $query = fn () => throw new \RuntimeException($reason);
        }

        return $gate->read($permission, $query, 'operator.admin-study-intakes.read', 'operator', 'study_intakes', metadata: $metadata);
    }
}
