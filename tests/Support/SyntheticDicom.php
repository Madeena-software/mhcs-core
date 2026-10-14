<?php

declare(strict_types=1);

namespace Tests\Support;

final class SyntheticDicom
{
    public static function bytes(array $overrides = [], bool $implicit = false, string $extra = ''): string
    {
        $sopClass = '1.2.840.10008.5.1.4.1.1.1.1.1';
        $sop = '2.25.300';
        $meta = [
            '00020001' => ['OB', "\0\1"],
            '00020002' => ['UI', $sopClass],
            '00020003' => ['UI', $sop],
            '00020010' => ['UI', $implicit ? '1.2.840.10008.1.2' : '1.2.840.10008.1.2.1'],
            '00020012' => ['UI', '2.25.400'],
        ];
        $data = [
            '00080005' => ['CS', 'ISO_IR 192'],
            '00080016' => ['UI', $sopClass], '00080018' => ['UI', $sop],
            '00080050' => ['SH', 'SYNTHETIC-EXAM'], '00081030' => ['LO', 'Synthetic chest'],
            '00100010' => ['PN', 'SYNTHETIC^ONLY'], '00100020' => ['LO', 'SYNTHETIC-MRN'],
            '00100030' => ['DA', '19900101'], '00100040' => ['CS', 'F'],
            '00180015' => ['CS', 'CHEST'], '00185101' => ['CS', 'PA'],
            '0020000d' => ['UI', '2.25.100'], '0020000e' => ['UI', '2.25.200'],
            '00200010' => ['SH', 'SYNTHETIC-STUDY'],
            '00280002' => ['US', pack('v', 1)], '00280004' => ['CS', 'MONOCHROME2'],
            '00280008' => ['IS', '1'], '00280010' => ['US', pack('v', 2)], '00280011' => ['US', pack('v', 2)],
            '00280100' => ['US', pack('v', 16)], '00280101' => ['US', pack('v', 16)],
            '00280102' => ['US', pack('v', 15)], '00280103' => ['US', pack('v', 0)],
            '7fe00010' => ['OW', pack('v*', 1, 2, 3, 4)],
        ];
        foreach ($overrides as $tag => $value) {
            if (str_starts_with($tag, '0002')) {
                $meta[$tag] = $value;
            } else {
                $data[$tag] = $value;
            }
        }
        $metaBytes = '';
        foreach ($meta as $tag => $value) {
            if ($value !== null) {
                $metaBytes .= self::element($tag, ...$value);
            }
        }
        $bytes = str_repeat("\0", 128).'DICM'.self::element('00020000', 'UL', pack('V', strlen($metaBytes))).$metaBytes;
        foreach ($data as $tag => $value) {
            if ($value !== null) {
                $bytes .= self::element($tag, $value[0], $value[1], $implicit);
            }
        }

        return $bytes.$extra;
    }

    public static function element(string $tag, string $vr, string $value, bool $implicit = false): string
    {
        if (strlen($value) % 2) {
            $value .= $vr === 'UI' ? "\0" : ' ';
        }
        $header = pack('vv', hexdec(substr($tag, 0, 4)), hexdec(substr($tag, 4)));
        if ($implicit) {
            return $header.pack('V', strlen($value)).$value;
        }
        $length = in_array($vr, ['OB', 'OW', 'SQ', 'UN', 'UT'], true)
            ? "\0\0".pack('V', strlen($value)) : pack('v', strlen($value));

        return $header.$vr.$length.$value;
    }
}
