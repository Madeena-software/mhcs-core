<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Application\Services;

use App\Modules\ImageGateway\Application\Contracts\AdminStudyWorklist;
use App\Modules\ImageGateway\Domain\AiJobStatus;
use App\Modules\Operator\Application\Contracts\AdminStudyIntakes;
use App\Shared\Authorization\AdminOperationalRead;
use Illuminate\Support\Facades\DB;
use Throwable;

final class AdminStudyWorklistRead implements AdminStudyWorklist
{
    public function read(string $facility, string $date, string $session, int $page, string $kind = 'initial'): array
    {
        $gate = app(AdminOperationalRead::class);
        $permission = fn () => app(AdminStudyIntakes::class)->authorize();
        try {
            $gate->authorize($permission);
            $intakes = app(AdminStudyIntakes::class)->read($facility, $date, $session, $page, $kind);
            if ($intakes['state'] !== 'available') {
                return ['state' => $intakes['state'], 'values' => null, 'observed_at' => null];
            }
        } catch (Throwable) {
            return $gate->read($permission, fn () => throw new \RuntimeException('integrity_unavailable'), 'image-gateway.admin-study-worklist.read', 'image-gateway', 'study_worklist', metadata: ['request_kind' => $kind]);
        }
        $values = $intakes['values'];

        return $gate->read($permission, function () use ($values) {
            $tuples = $values['tuples'];
            $ids = array_column($tuples, 'admission_id');
            $bookingIds = array_column($tuples, 'booking_id');
            $captures = DB::table('image_gateway_capture_sets')->where(fn ($q) => $q->whereIn('admission_id', $ids)->orWhereIn('booking_id', $bookingIds))->limit(26)->get(['id', 'admission_id', 'booking_id', 'member_schedule_id', 'operator_site_id', 'status', 'accepted_at', 'processing_status']);
            if ($captures->count() > count($tuples)) {
                throw new \RuntimeException('integrity_unavailable');
            }
            $studies = DB::table('image_gateway_studies')->whereIn('capture_set_id', $captures->pluck('id'))->limit(26)->get(['id', 'capture_set_id', 'display_reference', 'format']);
            $jobs = DB::table('image_gateway_ai_jobs')->where(fn ($q) => $q->whereIn('study_id', $studies->pluck('id'))->orWhereIn('capture_set_id', $captures->pluck('id'))->orWhereIn('admission_id', $ids)->orWhereIn('booking_id', $bookingIds))->limit(26)->get(['study_id', 'capture_set_id', 'booking_id', 'member_id', 'admission_id', 'status']);
            if ($studies->count() > $captures->count() || $jobs->count() > $studies->count()) {
                throw new \RuntimeException('integrity_unavailable');
            }
            $rows = [];
            foreach ($tuples as $tuple) {
                $row = ['ticket_reference' => $tuple['ticket_reference'], 'study_reference' => null, 'receipt' => 'not_received', 'processing' => 'not_applicable', 'dicom' => 'unavailable', 'ai' => 'not_applicable'];
                $related = $captures->filter(fn ($c) => $c->admission_id === $tuple['admission_id'] || $c->booking_id === $tuple['booking_id']);
                if ($related->count() > 1) {
                    throw new \RuntimeException('integrity_unavailable');
                }
                $capture = $related->first();
                $relatedJobs = $jobs->filter(fn ($j) => $j->admission_id === $tuple['admission_id'] || $j->booking_id === $tuple['booking_id'] || ($capture && $j->capture_set_id === $capture->id));
                if (! $capture && $relatedJobs->isNotEmpty()) {
                    throw new \RuntimeException('integrity_unavailable');
                }
                if ($capture) {
                    foreach (['admission_id', 'booking_id', 'member_schedule_id', 'operator_site_id'] as $field) {
                        if ($tuple[$field] !== $capture->$field) {
                            throw new \RuntimeException('integrity_unavailable');
                        }
                    }
                    if (! in_array($capture->status, ['capturing', 'accepted'], true) || ! in_array($capture->processing_status, ['pending', 'processing', 'retrying', 'completed', 'failed'], true)) {
                        throw new \RuntimeException('integrity_unavailable');
                    }
                    $associated = $studies->where('capture_set_id', $capture->id);
                    if ($associated->count() > 1 || $relatedJobs->count() > 1) {
                        throw new \RuntimeException('integrity_unavailable');
                    }
                    $study = $associated->first();
                    $job = $relatedJobs->first();
                    if ($capture->status === 'accepted' && ! $capture->accepted_at) {
                        throw new \RuntimeException('integrity_unavailable');
                    }
                    $row['receipt'] = $capture->status === 'accepted' ? 'accepted' : 'incomplete';
                    $row['processing'] = $capture->status === 'accepted' ? $capture->processing_status : 'not_applicable';
                    if ($study) {
                        if ($capture->status !== 'accepted' || $study->format !== 'application/dicom' || ! $study->display_reference) {
                            throw new \RuntimeException('integrity_unavailable');
                        }
                        $row['study_reference'] = $study->display_reference;
                        $row['dicom'] = 'available';
                        $row['ai'] = 'not_queued';
                    }
                    if ($job) {
                        if (! $study || $job->study_id !== $study->id || $job->capture_set_id !== $capture->id || $job->booking_id !== $tuple['booking_id'] || $job->member_id !== $tuple['member_id'] || $job->admission_id !== $tuple['admission_id'] || ! AiJobStatus::isValid($job->status)) {
                            throw new \RuntimeException('integrity_unavailable');
                        }
                        $row['ai'] = $job->status;
                    }
                }
                $rows[] = $row;
            }

            return ['facility' => $values['facility'], 'session' => $values['session'], 'rows' => $rows, 'more' => $values['more']];
        }, 'image-gateway.admin-study-worklist.read', 'image-gateway', 'study_worklist', metadata: ['request_kind' => $kind, 'facility_reference' => $values['facility']['reference'], 'operational_date' => $date, 'timezone' => $values['facility']['timezone'], 'session_reference' => $values['session']['reference']]);
    }
}
