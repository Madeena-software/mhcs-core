<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Domain\AiErrorCode;
use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\AiPacs\AiPacsPlaywrightReportDownloader;
use Symfony\Component\Process\Process;
use Tests\TestCase;

final class AiPacsPlaywrightReportDownloaderTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir().'/playwright_test_'.bin2hex(random_bytes(6));
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob("{$this->tempDir}/*") ?: []);
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    public function test_playwright_downloader_success_with_ai_report_and_image_report(): void
    {
        $mockScript = $this->createMockWorkerScript([
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'pdfPath' => 'PLACEHOLDER_PDF',
            'sha256' => 'mocked-sha256',
            'byteSize' => 109531,
            'aiReportSelected' => true,
            'imageReportSelected' => true,
            'customReportInactive' => true,
            'radiographVerified' => true,
        ]);

        $downloader = new AiPacsPlaywrightReportDownloader(
            baseUrl: 'http://124.225.183.175:8361',
            username: 'test_user',
            password: 'test_password',
            pythonBinary: 'python3',
            scriptPath: $mockScript,
        );

        $dest = $this->tempDir.'/output.pdf';
        $result = $downloader->downloadImageReport(121, 124, $dest, 'test-corr');

        $this->assertSame(hash('sha256', $result->pdfBytes), $result->checksum);
        $this->assertSame(strlen($result->pdfBytes), $result->bytes);
        $this->assertTrue($result->metadata['aiReportSelected']);
        $this->assertTrue($result->metadata['imageReportSelected']);
        $this->assertTrue($result->metadata['customReportInactive']);
        $this->assertTrue($result->metadata['radiographVerified']);
    }

    public function test_playwright_downloader_rejects_when_custom_report_is_active(): void
    {
        $mockScript = $this->createMockWorkerScript([
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'pdfPath' => 'PLACEHOLDER_PDF',
            'sha256' => 'mocked-sha256',
            'byteSize' => 109531,
            'aiReportSelected' => true,
            'imageReportSelected' => true,
            'customReportInactive' => false, // Violates policy!
        ]);

        $downloader = new AiPacsPlaywrightReportDownloader(
            baseUrl: 'http://124.225.183.175:8361',
            username: 'test_user',
            password: 'test_password',
            pythonBinary: 'python3',
            scriptPath: $mockScript,
        );

        $dest = $this->tempDir.'/output.pdf';

        try {
            $downloader->downloadImageReport(121, 124, $dest, 'test-corr');
            $this->fail('Expected ImageGatewayException when Custom Report is active');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED, $exception->category);
            $this->assertStringContainsString('Custom Report must remain inactive', $exception->getMessage());
        }
    }

    public function test_playwright_downloader_rejects_when_ai_report_not_selected(): void
    {
        $mockScript = $this->createMockWorkerScript([
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'pdfPath' => 'PLACEHOLDER_PDF',
            'sha256' => 'mocked-sha256',
            'byteSize' => 109531,
            'aiReportSelected' => false,
            'imageReportSelected' => true,
            'customReportInactive' => true,
        ]);

        $downloader = new AiPacsPlaywrightReportDownloader(
            baseUrl: 'http://124.225.183.175:8361',
            username: 'test_user',
            password: 'test_password',
            pythonBinary: 'python3',
            scriptPath: $mockScript,
        );

        $dest = $this->tempDir.'/output.pdf';

        try {
            $downloader->downloadImageReport(121, 124, $dest, 'test-corr');
            $this->fail('Expected ImageGatewayException when AI Report is not selected');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED, $exception->category);
            $this->assertStringContainsString('AI Report selection', $exception->getMessage());
        }
    }

    public function test_playwright_downloader_rejects_when_image_report_not_selected(): void
    {
        $mockScript = $this->createMockWorkerScript([
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'pdfPath' => 'PLACEHOLDER_PDF',
            'sha256' => 'mocked-sha256',
            'byteSize' => 109531,
            'aiReportSelected' => true,
            'imageReportSelected' => false,
            'customReportInactive' => true,
        ]);

        $downloader = new AiPacsPlaywrightReportDownloader(
            baseUrl: 'http://124.225.183.175:8361',
            username: 'test_user',
            password: 'test_password',
            pythonBinary: 'python3',
            scriptPath: $mockScript,
        );

        $dest = $this->tempDir.'/output.pdf';

        try {
            $downloader->downloadImageReport(121, 124, $dest, 'test-corr');
            $this->fail('Expected ImageGatewayException when Image Report is not selected');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED, $exception->category);
            $this->assertStringContainsString('Image Report selection', $exception->getMessage());
        }
    }

    public function test_playwright_downloader_redacts_credentials_on_error(): void
    {
        $sensitivePassword = 'ultra-confidential-password-123';
        $mockScript = $this->createMockWorkerScript([
            'success' => false,
            'errorCode' => 'AI_PACS_AUTH_FAILED',
            'errorMessage' => "Failed with password {$sensitivePassword} on remote",
            'pdfPath' => null,
            'sha256' => null,
            'byteSize' => 0,
            'aiReportSelected' => false,
            'imageReportSelected' => false,
            'customReportInactive' => true,
        ]);

        $downloader = new AiPacsPlaywrightReportDownloader(
            baseUrl: 'http://124.225.183.175:8361',
            username: 'test_user',
            password: $sensitivePassword,
            pythonBinary: 'python3',
            scriptPath: $mockScript,
        );

        $dest = $this->tempDir.'/output.pdf';

        try {
            $downloader->downloadImageReport(121, 124, $dest, 'test-corr');
            $this->fail('Expected ImageGatewayException');
        } catch (ImageGatewayException $exception) {
            $this->assertStringNotContainsString($sensitivePassword, $exception->getMessage());
            $this->assertStringContainsString('[REDACTED]', $exception->getMessage());
        }
    }

    public function test_playwright_worker_selector_fallback_order(): void
    {
        $pythonCode = <<<'PY'
import sys
sys.path.insert(0, 'app/Modules/ImageGateway/Infrastructure/AiPacs/Playwright')
from pacs_report_downloader import find_element

class MockElement:
    def __init__(self, name, visible=True):
        self.name = name
        self._visible = visible
    def is_visible(self):
        return self._visible
    def count(self):
        return 1

class MockPage:
    def __init__(self, available):
        self.available = available
    def get_by_role(self, role, name=None):
        if ('role', (role, name)) in self.available:
            return MockElementList([MockElement('role:' + str(name))])
        return MockElementList([])
    def get_by_text(self, text, exact=True):
        if ('text', text) in self.available:
            return MockElementList([MockElement('text:' + str(text))])
        return MockElementList([])
    def query_selector(self, css):
        if ('css', css) in self.available:
            return MockElement('css:' + str(css))
        return None

class MockElementList:
    def __init__(self, elements):
        self.elements = elements
    def count(self):
        return len(self.elements)
    @property
    def first(self):
        return self.elements[0] if self.elements else None

# 1. Role priority over text and css
page1 = MockPage({('role', ('button', 'Generate')): True, ('text', 'Generate'): True, ('css', '.gen-btn'): True})
el1 = find_element(page1, [('role', ('button', 'Generate')), ('text', 'Generate'), ('css', '.gen-btn')])
assert el1.name == 'role:Generate'

# 2. Text priority over css when role missing
page2 = MockPage({('text', 'Generate'): True, ('css', '.gen-btn'): True})
el2 = find_element(page2, [('role', ('button', 'Generate')), ('text', 'Generate'), ('css', '.gen-btn')])
assert el2.name == 'text:Generate'

# 3. CSS fallback when role and text missing
page3 = MockPage({('css', '.gen-btn'): True})
el3 = find_element(page3, [('role', ('button', 'Generate')), ('text', 'Generate'), ('css', '.gen-btn')])
assert el3.name == 'css:.gen-btn'

print("OK")
PY;

        $process = new Process(['python3', '-c', $pythonCode]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString('OK', $process->getOutput());
    }

    public function test_playwright_downloader_rejects_when_radiograph_not_verified(): void
    {
        $mockScript = $this->createMockWorkerScript([
            'success' => true,
            'errorCode' => null,
            'errorMessage' => null,
            'pdfPath' => 'PLACEHOLDER_PDF',
            'sha256' => 'mocked-sha256',
            'byteSize' => 109531,
            'aiReportSelected' => true,
            'imageReportSelected' => true,
            'customReportInactive' => true,
            'radiographVerified' => false,
        ]);

        $downloader = new AiPacsPlaywrightReportDownloader(
            baseUrl: 'http://124.225.183.175:8361',
            username: 'test_user',
            password: 'test_password',
            pythonBinary: 'python3',
            scriptPath: $mockScript,
        );

        $dest = $this->tempDir.'/output.pdf';

        try {
            $downloader->downloadImageReport(121, 124, $dest, 'test-corr');
            $this->fail('Expected ImageGatewayException when radiograph is not verified');
        } catch (ImageGatewayException $exception) {
            $this->assertSame(AiErrorCode::AI_PACS_REPORT_DOWNLOAD_FAILED, $exception->category);
            $this->assertStringContainsString('did not verify nonblank radiograph', $exception->getMessage());
        }
    }

    public function test_python_wait_for_report_canvas_nonblank_with_delayed_rendering_and_blank_rejection(): void
    {
        $pythonCode = <<<'PY'
import sys
sys.path.insert(0, 'app/Modules/ImageGateway/Infrastructure/AiPacs/Playwright')
from pacs_report_downloader import wait_for_report_canvas_nonblank

class DelayedMockPage:
    def __init__(self):
        self.call_count = 0
    def evaluate(self, script):
        self.call_count += 1
        if self.call_count < 3:
            return {'status': 'ok', 'width': 3000, 'height': 4096, 'nonZeroRange': 0, 'stdDev': 0.0}
        else:
            return {'status': 'ok', 'width': 3000, 'height': 4096, 'nonZeroRange': 5500, 'stdDev': 32.5}
    def wait_for_timeout(self, ms):
        pass

p = DelayedMockPage()
res = wait_for_report_canvas_nonblank(p, timeout_sec=5)
assert res['nonZeroRange'] == 5500
assert p.call_count >= 4

class BlankMockPage:
    def evaluate(self, script):
        return {'status': 'ok', 'width': 3000, 'height': 4096, 'nonZeroRange': 0, 'stdDev': 0.0}
    def wait_for_timeout(self, ms):
        pass

p_blank = BlankMockPage()
try:
    wait_for_report_canvas_nonblank(p_blank, timeout_sec=1)
    assert False, 'Should have raised RuntimeError on blank canvas'
except RuntimeError as e:
    assert 'blank or unrendered' in str(e)

print("CANVAS_OK")
PY;

        $process = new Process(['python3', '-c', $pythonCode]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString('CANVAS_OK', $process->getOutput());
    }

    public function test_python_verify_downloaded_pdf_radiograph_rejects_blank_and_accepts_nonblank(): void
    {
        $pythonCode = <<<PY
import sys, io, numpy as np
from PIL import Image

sys.path.insert(0, 'app/Modules/ImageGateway/Infrastructure/AiPacs/Playwright')
from pacs_report_downloader import verify_downloaded_pdf_radiograph

# 1. Blank PDF
blank_img = Image.new('L', (1000, 1400), color=255)
blank_pdf = '{$this->tempDir}/blank_report.pdf'
blank_img.convert('RGB').save(blank_pdf, 'PDF')

try:
    verify_downloaded_pdf_radiograph(blank_pdf)
    assert False, 'Should have rejected blank radiograph'
except RuntimeError as e:
    assert 'blank radiograph region' in str(e)

# 2. Non-blank PDF
arr = np.random.randint(20, 200, size=(1400, 1000), dtype=np.uint8)
valid_img = Image.fromarray(arr)
valid_pdf = '{$this->tempDir}/valid_report.pdf'
valid_img.convert('RGB').save(valid_pdf, 'PDF')

metrics = verify_downloaded_pdf_radiograph(valid_pdf)
assert metrics['radiographVerified'] is True
assert metrics['radiographPixelCount'] > 1000
assert metrics['radiographStdDev'] > 15.0

print("PDF_VERIFY_OK")
PY;

        $process = new Process(['python3', '-c', $pythonCode]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString('PDF_VERIFY_OK', $process->getOutput());
    }

    public function test_download_not_triggered_before_readiness(): void
    {
        $pythonCode = <<<'PY'
import sys
sys.path.insert(0, 'app/Modules/ImageGateway/Infrastructure/AiPacs/Playwright')
from pacs_report_downloader import wait_for_report_canvas_nonblank

download_triggered = False

class FailureMockPage:
    def evaluate(self, script):
        # Blank canvas keeps returning 0 pixels
        return {'status': 'ok', 'width': 3000, 'height': 4096, 'nonZeroRange': 0, 'stdDev': 0.0}
    def wait_for_timeout(self, ms):
        pass

p = FailureMockPage()

try:
    # 1. Canvas readiness check must pass before download trigger
    wait_for_report_canvas_nonblank(p, timeout_sec=1)
    download_triggered = True
except RuntimeError:
    pass

assert not download_triggered, "Download must never be triggered when canvas is unrendered/blank"
print("GATE_OK")
PY;

        $process = new Process(['python3', '-c', $pythonCode]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertStringContainsString('GATE_OK', $process->getOutput());
    }

    /**
     * @param  array<string, mixed>  $output
     */
    private function createMockWorkerScript(array $output): string
    {
        $validPdf = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\nxref\n0 1\n0000000000 65535 f\ntrailer<</Size 1>>\nstartxref\n50\n%%EOF";
        $validPdf = str_pad($validPdf, 256, "\n").'%%EOF';

        $scriptFile = $this->tempDir.'/mock_worker_'.bin2hex(random_bytes(4)).'.py';
        $jsonOut = json_encode($output);

        $scriptContent = <<<PYTHON
import sys, json

raw = sys.stdin.read()
input_data = json.loads(raw)
dest = input_data['destinationPath']

out_template = json.loads('''{$jsonOut}''')
success = out_template.get('success', False)
if success:
    with open(dest, 'wb') as f:
        f.write(b"""{$validPdf}""")

out = dict(out_template)
out['pdfPath'] = dest
print(json.dumps(out))
PYTHON;

        file_put_contents($scriptFile, $scriptContent);
        chmod($scriptFile, 0755);

        return $scriptFile;
    }
}
