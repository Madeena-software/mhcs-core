import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';

const script = 'app/Modules/ImageGateway/Infrastructure/Dicom/verify.cjs';
function verify(overrides) {
    // Only synthetic parser input. This does not exercise the production seccomp boundary.
    const code = "require 'vendor/autoload.php'; echo Tests\\Support\\SyntheticDicom::bytes(json_decode($argv[1], true));";
    const fixture = spawnSync('php', ['-r', code, JSON.stringify(overrides)]);
    assert.equal(fixture.status, 0);
    return spawnSync(process.execPath, [script], { input: fixture.stdout, encoding: 'utf8' });
}
test('legacy parser output keeps its original binding shape without a modality tag', () => {
    const result = verify({});
    assert.equal(result.status, 0);
    assert.equal(Object.hasOwn(JSON.parse(result.stdout), 'modality'), false);
});
test('Doctor scope uses actual DX modality and rejects inconsistent SOP classes', () => {
    const result = verify({ '00080060': ['CS', 'DX'] });
    assert.equal(result.status, 0);
    assert.equal(JSON.parse(result.stdout).modality, 'DX');
    assert.equal(verify({ '00080060': ['CS', 'CR'] }).status, 1);
    assert.equal(verify({ '00080060': ['CS', 'MR'] }).status, 1);
});
