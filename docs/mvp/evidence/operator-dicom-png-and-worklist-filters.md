---
title: Operator DICOM PNG and worklist filter remediation evidence
status: review-required
last_updated: 2026-10-02
---

# Review identity

- Original baseline: `b1a2204a2571920a6c696575a6480aad5a1294fd`.
- Original task: `.agents/tasks/operator-dicom-png-and-worklist-filters.md @ 1c2a03c7f83f92c849589dc7b5a7d8874028d632`.
- Rejected candidate: `1ed8005e57af1bc941b1ebd4d040b3e89138c555`.
- Remediation/release governing task: same path @ `036417ad91d16af1be13ab65770915554061f6f4`; execution-start HEAD was that publication commit on the existing feature branch, clean, with unchanged candidate product source. v1.1 publication `589c3e65aa11874512a0cb7350be86562626baeb` remains historical.
- Human authorized push/merge/deploy and later explicitly authorized integrating the already-deployed recovery branch first. Recovery integration is separate under `.agents/tasks/production-recovery-main-integration.md @ 32ba927c0806b6468b3a80e40cc6db42752f2a5d`.

# Observed remediation evidence

R1: Replaced custom presentation and fragment guessing with installed Cornerstone CPU renderer, full metadata provider and frame extractor. Valid zero window center retained; native dimensions, source orientation and full edges are independent of viewer and DPR. Unequal spacing is normalized to export the native pixel grid. Installed extractor's nonzero byte-array offset assumption is normalized locally. Real downloaded 3x2 PNGs were decoded and every asymmetric RGBA sample asserted for MONOCHROME1/2, rescale, RGB and fragmented RLE, at DPR1/2. Candidate pixel regression failed before fix (zero rendered128 instead of255).

R2: Existing timeout helpers bound initialization, retrieval/body, decode, rendering and blob generation. Retrieval aborts on exit. Temporary provider, canvas, anchor and blob URL cleanup is in finally. Bootstrap import rejection/timeout restores useful translated controls, without raw PNG diagnostics. Nine synthetic exporter failure cases verify no download and cleanup; repeated failures and duplicate-click/init guards verified. Automatic refresh waits only while a PNG button is busy, then resumes.

R3: Removed competing legacy selection handler. Actual Chromium select-all/individual/indeterminate/filter/reset/submission checks and serialized FormData include only visible selected studies. Candidate reproduced five selected hidden checkboxes. Existing server authorization and ZIP tests retained. Two obsolete static assertions requiring the buggy Blade listener were removed from OperatorPortraitDicomViewerTest; actual behavioral regression coverage replaces them.

R4: Strict dates/status URL sanitization, registry-backed inverted-range alert and state/URL/count/reset on empty lists. Actual DOM refresh checks include empty-to-arriving records and the existing5000ms callback. All four row/display timestamps now share UTC-to-configured-timezone conversion; real fixtures verify23:30UTC becomes next-day06:30Asia/Jakarta. Candidate date/empty/counter/feedback regressions failed before fixes.

R5: Verification options match seven existing service states; real eligible fixtures plus ineligible/foreign-site/guest checks pass. Operational selectors omit states excluded by existing worklist eligibility. No workflow state/eligibility changes.

R6: Safe Blade JSON supplies PNG messages and shared counter/error text from lang/id.json; translator registry substitution and attribute/script escaping are tested. DOM tests exercise replacement copy.

R7: Explicit synthetic allowlist only; production-login/production-validation JavaScript tests were not executed. PNG signature, native dimensions, exact pixels and lifecycle negatives plus four-list DOM interactions complement PHP access/workflow regressions.

# Commands and results

Explicit testing environment for PHP: `APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: DB_URL= QUEUE_CONNECTION=sync MHCS_PRIVATE_OBJECT_DISK=local`.

- `vendor/bin/phpunit tests/Feature/Operator/OperatorWorklistFilterTest.php tests/Feature/Operator/Mvp14ImageGatewayIntegrationTest.php tests/Feature/Operator/OperatorPortraitDicomViewerTest.php tests/Feature/Operator/OperatorFieldOperationsSlice1Test.php tests/Feature/Operator/OperatorFieldOperationsSlice2Test.php tests/Feature/Operator/OperatorFieldOperationsSlice3Test.php tests/Feature/Operator/OperatorFieldOperationsSlice4Test.php tests/ImageGateway/ImageGatewayAiDispatchTest.php tests/Feature/Localization/MvpApplicationIndonesianUiLocalizationTest.php` —144tests,1366assertions,passed.
- `node --test tests/JavaScript/operator-dicom-viewer.test.mjs tests/JavaScript/operator-worklist-filters.test.mjs tests/JavaScript/operator-worklist-filters-browser.test.mjs tests/JavaScript/operator-dicom-png-browser.test.mjs tests/JavaScript/operator-png-bootstrap.test.mjs` —final explicit allowlist37tests,zero failures (final dot reporter37successes). Earlier run36tests passed before the additional real refresh callback case.
- `npm run build` —passed; DICOM browser bundle check passed. Existing codec fs/path browser-externalization, optional fontaine and chunk-size warnings remain.
- `vendor/bin/pint --test tests/Feature/Operator/OperatorWorklistFilterTest.php tests/Feature/Operator/OperatorPortraitDicomViewerTest.php` —passed.
- `git diff --check` —passed.
- Separate source-only deployment preflight: `vendor/bin/phpunit tests/Deployment/Wp02DeploymentTest.php tests/Deployment/ProductionDockerBuildResilienceTest.php tests/Deployment/ProductionVerificationWorkflowTest.php` —3tests,337assertions,passed. This does not establish production recovery/main compatibility.

# Limits and release separation

These are local synthetic results, not CI or live private-data evidence. Chromium supports the observed browser path; other browsers and real patient images were not exercised. Genuine initialization failures propagate and initialization waits are bounded, but a hung Cornerstone initializer was not independently injected in the browser. Supported default rendering is delegated to the installed implementation; no new formats or multi-frame selection policy were added.

G10 remained blocked for deploying PNG-only main because observed deployed `d0f99ecd604a2b0aa0f31a62396790b04028c9eb` includes recovery commits missing main. Independent PNG acceptance does not authorize deleting those fixes or establish full historical recovery/AI task acceptance. The separately human-approved integration task must close that review/release gap before deployment.
