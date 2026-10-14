<?php

declare(strict_types=1);

namespace App\Modules\ImageGateway\Infrastructure\Dicom;

use App\Modules\ImageGateway\Domain\ImageGatewayException;
use Symfony\Component\Process\Process;
use Throwable;

final class BoundedDicomVerifier
{
    public const MAX_BYTES = 64 * 1024 * 1024;

    /** Owner-resolved identity/manifest values only; this adapter never grants disclosure. */
    public function verify(string $bytes, string $sha256, int $byteSize, array $expected): array
    {
        if ($byteSize < 133 || $byteSize > self::MAX_BYTES || strlen($bytes) !== $byteSize
            || ! hash_equals($sha256, hash('sha256', $bytes))
            || ! isset($expected['patientId'], $expected['patientName'])) {
            throw $this->unverified();
        }

        $script = __DIR__.'/verify.cjs';
        $environment = array_fill_keys(array_unique([...array_keys(getenv()), ...array_keys($_ENV), ...array_keys($_SERVER)]), false);
        $environment['PATH'] = '/usr/local/bin:/usr/bin:/bin';
        $environment['LANG'] = 'C';
        $filter = $this->networkFilter();
        $filterPath = stream_get_meta_data($filter)['uri'];
        $process = new Process([
            '/usr/bin/prlimit', '--as=1073741824', '--cpu=5', '--core=0', '--fsize=0', '--nofile=32', '--',
            '/usr/bin/setpriv', '--no-new-privs', '--seccomp-filter', $filterPath, '--',
            'node', '--no-expose-wasm', '--jitless', '--max-old-space-size=64', '--permission',
            '--allow-fs-read='.$script, '--allow-fs-read='.base_path('node_modules/dicom-parser'), $script,
        ], __DIR__, $environment, $bytes, 5);
        $process->disableOutput();
        $output = '';
        $error = false;
        try {
            $process->run(function (string $type, string $chunk) use (&$output, &$error, $process): void {
                if ($type !== Process::OUT || strlen($output) + strlen($chunk) > 16384) {
                    $error = true;
                    $process->stop(0);

                    return;
                }
                $output .= $chunk;
            });
            if (! $process->isSuccessful() || $error) {
                throw $this->unverified();
            }
            $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($result) || ($result['packageVersion'] ?? null) !== '1.8.21') {
                throw $this->unverified();
            }
            foreach ($expected as $key => $value) {
                if (! array_key_exists($key, $result) || $result[$key] !== $value) {
                    throw $this->unverified();
                }
            }

            return $result + ['sha256' => $sha256, 'byteSize' => $byteSize];
        } catch (Throwable) {
            // Parser errors may carry patient bytes; never attach their output or exception.
            throw $this->unverified();
        } finally {
            if ($process->isRunning()) {
                $process->stop(0);
            }
            fclose($filter);
        }
    }

    /** Kernel filter denies socket creation and outgoing network syscalls, including x32 bypass. */
    private function networkFilter()
    {
        [$architecture, $syscalls] = match (php_uname('m')) {
            'x86_64' => [0xC000003E, [41, 42, 44, 46, 53, 307]],
            'aarch64' => [0xC00000B7, [198, 199, 203, 206, 211, 269]],
            default => throw $this->unverified(),
        };
        $instructions = [[0x20, 0, 0, 4], [0x15, 1, 0, $architecture], [0x06, 0, 0, 0x80000000],
            [0x20, 0, 0, 0], [0x35, 0, 1, 0x40000000], [0x06, 0, 0, 0x80000000]];
        foreach ($syscalls as $syscall) {
            $instructions[] = [0x15, 0, 1, $syscall];
            $instructions[] = [0x06, 0, 0, 0x00050001]; // SECCOMP_RET_ERRNO | EPERM.
        }
        $instructions[] = [0x06, 0, 0, 0x7FFF0000];
        // This anonymous file contains only static filter instructions, never DICOM or identities.
        $filter = tmpfile();
        if ($filter === false) {
            throw $this->unverified();
        }
        foreach ($instructions as $instruction) {
            if (fwrite($filter, pack('vCCV', ...$instruction)) !== 8) {
                fclose($filter);
                throw $this->unverified();
            }
        }

        return $filter;
    }

    private function unverified(): ImageGatewayException
    {
        return new ImageGatewayException('DICOM_UNVERIFIED', 'DICOM verification unavailable.');
    }
}
