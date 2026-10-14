<?php

declare(strict_types=1);

namespace Tests\ImageGateway;

use App\Modules\ImageGateway\Domain\ImageGatewayException;
use App\Modules\ImageGateway\Infrastructure\Dicom\BoundedDicomVerifier;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\Process;
use Tests\Support\SyntheticDicom;
use Tests\TestCase;

final class BoundedDicomVerifierTest extends TestCase
{
    private const IDENTITY = ['patientId' => 'SYNTHETIC-MRN', 'patientName' => 'SYNTHETIC^ONLY'];

    public function test_explicit_and_implicit_direct_and_supplied_uid_converted_inputs(): void
    {
        foreach ([false, true] as $implicit) {
            $bytes = SyntheticDicom::bytes(implicit: $implicit);
            $digest = hash('sha256', $bytes);
            $result = (new BoundedDicomVerifier)->verify($bytes, $digest, strlen($bytes), self::IDENTITY);
            $this->assertSame('2.25.100', $result['studyInstanceUid']);
            $this->assertSame('2.25.300', $result['sopInstanceUid']);
            $this->assertSame('1.8.21', $result['packageVersion']);
            $this->assertSame('1.8.12', $result['parserVersion']);
            $this->assertSame(1, $result['frames']);
            $this->assertSame($digest, hash('sha256', $bytes));
        }
        $bytes = SyntheticDicom::bytes(['0020000d' => ['UI', '2.25.987654321']]);
        $this->assertSame('2.25.987654321', $this->verify($bytes, self::IDENTITY + ['studyInstanceUid' => '2.25.987654321'])['studyInstanceUid']);
    }

    #[DataProvider('invalidInputs')]
    public function test_unsafe_dataset_is_rejected_without_output_or_patient_error(array $overrides, string $extra = ''): void
    {
        $bytes = SyntheticDicom::bytes($overrides, extra: $extra);
        try {
            $this->verify($bytes);
            $this->fail('Unsafe DICOM was accepted.');
        } catch (ImageGatewayException $exception) {
            $this->assertSame('DICOM_UNVERIFIED', $exception->category);
            $this->assertSame('DICOM verification unavailable.', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public static function invalidInputs(): array
    {
        return [
            'missing UID' => [['0020000d' => null]],
            'invalid UID' => [['0020000d' => ['UI', '2.025.1']]],
            'file meta conflict' => [['00020003' => ['UI', '2.25.999']]],
            'file meta class conflict' => [['00020002' => ['UI', '2.25.999']]],
            'file meta version conflict' => [['00020001' => ['OB', "\0\2"]]],
            'unsupported syntax' => [['00020010' => ['UI', '1.2.840.10008.1.2.1.99']]],
            'multiple frames' => [['00280008' => ['IS', '2']]],
            'charset escape' => [['00080005' => ['CS', 'ISO 2022 IR 87']]],
            'invalid UTF8' => [['00100010' => ['PN', "SYNTHETIC\xff"]]],
            'wrong VR' => [['00100020' => ['UI', 'SYNTHETIC-MRN']]],
            'invalid VR' => [['00100020' => ['ZZ', 'SYNTHETIC-MRN']]],
            'short pixels' => [['7fe00010' => ['OW', "\0\0"]]],
            'dimension limit' => [['00280010' => ['US', pack('v', 9000)]]],
            'duplicate root identity' => [[], SyntheticDicom::element('00100020', 'LO', 'SYNTHETIC-MRN')],
            'truncated trailing element' => [[], pack('vv', 0x0010, 0x0020).'LO'.pack('v', 50).'x'],
            'malformed sequence' => [['00081111' => ['SQ', 'NOT-A-SEQUENCE']]],
        ];
    }

    public function test_owner_identity_uid_and_hash_conflicts_are_rejected(): void
    {
        $bytes = SyntheticDicom::bytes();
        foreach ([['patientId' => 'OTHER'], ['patientName' => 'OTHER'], ['birthDate' => '20000101'], ['sex' => 'M'], ['accessionNumber' => 'OTHER'], ['studyInstanceUid' => '2.25.999']] as $conflict) {
            try {
                $this->verify($bytes, array_replace(self::IDENTITY, $conflict));
                $this->fail('Conflicting owner evidence was accepted.');
            } catch (ImageGatewayException $exception) {
                $this->assertSame('DICOM_UNVERIFIED', $exception->category);
            }
        }
        $this->expectException(ImageGatewayException::class);
        (new BoundedDicomVerifier)->verify($bytes, str_repeat('0', 64), strlen($bytes), self::IDENTITY);
    }

    public function test_oversized_and_truncated_inputs_fail_closed(): void
    {
        foreach ([str_repeat('x', BoundedDicomVerifier::MAX_BYTES + 1), substr(SyntheticDicom::bytes(), 0, -2), 'DICM'] as $bytes) {
            try {
                $this->verify($bytes);
                $this->fail('Invalid input was accepted.');
            } catch (ImageGatewayException $exception) {
                $this->assertSame('DICOM_UNVERIFIED', $exception->category);
            }
        }
    }

    public function test_exception_traces_redact_bytes_and_owner_identity(): void
    {
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            (new BoundedDicomVerifier)->verify('SYNTHETIC-BYTE-SENTINEL', str_repeat('0', 64), 23, ['patientId' => 'SYNTHETIC-IDENTITY-SENTINEL']);
            $this->fail('Invalid input was accepted.');
        } catch (ImageGatewayException $exception) {
            $frame = array_values(array_filter($exception->getTrace(), fn ($frame) => ($frame['class'] ?? '') === BoundedDicomVerifier::class && ($frame['function'] ?? '') === 'verify'))[0];
            $this->assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][0]);
            $this->assertInstanceOf(\SensitiveParameterValue::class, $frame['args'][3]);
            $this->assertStringNotContainsString('SYNTHETIC-BYTE-SENTINEL', (string) $exception);
            $this->assertStringNotContainsString('SYNTHETIC-IDENTITY-SENTINEL', (string) $exception);
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
    }

    public function test_kernel_network_denial_and_cpu_memory_limits(): void
    {
        $filter = (new ReflectionMethod(BoundedDicomVerifier::class, 'networkFilter'))->invoke(new BoundedDicomVerifier);
        try {
            $process = new Process([
                'setpriv', '--no-new-privs', '--seccomp-filter', stream_get_meta_data($filter)['uri'], '--',
                'node', '-e', 'const s=require("node:net").connect(9,"127.0.0.1");s.on("error",e=>{if(e.code!=="EPERM")process.exit(1)});',
            ]);
            $process->mustRun();
            $this->assertSame('', $process->getOutput());
            $this->assertSame('', $process->getErrorOutput());
        } finally {
            fclose($filter);
        }
        $cpu = new Process(['prlimit', '--cpu=1', '--core=0', '--', 'node', '--jitless', '-e', 'while(true){}'], timeout: 3);
        try {
            $cpu->run();
        } catch (ProcessSignaledException $exception) {
            $this->assertContains($exception->getSignal(), [9, 24]);
        }
        $this->assertFalse($cpu->isSuccessful());
        $memory = new Process(['prlimit', '--as=1073741824', '--core=0', '--', 'node', '--jitless', '--max-old-space-size=64', '-e', 'Buffer.alloc(2*1024*1024*1024)']);
        $memory->run();
        $this->assertFalse($memory->isSuccessful());
    }

    private function verify(string $bytes, array $expected = self::IDENTITY): array
    {
        return (new BoundedDicomVerifier)->verify($bytes, hash('sha256', $bytes), strlen($bytes), $expected);
    }
}
