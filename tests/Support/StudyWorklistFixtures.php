<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class StudyWorklistFixtures
{
    public static function intake(array $f, string $ticket = 'T-001', string $state = 'waiting'): array
    {
        $booking = AdminOperationsFixtures::booking($f, 'completed');
        AdminOperationsFixtures::arrival($f, $booking);
        $profile = DB::table('operator_profiles')->where('user_id', $f['admin']->id)->value('id');
        $ticketId = (string) Str::uuid();
        $admission = (string) Str::uuid();
        DB::table('operator_paper_tickets')->insert(['id' => $ticketId, 'booking_id' => $booking, 'member_schedule_id' => $f['scheduleId'], 'operator_site_id' => $f['operatorSiteId'], 'operator_profile_id' => $profile, 'ticket_number' => $ticket, 'issued_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        DB::table('operator_queue_admissions')->insert(['id' => $admission, 'operator_paper_ticket_id' => $ticketId, 'operator_site_id' => $f['operatorSiteId'], 'member_schedule_id' => $f['scheduleId'], 'queue_class' => 'general', 'stage' => 'xray', 'state' => $state, 'ready_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        return compact('booking', 'profile', 'ticketId', 'admission');
    }

    public static function capture(array $f, array $intake, string $processing = 'pending', bool $accepted = true): string
    {
        $id = (string) Str::uuid();
        DB::table('image_gateway_capture_sets')->insert(['id' => $id, 'submission_id' => 'synthetic-'.$id, 'admission_id' => $intake['admission'], 'booking_id' => $intake['booking'], 'member_schedule_id' => $f['scheduleId'], 'operator_site_id' => $f['operatorSiteId'], 'operator_profile_id' => $intake['profile'], 'radiograph_count' => 1, 'status' => $accepted ? 'accepted' : 'capturing', 'accepted_at' => $accepted ? now() : null, 'processing_status' => $processing, 'radiograph_status' => 'success', 'gain_status' => $accepted ? 'success' : 'pending', 'last_error_code' => 'PRIVATE_ERROR_SENTINEL', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public static function study(string $capture): string
    {
        $id = (string) Str::uuid();
        DB::table('image_gateway_studies')->insert(['id' => $id, 'capture_set_id' => $capture, 'display_reference' => 'DCM-'.Str::upper(Str::random(8)), 'object_key' => 'PRIVATE_KEY_SENTINEL/'.$id, 'checksum' => str_repeat('a', 64), 'bytes' => 100, 'format' => 'application/dicom', 'study_instance_uid' => $id, 'series_instance_uid' => $id, 'sop_instance_uid' => $id, 'filename' => 'PRIVATE_FILENAME_SENTINEL.dcm', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }

    public static function job(array $intake, string $capture, string $study, string $status = 'queued'): string
    {
        $id = (string) Str::uuid();
        DB::table('image_gateway_ai_jobs')->insert(['id' => $id, 'study_id' => $study, 'capture_set_id' => $capture, 'booking_id' => $intake['booking'], 'member_id' => DB::table('bookings')->where('id', $intake['booking'])->value('member_id'), 'admission_id' => $intake['admission'], 'status' => $status, 'last_error_message' => 'PRIVATE_FINDINGS_SENTINEL https://private.example.test/report', 'created_at' => now(), 'updated_at' => now()]);

        return $id;
    }
}
