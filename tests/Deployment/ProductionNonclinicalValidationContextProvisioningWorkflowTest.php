<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionNonclinicalValidationContextProvisioningWorkflowTest extends TestCase
{
    private function workflow(): string
    {
        $workflow = file_get_contents(base_path('.github/workflows/provision-production-nonclinical-validation-context.yml'));
        $this->assertIsString($workflow);

        return $workflow;
    }

    public function test_phase_h_is_manual_least_privilege_and_exactly_authorized(): void
    {
        $workflow = $this->workflow();
        $this->assertStringContainsString("on:\n  workflow_dispatch:\n", $workflow);
        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        $this->assertStringContainsString('runs-on: self-hosted', $workflow);
        $this->assertStringContainsString("permissions:\n  contents: read", $workflow);
        $this->assertStringContainsString('AUTHORIZE_ONE_PRODUCTION_VALIDATION_CONTEXT_PROVISION', $workflow);
        $this->assertStringContainsString('MHCS_REAL_NPZ_VALIDATION_OPERATOR_PASSWORD', $workflow);
        foreach (['push:', 'pull_request:', 'schedule:', 'workflow_run:', 'docker service update', 'docker restart'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }

    public function test_phase_h_guards_revision_before_provisioning_and_reports_safe_fields(): void
    {
        $workflow = $this->workflow();
        foreach (['app_container_resolved=', 'version_current_match=', 'service_revision_match=', 'container_revision_match=', 'revision_match=', 'docker service inspect', 'VERSION-CURRENT'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $guard = strpos($workflow, 'if [ "$revision_match" != true ]; then');
        $provision = strpos($workflow, 'php artisan mhcs:provision-nonclinical-validation-context');
        $this->assertNotFalse($guard);
        $this->assertNotFalse($provision);
        $this->assertLessThan($provision, $guard);
        foreach (['validation_context_key=real-npz-e2e-v1', 'environment_guard=PASS', 'authorization_guard=PASS', 'operator_minimum_permissions=PASS', 'operator_site_assignment=PASS', 'operator_shift_assignment=PASS', 'validation_operator_login_ready=true', 'validation_context_provisioning=PASS'] as $required) {
            $this->assertSame(1, substr_count($workflow, $required));
        }
    }

    public function test_phase_h_reports_only_a_read_only_future_or_active_schedule(): void
    {
        $workflow = $this->workflow();
        foreach (['use Illuminate\\Contracts\\Console\\Kernel;', 'use Illuminate\\Support\\Facades\\DB;', 'use App\\Shared\\Validation\\NonclinicalValidationContext;', 'NonclinicalValidationContext::MARKER_NAMESPACE', 'NonclinicalValidationContext::KEY', 'validation_context_prepared=PASS', 'validation_schedule_window=', 'FUTURE', 'ACTIVE', 'ENDED', 'validation_schedule_starts_at_utc=', 'validation_schedule_ends_at_utc='] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $resolverStart = strpos($workflow, 'cat >"$resolver"');
        $resolverEnd = strpos($workflow, "PHP\n", $resolverStart);
        $this->assertNotFalse($resolverStart);
        $this->assertNotFalse($resolverEnd);
        $resolver = substr($workflow, $resolverStart, $resolverEnd - $resolverStart);
        foreach (['->insert(', '->update(', '->delete(', 'DB::insert', 'DB::update', 'DB::delete', 'drive.usercontent.google.com', 'npz', 'Mpips', 'S3'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $resolver);
        }
        $this->assertStringNotContainsString('sleep ', $workflow);
        $this->assertStringNotContainsString('/operator/', $workflow);
    }

    public function test_phase_h_uses_utc_instants_and_checks_the_exact_site_schedule_projection(): void
    {
        $workflow = $this->workflow();
        foreach (['use DateTimeImmutable;', 'use DateTimeZone;', 'new DateTimeImmutable', 'new DateTimeZone', '$endsAt > $startsAt', 'local_site_id', 'stable_operator_site_id', 'sync_status', 'eligible', 'schedule_starts_at', 'schedule_ends_at', 'quota'] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        foreach (['$schedule->starts_at > now()', '$schedule->ends_at <= now()', 'strtotime(', 'gmdate('] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $workflow);
        }
    }
}
