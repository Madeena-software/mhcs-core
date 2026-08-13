# Local Operator-to-DICOM walkthrough

This is a disposable, non-clinical local rehearsal. Use the existing native
Laravel server and database queue only. Local private objects use the
filesystem (`MHCS_PRIVATE_OBJECT_DISK=local`); production keeps private S3.
MHCS stores plain private bytes, and only the existing Image Gateway worker
crosses the queued MPIPS boundary after capture acceptance.

## Start

Confirm variable names and values locally without printing them: local mode,
loopback MySQL, `QUEUE_CONNECTION=database`, and the local private disk. Then
reset only the disposable database and `storage/app/private/objects` after
confirming the private parent and target are real non-symlink paths. Run:

```bash
TARGET="." php artisan migrate:fresh --force --quiet
TARGET="." php artisan db:seed --quiet --class=Database\\Seeders\\MvpCoreClinicSeeder
PHP_CLI_SERVER_WORKERS=4 TARGET="." php artisan serve --no-reload --host=127.0.0.1 --port=8013
TARGET="." php artisan queue:work database --queue=image-gateway --timeout=390
```

Leave exactly four native HTTP workers and one `image-gateway` worker running.
Do not inspect logs, private objects, NPZ, DICOM, credentials, AWS/S3, or
MPIPS responses. The ignored credential file is mode `0600`; read it locally
only and never copy its contents.

## User checklist

1. Open `http://127.0.0.1:8013/operator/login`, sign in as the primary seeded
   Operator, select the active site/current shift, and open its already-called
   X-ray capture. Confirm there is no capture set or DICOM and the existing
   upload form is immediately available.
2. Submit the approved local non-clinical radiograph/gain NPZ pair once.
   Observe progress and disabled inputs. At safe `queued`/`processing`, close
   and reopen the page; confirm durable polling and retry only a reported
   unsuccessful component.
3. When ready, confirm the results list uses a short `DCM-…` reference. Open
   the study in the current tab and verify a centred portrait-suitable,
   read-only viewer with automatic VOI and zoom/pan only. Use **Unduh DICOM**
   for the normal authenticated attachment download.
4. Select **Buka di monitor**. Confirm one named compact popup opens for the
   same protected study, remains usable after portrait resize, preserves the
   reference/state/download, and hides broad workstation navigation. If the
   browser blocks it, confirm the safe Indonesian fallback and continue in the
   current tab.
5. If the viewer cannot load, confirm it leaves “Memuat DICOM…” for the safe
   Indonesian error state with download and return actions. Do not manufacture
   a failure by changing code, data, or configuration.
6. Sign in as the second same-site/current-shift Operator. Confirm the first
   returned study remains discoverable, viewable, and normally downloadable;
   then open the second Operator’s own already-called X-ray capture and confirm
   it is ready for a separate NPZ pair. Confirm unauthorised access is denied
   through the existing safe behavior.

Return only sanitized PASS/FAIL, symptoms, and non-sensitive screenshots.
Manual outcomes go to Planner/Reviewer; they are not release authorization.
