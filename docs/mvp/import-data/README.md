# Friday B2B roster import input

**Status:** Approved Friday-only input handling

The B2B partner supplies the raw roster as a PDF in this directory. The PDF
and every roster or credential data file remain ignored by Git and must never
be committed, copied into planning documents, test fixtures, logs, or command
output.

For the 37-member Friday import, a Member Administrator manually transcribes
the supplied PDF into a UTF-8 CSV file in this same directory. The importer
accepts this exact header row:

```text
name,birthplace,birth_date,ktp_address,nik
```

`birth_date` uses `YYYY-MM-DD`; `nik` is treated as text so leading zeroes are
preserved. The CSV is a local one-time import input, not an application upload
or a Member-facing file.

The import process validates the header, required cells, date format, exact
NIK format, duplicate NIK values in the file, and duplicate protected NIKs in
MHCS before it changes data. It must fail without a partial import when any
row is invalid. It creates no plaintext-password record, log, or repository
artifact. The separate credential spreadsheet is delivered once to the
designated B2B contact outside MHCS after release-candidate verification.
