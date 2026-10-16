'use strict';

const parser = require('../../../../../node_modules/dicom-parser');
const { TextDecoder } = require('node:util');
const MAX_BYTES = 64 * 1024 * 1024;
const fail = () => { throw new Error('unverified'); };
const assert = condition => { if (!condition) fail(); };

// Read raw stdin only: identities never enter arguments, disk, or error output.
async function main() {
    const chunks = [];
    let size = 0;
    for await (const chunk of process.stdin) {
        size += chunk.length;
        assert(size <= MAX_BYTES);
        chunks.push(chunk);
    }
    const bytes = Buffer.concat(chunks);
    assert(bytes.length > 132 && bytes.toString('ascii', 128, 132) === 'DICM');
    const stream = new parser.ByteStream(parser.littleEndianByteArrayParser, bytes, 132);
    const elements = {};
    const warnings = [];
    let syntax;
    let count = 0;
    let datasetStarted = false;
    while (stream.position < bytes.length) {
        const start = stream.position;
        const tag = parser.readTag(stream);
        stream.position = start;
        const meta = tag.startsWith('x0002');
        assert(!meta || !datasetStarted);
        if (!meta) datasetStarted = true;
        if (!meta) assert(syntax === '1.2.840.10008.1.2.1' || syntax === '1.2.840.10008.1.2');
        const element = meta || syntax === '1.2.840.10008.1.2.1'
            ? parser.readDicomElementExplicit(stream, warnings)
            : parser.readDicomElementImplicit(stream);
        assert(++count <= 4096 && !elements[element.tag]);
        assert(stream.position > start && stream.position <= bytes.length);
        assert(element.length !== 0xffffffff && element.length % 2 === 0);
        if (element.vr) assert(/^(AE|AS|AT|CS|DA|DS|DT|FD|FL|IS|LO|LT|OB|OD|OF|OL|OW|PN|SH|SL|SQ|SS|ST|TM|UC|UI|UL|UN|UR|US|UT)$/.test(element.vr));
        elements[element.tag] = element;
        if (element.tag === 'x00020010') {
            syntax = bytes.subarray(element.dataOffset, element.dataOffset + element.length).toString('ascii').replace(/\0$/, '');
            assert(syntax === '1.2.840.10008.1.2.1' || syntax === '1.2.840.10008.1.2');
        }
    }
    assert(warnings.length === 0 && stream.warnings.length === 0);
    const ds = new parser.DataSet(parser.littleEndianByteArrayParser, bytes, elements);
    const charsetElement = elements.x00080005;
    const charset = charsetElement ? bytes.subarray(charsetElement.dataOffset, charsetElement.dataOffset + charsetElement.length).toString('ascii').trim() : '';
    assert(['', 'ISO_IR 6', 'ISO_IR 192'].includes(charset));
    function text(tag, max, vr, required = false) {
        const el = elements[tag];
        if (!el) { assert(!required); return null; }
        assert(el.length <= max * 4 + 2 && (!el.vr || el.vr === vr));
        const raw = bytes.subarray(el.dataOffset, el.dataOffset + el.length);
        if (charset !== 'ISO_IR 192' || vr === 'UI') assert(raw.every(byte => byte < 128));
        const value = new TextDecoder('utf-8', { fatal: true }).decode(raw).replace(vr === 'UI' ? /\0$/ : / +$/, '');
        assert(value.length <= max && !/[\x00-\x1f\x7f]/.test(value) && (!required || value.length > 0));
        return value;
    }
    function uid(tag) {
        const value = text(tag, 64, 'UI', true);
        assert(/^[012]\.(?:0|[1-9][0-9]*)(?:\.(?:0|[1-9][0-9]*))*$/.test(value));
        if (value.startsWith('0.') || value.startsWith('1.')) assert(Number(value.split('.')[1]) <= 39);
        return value;
    }
    function us(tag) {
        const el = elements[tag];
        assert(el && el.length === 2 && (!el.vr || el.vr === 'US'));
        return ds.uint16(tag);
    }
    const sopClassUid = uid('x00080016');
    const sopInstanceUid = uid('x00080018');
    assert(uid('x00020002') === sopClassUid && uid('x00020003') === sopInstanceUid);
    assert(['1.2.840.10008.5.1.4.1.1.1', '1.2.840.10008.5.1.4.1.1.1.1', '1.2.840.10008.5.1.4.1.1.1.1.1'].includes(sopClassUid));
    const metaLength = elements.x00020000;
    assert(metaLength && metaLength.vr === 'UL' && metaLength.length === 4);
    const metaVersion = elements.x00020001;
    assert(metaVersion && metaVersion.vr === 'OB' && metaVersion.length === 2);
    assert(bytes[metaVersion.dataOffset] === 0 && bytes[metaVersion.dataOffset + 1] === 1);
    uid('x00020012');
    const metaEnd = Math.max(...Object.values(elements).filter(el => el.tag.startsWith('x0002')).map(el => el.dataOffset + el.length));
    assert(ds.uint32('x00020000') === metaEnd - metaLength.dataOffset - 4);
    assert(text('x00280008', 12, 'IS') === null || text('x00280008', 12, 'IS') === '1');
    const rows = us('x00280010'), columns = us('x00280011'), bits = us('x00280100');
    assert(rows > 0 && columns > 0 && rows <= 8192 && columns <= 8192 && [8, 16].includes(bits));
    assert(us('x00280002') === 1 && ['MONOCHROME1', 'MONOCHROME2'].includes(text('x00280004', 16, 'CS', true)));
    assert([0, 1].includes(us('x00280103')) && us('x00280101') > 0 && us('x00280101') <= bits && us('x00280102') === us('x00280101') - 1);
    const pixels = elements.x7fe00010;
    const pixelBytes = rows * columns * bits / 8;
    assert(pixels && pixels.length === pixelBytes + pixelBytes % 2 && (!pixels.vr || ['OB', 'OW'].includes(pixels.vr)));
    const result = {
        packageVersion: require('../../../../../node_modules/dicom-parser/package.json').version,
        parserVersion: parser.version,
        studyInstanceUid: uid('x0020000d'), seriesInstanceUid: uid('x0020000e'), sopInstanceUid, sopClassUid,
        transferSyntaxUid: syntax, frames: 1, rows, columns,
        patientId: text('x00100020', 64, 'LO', true), patientName: text('x00100010', 64, 'PN', true),
        birthDate: text('x00100030', 8, 'DA'), sex: text('x00100040', 16, 'CS'),
        accessionNumber: text('x00080050', 16, 'SH'), studyId: text('x00200010', 16, 'SH'),
        studyDescription: text('x00081030', 64, 'LO'), bodyPart: text('x00180015', 16, 'CS'),
        projection: text('x00185101', 16, 'CS'), laterality: text('x00200062', 16, 'CS'),
    };
    if (elements.x00080060) {
        result.modality = text('x00080060', 16, 'CS', true);
        assert(['CR', 'DX'].includes(result.modality));
        assert((result.modality === 'CR') === (sopClassUid === '1.2.840.10008.5.1.4.1.1.1'));
    }
    assert(result.packageVersion === '1.8.21');
    assert(!result.birthDate || /^\d{8}$/.test(result.birthDate));
    assert(!result.sex || ['M', 'F', 'O'].includes(result.sex));
    const output = JSON.stringify(result);
    assert(Buffer.byteLength(output) <= 16384);
    process.stdout.write(output);
}

main().catch(() => { process.exitCode = 1; });
