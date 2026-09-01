<?php

declare(strict_types=1);

namespace Tests\Deployment;

use Tests\TestCase;

final class ProductionExactDicomRemediationWorkflowTest extends TestCase
{
    public function test_workflow_is_manual_only_exactly_two_modes_and_guarded(): void
    {
        $path = base_path('.github/workflows/remediate-production-dicom.yml');
        $this->assertFileExists($path);

        $workflow = file_get_contents($path);
        $this->assertIsString($workflow);

        $this->assertSame(1, substr_count($workflow, 'workflow_dispatch:'));
        foreach (['push:', 'pull_request:', 'schedule:', 'workflow_run:', 'repository_dispatch:'] as $automaticTrigger) {
            $this->assertStringNotContainsString($automaticTrigger, $workflow);
        }
        foreach (['t005_failed_capture_retry', 'dcm_zshnsx90_regenerate'] as $mode) {
            $this->assertStringContainsString($mode, $workflow);
        }
        foreach ([
            'authorization_marker',
            'expected_application_revision',
            'EXPECTED_MPIPS_REVISION',
            'f2bf7b9980f9af7649e1a6c45c46aaee7a55a36a',
            'production-deployment-mhcs_core',
            'contents: read',
            'mhcs:remediate-production-dicom',
            '46165c59-1fa6-4f58-9485-a515529c0f76',
            'ed367bcf-4430-496c-a006-f3e8479421d4',
            'DCM-ZSHNSX90',
            'T005',
            'preflight',
        ] as $required) {
            $this->assertStringContainsString($required, $workflow);
        }
        $this->assertStringNotContainsString('capture_id:', $workflow);
        $this->assertStringNotContainsString('study_id:', $workflow);
        $this->assertStringContainsString('cancel-in-progress: false', $workflow);
        $this->assertStringContainsString('git -C /app merge-base --is-ancestor', $workflow);
        $this->assertStringContainsString('mpips_fix_containment_unproven', $workflow);
        $this->assertStringContainsString('verified-ancestor:', $workflow);
        $this->assertStringNotContainsString('EXPECTED_MPIPS_REVISION" == "$REQUIRED_MPIPS_FIX', $workflow);
    }
}
