# MHCS Core source coverage index

This is a planning and conformance-analysis artifact for the local approved
specification baseline. It does not implement features and does not establish
full MHCS Core product conformance. Mocks, stubs, interfaces, prototypes, and
planning artifacts do not satisfy production requirements.

## Baseline metadata

| Field | Value |
|---|---|
| Declared source context commit | `e9f5e9f76b09f0327f50c88e926813566efd60c0` |
| Source-commit correspondence | `unverified` — the declared object is unavailable in the target repository and no external repository was accessed; local files were not compared with the source revision |
| Target repository | `/var/www/mhcs-core` |
| Target commit | `e3c01162f892aacf2aa5d997d8ac5be3c25dfe8a` |
| Target branch | `main` |
| Analysis date | `2026-08-04` |
| Initial working tree | Clean; no staged, modified, or untracked paths; `docs/` did not exist |
| Local baseline authority | The seven files listed below and their SHA-256 digests |

### Mandatory local specifications

| Repository-relative path | SHA-256 |
|---|---|
| `.agents/context/project.md` | `e79fb274984b349abf4b261c1c31d3edb77265ae1800afb512392e2a79ff8180` |
| `.agents/context/modules/member/project.md` | `03a12418e3823c6781855a3166a9a30b0ffef8205e879a7cb8f470bd4ac42280` |
| `.agents/context/modules/operator/project.md` | `db828511b28b1aa7983ffa51852fcd8f38f8dd804deb4374ffb69054e5b708be` |
| `.agents/context/modules/doctor/project.md` | `bc6bdac5657e01756f8494a15dd4b424ba6d24d5b89cdc05ce51d7ec04512480` |
| `.agents/context/modules/image-gateway/project.md` | `779bd0ab8b467ee06c20dcd4f9154eaba3ef2ac74b4145db082452934faf3af3` |
| `.agents/context/ui-language.md` | `e6ddcc96295b0990101a5b2b0b38c9886a37a550fe35fab6a2d11aee2f2251248` |
| `.agents/context/design/mhcs-core-design.html` | `365e199b98eb00add13f15ef82221dba3fb778fe5610bbbebeac4e67e3b177a6` |

`E0` in the matrix means that repository inspection found only `AGENTS.md` and
`.agents/`; there is no application source, Composer or frontend manifest,
configuration, migration, route, queue, adapter, storage, or test evidence.
The source-coverage mappings below therefore describe the required future
evidence, not current implementation.

## Markdown heading ledger

The exact heading-to-identifier reconciliation appears below under **Exact matrix
reconciliation** and is authoritative. It was generated from the current matrix
source locators, so repeated headings, prose-only sections, procedural sections,
and acceptance sections remain observable without maintaining a second compact
mapping that could drift. A heading with no extracted row is explicitly retained
there with a section-specific rationale and remains a manual audit item when its
prose may contain an obligation.

## Design coverage ledger

The HTML is an approved visual and interaction reference, not implementation
evidence. Repeated CSS declarations and repeated markup instances are grouped
into the underlying design rule. The prototype's sample names, NIK values,
clinical text, alert calls, and simulated DICOM are not production data or
production behavior.

| Stable design locator | Implementation-relevant item | Coverage |
|---|---|---|
| `html[data-density]` and `style:tokens` | Global reset, font families, density switch, spacing, radii, shadows, semantic colors, compact/comfortable variants | `DES-001` |
| `#master-switcher-bar` | Persistent MHCS Core Suite header and member/operator portal switcher | `DES-002` |
| `.portal-member .app-root` | Member shell: dark top bar, breadcrumbs, role tools, sidebar, scrollable content viewport | `DES-003` |
| `#nav-member`, `#nav-operator` | Grouped navigation, active state, member and clinic-operational surfaces | `DES-004` |
| `#screen-dashboard` | Member dashboard hero, points chip, active-visit card, quick actions, recent-history table | `DES-005` |
| `.queue-active-box` | Member active visit state without a public ticket number | `DES-006` |
| `#screen-request` | New radiography-session form: site, service, date/shift, optional note/upload, points confirmation | `DES-007` |
| `#screen-history` | Searchable session history table and report actions | `DES-008` |
| `#screen-wallet` | Points packages, balance summary, and top-up actions | `DES-009` |
| `#screen-op-queue` | Private operator queue table, masked/public-data review pending product implementation | `DES-010` |
| `#screen-op-walkin` | Assisted walk-in registration and payment surface | `DES-011` |
| `#ai-modal` | Report/result modal, image preview, summary/formal separation, download/close actions | `DES-012` |
| `.portal-operator .topbar` | Operator workstation header, station selector, module tabs, connectivity, user chip | `DES-013` |
| `#screen-operator-stage-queue` | Concurrent basic-examination, X-ray, awaiting-AI, and result-monitor queue cards | `DES-014` |
| `#screen-operator-lcd` | Paired read-only public LCD with two destinations, current calls, five recent calls, stale/expiry state | `DES-015` |
| `#screen-operator-workstation .left-sidebar` | Operator queue statistics, search, filters, queue rows, selected state | `DES-016` |
| `#screen-operator-dicom` | Read-only DICOM viewer HUD, image viewport, overlays, thumbnails, zoom/invert/grid/reset controls | `DES-017` |
| `#screen-operator-generator` | Generator protocol controls, AEC selection, anatomical program, ready state, emergency stop | `DES-018` |
| `#operator-workstation-footer` | Ready status, reject/retake, complete-and-send action bar | `DES-019` |
| `#completeModal` | Submission confirmation modal with cancel/submit actions | `DES-020` |
| `[data-op-view]`, `switchRoute`, `switchOpModule` | Route/module state changes, active navigation, visible portal transitions | `DES-021` |
| `@media` inventory and overflow rules | Responsive behavior: no explicit media-query breakpoints; horizontal table/filter overflow and bounded panels require responsive verification | `DES-022` |

## Coverage findings and omissions

- No mandatory local specification is missing or unreadable.
- Local relative context links resolve to files present in this repository. The
  external standards, Stitch project, deployment-template repository, MPIPS
  repository, payment gateway, AI provider, notification providers, object
  storage, Grabber schema, and FHIR package were not fetched or contacted.
- The declared source commit cannot be compared locally; this is a provenance
  limitation, not evidence of a match.
- The design reference contains no explicit responsive `@media` rules. Its
  overflow declarations and fixed workstation columns are mapped to `DES-022`
  and require an implementation decision and rendered verification.
- The design sample exposes full-looking NIK values and uses member/public
  strings such as “X-Ray”, “PASIEN”, and “Pemeriksaan Baru”. The UI language
  policy governs production member/public copy; the prototype is not evidence
  that those strings are approved for production. See conflicts `C-01`–`C-04`.
- The design's `alert()` calls, `window.print()`, simulated radiograph SVG, and
  “PACS: CONNECTED” label are interaction/reference examples only. They do not
  prove durable storage, authorization, payment, queue, clinical, or external
  adapter behavior.
- No duplicate requirement was removed from the matrix. Repeated obligations
  across module specifications are represented once under the owning authority
  where ownership is explicit and cross-referenced in this index.
- No source authority explicitly supersedes the unresolved privacy, FHIR
  canonical-package, Grabber schema, payment-gateway, or doctor-report design
  decisions. They remain visible in the matrix and plan.

## Exact matrix reconciliation

The exact source-heading lookup below is authoritative for this generated baseline. Each matrix row carries a source path and heading locator. A heading with no matching row is explicitly identified rather than silently omitted.

### .agents/context/project.md

| Heading | Exact matrix IDs or specific rationale |
|---|---|
| Repository decision | `ARCH-001`, `ARCH-002`, `ARCH-003`, `ARCH-004`, `ARCH-005`, `ARCH-006`, `ARCH-007` |
| Why this boundary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Technology stack | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Target repository layout | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Runtime topology | `ARCH-008`, `ARCH-009`, `ARCH-010` |
| Module interaction rules | `ARCH-011`, `ARCH-012`, `ARCH-013`, `ARCH-014`, `ARCH-015`, `ARCH-016`, `ARCH-017`, `ARCH-018` |
| Image Gateway module boundary | `ARCH-019`, `ARCH-020`, `ARCH-021`, `ARCH-022`, `ARCH-023`, `ARCH-024`, `ARCH-025`, `ARCH-026`, `ARCH-027` |
| MPIPS black-box contract | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Conversion flow | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Constraints and hazards | `ARCH-028`, `ARCH-029` |
| Deployment | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Security boundary | `ARCH-030`, `ARCH-031`, `ARCH-032`, `ARCH-033`, `ARCH-034`, `ARCH-035`, `ARCH-036` |
| Extraction rule | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
### .agents/context/modules/member/project.md

| Heading | Exact matrix IDs or specific rationale |
|---|---|
| Agent rules | Specific non-normative/procedural section; obligations are covered by parent or acceptance rows. |
| MHCS Core topology | No extracted list-item row; prose-only module-boundary description remains an explicit manual audit item and is not implementation evidence. |
| Purpose and ownership | `MEM-001`, `MEM-002`, `MEM-003`, `MEM-004`, `MEM-005`, `MEM-006`, `MEM-007`, `MEM-008`, `MEM-009` |
| Users and admin panel | `MEM-010`, `MEM-011`, `MEM-012`, `MEM-013` |
| Identity model | `MEM-014`, `MEM-015`, `MEM-016`, `MEM-017`, `MEM-018`, `MEM-019` |
| Children and guardians | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| B2B-first commercial model | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Initial B2B provisioning | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Booking and points rules | `MEM-020`, `MEM-021`, `MEM-022`, `MEM-023`, `MEM-024`, `MEM-025`, `MEM-026`, `MEM-027`, `MEM-028`, `MEM-029`, `MEM-030`, `MEM-031` |
| B2C cancellation and postponement | `MEM-032`, `MEM-033`, `MEM-034`, `MEM-035`, `MEM-036`, `MEM-037` |
| Family participation | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Organization and examination-site rule | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Required data model | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Schema requirements | `MEM-038`, `MEM-039`, `MEM-040`, `MEM-041`, `MEM-042`, `MEM-043`, `MEM-044`, `MEM-045`, `MEM-046`, `MEM-047`, `MEM-048`, `MEM-049`, `MEM-050`, `MEM-051`, `MEM-052`, `MEM-053`, `MEM-054`, `MEM-055`, `MEM-056`, `MEM-057`, `MEM-058`, `MEM-059`, `MEM-060`, `MEM-061`, `MEM-062`, `MEM-063`, `MEM-064` |
| Account and member states | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Booking states | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Doctor-requested repeat entitlement contract | `MEM-065`, `MEM-066`, `MEM-067` |
| Operator attendance application contract | `MEM-068`, `MEM-069`, `MEM-070`, `MEM-071`, `MEM-072`, `MEM-073`, `MEM-074`, `MEM-075` |
| Operator-assisted walk-in application contract | `MEM-076`, `MEM-077`, `MEM-078`, `MEM-079`, `MEM-080`, `MEM-081`, `MEM-082`, `MEM-083` |
| Arrival identity verification | `MEM-084`, `MEM-085` |
| Examination consent record | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Operator cash-closing application contract | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Basic examination & vital signs assessment | `MEM-086`, `MEM-087`, `MEM-088`, `MEM-089`, `MEM-090`, `MEM-091`, `MEM-092`, `MEM-093`, `MEM-094`, `MEM-095`, `MEM-096` |
| Structured interview | `MEM-097`, `MEM-098`, `MEM-099`, `MEM-100`, `MEM-101` |
| Operator measurement operation | `MEM-102`, `MEM-103`, `MEM-104`, `MEM-105`, `MEM-106`, `MEM-107` |
| Security and privacy invariants | `MEM-108`, `MEM-109`, `MEM-110`, `MEM-111`, `MEM-112`, `MEM-113`, `MEM-114`, `MEM-115`, `MEM-116`, `MEM-117`, `MEM-118`, `MEM-119` |
| FHIR R5 boundary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Version and conformance policy | `MEM-120`, `MEM-121`, `MEM-122`, `MEM-123`, `MEM-124` |
| Required radiology chain | `MEM-125`, `MEM-126`, `MEM-127`, `MEM-128`, `MEM-129`, `MEM-130`, `MEM-131`, `MEM-132`, `MEM-133` |
| Ownership of FHIR mappings | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Mapping metadata | `MEM-134`, `MEM-135`, `MEM-136`, `MEM-137`, `MEM-138`, `MEM-139`, `MEM-140` |
| Terminology and units | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Conformance artifacts | `MEM-141`, `MEM-142`, `MEM-143`, `MEM-144`, `MEM-145`, `MEM-146` |
| Admin panel | `MEM-147`, `MEM-148`, `MEM-149`, `MEM-150`, `MEM-151`, `MEM-152`, `MEM-153`, `MEM-154`, `MEM-155`, `MEM-156`, `MEM-157`, `MEM-158` |
| Acceptance criteria | `MEM-159`, `MEM-160`, `MEM-161`, `MEM-162`, `MEM-163`, `MEM-164`, `MEM-165`, `MEM-166`, `MEM-167`, `MEM-168`, `MEM-169`, `MEM-170`, `MEM-171`, `MEM-172`, `MEM-173`, `MEM-174`, `MEM-175`, `MEM-176`, `MEM-177`, `MEM-178`, `MEM-179`, `MEM-180`, `MEM-181`, `MEM-182`, `MEM-183`, `MEM-184`, `MEM-185`, `MEM-186`, `MEM-187`, `MEM-188`, `MEM-189`, `MEM-190`, `MEM-191`, `MEM-192`, `MEM-193`, `MEM-194`, `MEM-195`, `MEM-196`, `MEM-197`, `MEM-198`, `MEM-199`, `MEM-200`, `MEM-201`, `MEM-202`, `MEM-203`, `MEM-204`, `MEM-205`, `MEM-206`, `MEM-207`, `MEM-208`, `MEM-209` |
| Open decisions | `MEM-210`, `MEM-211`, `MEM-212` |
| Standards references | Specific non-normative/procedural section; obligations are covered by parent or acceptance rows. |
### .agents/context/modules/operator/project.md

| Heading | Exact matrix IDs or specific rationale |
|---|---|
| MHCS Core topology | No extracted list-item row; prose-only module-boundary description remains an explicit manual audit item and is not implementation evidence. |
| Purpose | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Users and authorization | `OPR-001`, `OPR-002` |
| Site ownership and synchronization | `OPR-003`, `OPR-004`, `OPR-005` |
| Shift eligibility and operator assignment | `OPR-006`, `OPR-007`, `OPR-008`, `OPR-009`, `OPR-010`, `OPR-011`, `OPR-012`, `OPR-013`, `OPR-014` |
| Attendance and identity verification | `OPR-015`, `OPR-016`, `OPR-017`, `OPR-018`, `OPR-019`, `OPR-020` |
| Basic examination & vital signs assessment | `OPR-021`, `OPR-022`, `OPR-023`, `OPR-024`, `OPR-025` |
| Queue rules | `OPR-026`, `OPR-027`, `OPR-028`, `OPR-029`, `OPR-030` |
| Public LCD display | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Walk-in boundary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Examination protocol configuration | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| NPZ draft and submission flow | `OPR-031`, `OPR-032`, `OPR-033`, `OPR-034`, `OPR-035`, `OPR-036`, `OPR-037`, `OPR-038`, `OPR-039`, `OPR-040` |
| Submission reliability and completion | `OPR-041`, `OPR-042`, `OPR-043`, `OPR-044`, `OPR-045`, `OPR-046` |
| AI waiting and result status monitoring | `OPR-047`, `OPR-048`, `OPR-049`, `OPR-050` |
| Corrections and repeat examinations | `OPR-051`, `OPR-052`, `OPR-053`, `OPR-054`, `OPR-055`, `OPR-056` |
| Read-only image access | `OPR-057`, `OPR-058`, `OPR-059`, `OPR-060` |
| Operator earnings | `OPR-061`, `OPR-062`, `OPR-063`, `OPR-064`, `OPR-065` |
| Automated operator payouts | `OPR-066`, `OPR-067`, `OPR-068`, `OPR-069`, `OPR-070`, `OPR-071`, `OPR-072`, `OPR-073` |
| Cash closing | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Administrator capabilities | `OPR-074`, `OPR-075`, `OPR-076`, `OPR-077`, `OPR-078`, `OPR-079`, `OPR-080`, `OPR-081`, `OPR-082`, `OPR-083`, `OPR-084` |
| Application operations | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Member module contract | `OPR-085`, `OPR-086`, `OPR-087`, `OPR-088`, `OPR-089`, `OPR-090`, `OPR-091`, `OPR-092`, `OPR-093`, `OPR-094`, `OPR-095` |
| Image Gateway module contract | `OPR-096`, `OPR-097`, `OPR-098`, `OPR-099` |
| Earnings and payment event contracts | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| FHIR R5 boundary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Appointment and Encounter states | `OPR-100`, `OPR-101`, `OPR-102`, `OPR-103`, `OPR-104`, `OPR-105`, `OPR-106`, `OPR-107` |
| Clinical metadata | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Conformance | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Security and audit requirements | `OPR-108`, `OPR-109`, `OPR-110`, `OPR-111`, `OPR-112`, `OPR-113`, `OPR-114`, `OPR-115`, `OPR-116` |
| Does not own | `OPR-117`, `OPR-118`, `OPR-119`, `OPR-120`, `OPR-121`, `OPR-122`, `OPR-123`, `OPR-124` |
| External design inputs | `OPR-125`, `OPR-126`, `OPR-127`, `OPR-128` |
### .agents/context/modules/doctor/project.md

| Heading | Exact matrix IDs or specific rationale |
|---|---|
| MHCS Core topology | No extracted list-item row; prose-only module-boundary description remains an explicit manual audit item and is not implementation evidence. |
| Purpose | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Intended users and authorization | `DOC-001`, `DOC-002` |
| Target work queue | `DOC-003`, `DOC-004`, `DOC-005` |
| Study access | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Diagnostic-quality decision | `DOC-006`, `DOC-007` |
| Doctor-requested repeat lifecycle | `DOC-008`, `DOC-009`, `DOC-010`, `DOC-011`, `DOC-012`, `DOC-013`, `DOC-014`, `DOC-015`, `DOC-016`, `DOC-017`, `DOC-018`, `DOC-019` |
| Report lifecycle | `DOC-020`, `DOC-021`, `DOC-022`, `DOC-023`, `DOC-024`, `DOC-025`, `DOC-026`, `DOC-027`, `DOC-028`, `DOC-029`, `DOC-030`, `DOC-031`, `DOC-032`, `DOC-033` |
| Doctor earnings | `DOC-034`, `DOC-035`, `DOC-036`, `DOC-037`, `DOC-038`, `DOC-039`, `DOC-040` |
| Automated doctor payouts | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Information received | `DOC-041`, `DOC-042`, `DOC-043`, `DOC-044`, `DOC-045` |
| Information produced | `DOC-046`, `DOC-047`, `DOC-048`, `DOC-049`, `DOC-050`, `DOC-051`, `DOC-052` |
| Application and module operations | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| FHIR R5 boundary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Security and audit requirements | `DOC-053`, `DOC-054`, `DOC-055`, `DOC-056`, `DOC-057`, `DOC-058`, `DOC-059` |
| Does not own | `DOC-060`, `DOC-061`, `DOC-062`, `DOC-063`, `DOC-064`, `DOC-065`, `DOC-066` |
| Open design decisions | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
### .agents/context/modules/image-gateway/project.md

| Heading | Exact matrix IDs or specific rationale |
|---|---|
| MHCS Core topology | No extracted list-item row; prose-only module-boundary description remains an explicit manual audit item and is not implementation evidence. |
| Purpose | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Intended consumers | `IMG-001`, `IMG-002`, `IMG-003`, `IMG-004`, `IMG-005` |
| Submission boundary | `IMG-006`, `IMG-007`, `IMG-008`, `IMG-009`, `IMG-010`, `IMG-011` |
| Processing coordination | `IMG-012`, `IMG-013`, `IMG-014`, `IMG-015`, `IMG-016`, `IMG-017`, `IMG-018`, `IMG-019` |
| Private MPIPS adapter | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Completion rules | `IMG-020`, `IMG-021` |
| Permanent storage | `IMG-022`, `IMG-023`, `IMG-024`, `IMG-025`, `IMG-026`, `IMG-027` |
| Access and distribution | `IMG-028`, `IMG-029`, `IMG-030`, `IMG-031`, `IMG-032`, `IMG-033` |
| AI and doctor routing | `IMG-034`, `IMG-035`, `IMG-036`, `IMG-037`, `IMG-038`, `IMG-039`, `IMG-040`, `IMG-041`, `IMG-042`, `IMG-043`, `IMG-044`, `IMG-045`, `IMG-046` |
| Doctor replacement-study contract | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| FHIR R5 boundary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Does not own | `IMG-047`, `IMG-048`, `IMG-049`, `IMG-050`, `IMG-051`, `IMG-052`, `IMG-053`, `IMG-054`, `IMG-055` |
| Open design decisions | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
### .agents/context/ui-language.md

| Heading | Exact matrix IDs or specific rationale |
|---|---|
| Purpose | `UIL-001`, `UIL-002`, `UIL-003`, `UIL-004`, `UIL-005`, `UIL-006`, `UIL-007`, `UIL-008`, `UIL-009`, `UIL-010`, `UIL-011`, `UIL-012` |
| Language requirement | `UIL-013`, `UIL-014`, `UIL-015`, `UIL-016`, `UIL-017`, `UIL-018`, `UIL-019`, `UIL-020`, `UIL-021`, `UIL-022`, `UIL-023`, `UIL-024`, `UIL-025`, `UIL-026`, `UIL-027`, `UIL-028`, `UIL-029`, `UIL-030`, `UIL-031` |
| Product language position | `UIL-032`, `UIL-033`, `UIL-034`, `UIL-035`, `UIL-036`, `UIL-037`, `UIL-038`, `UIL-039`, `UIL-040` |
| Default audience terminology | `UIL-041`, `UIL-042`, `UIL-043`, `UIL-044`, `UIL-045`, `UIL-046`, `UIL-047`, `UIL-048`, `UIL-049`, `UIL-050`, `UIL-051`, `UIL-052`, `UIL-053`, `UIL-054` |
| Primary service terminology | `UIL-055`, `UIL-056`, `UIL-057`, `UIL-058`, `UIL-059`, `UIL-060`, `UIL-061`, `UIL-062`, `UIL-063`, `UIL-064`, `UIL-065`, `UIL-066`, `UIL-067` |
| Radiography terminology model | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Sesi Foto Radiografi | `UIL-068`, `UIL-069`, `UIL-070` |
| Foto Radiografi | `UIL-071`, `UIL-072`, `UIL-073` |
| Gambar Radiografi | `UIL-074`, `UIL-075`, `UIL-076` |
| Use of the term “X-ray” | `UIL-077`, `UIL-078`, `UIL-079`, `UIL-080`, `UIL-081`, `UIL-082`, `UIL-083`, `UIL-084`, `UIL-085`, `UIL-086`, `UIL-087`, `UIL-088`, `UIL-089`, `UIL-090`, `UIL-091`, `UIL-092`, `UIL-093`, `UIL-094`, `UIL-095`, `UIL-096`, `UIL-097`, `UIL-098`, `UIL-099`, `UIL-100`, `UIL-101`, `UIL-102`, `UIL-103`, `UIL-104`, `UIL-105`, `UIL-106`, `UIL-107`, `UIL-108`, `UIL-109`, `UIL-110`, `UIL-111` |
| Examination terminology | `UIL-112`, `UIL-113`, `UIL-114`, `UIL-115`, `UIL-116`, `UIL-117` |
| Public queue terminology | `UIL-118`, `UIL-119`, `UIL-120`, `UIL-121`, `UIL-122`, `UIL-123`, `UIL-124`, `UIL-125`, `UIL-126`, `UIL-127`, `UIL-128`, `UIL-129`, `UIL-130`, `UIL-131`, `UIL-132` |
| Member journey terminology | `UIL-133`, `UIL-134`, `UIL-135`, `UIL-136`, `UIL-137`, `UIL-138`, `UIL-139` |
| Approved terminology map | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Internal and member-facing terminology boundary | `UIL-140`, `UIL-141`, `UIL-142`, `UIL-143`, `UIL-144`, `UIL-145`, `UIL-146`, `UIL-147`, `UIL-148`, `UIL-149`, `UIL-150` |
| Tone principles | `UIL-151`, `UIL-152` |
| Calm | `UIL-153`, `UIL-154`, `UIL-155`, `UIL-156`, `UIL-157` |
| Preventive | `UIL-158`, `UIL-159`, `UIL-160` |
| Professional | `UIL-161`, `UIL-162`, `UIL-163`, `UIL-164` |
| Human | `UIL-165`, `UIL-166`, `UIL-167`, `UIL-168` |
| Precise | `UIL-169`, `UIL-170`, `UIL-171`, `UIL-172`, `UIL-173`, `UIL-174` |
| Non-judgmental | `UIL-175`, `UIL-176`, `UIL-177`, `UIL-178`, `UIL-179`, `UIL-180`, `UIL-181`, `UIL-182`, `UIL-183`, `UIL-184` |
| Safety and transparency | `UIL-185`, `UIL-186`, `UIL-187`, `UIL-188`, `UIL-189`, `UIL-190`, `UIL-191`, `UIL-192`, `UIL-193`, `UIL-194`, `UIL-195`, `UIL-196` |
| Consent language | `UIL-197`, `UIL-198`, `UIL-199`, `UIL-200`, `UIL-201`, `UIL-202`, `UIL-203`, `UIL-204`, `UIL-205`, `UIL-206`, `UIL-207` |
| Health-claim boundaries | `UIL-208`, `UIL-209`, `UIL-210`, `UIL-211`, `UIL-212`, `UIL-213`, `UIL-214`, `UIL-215`, `UIL-216`, `UIL-217`, `UIL-218`, `UIL-219`, `UIL-220`, `UIL-221`, `UIL-222`, `UIL-223`, `UIL-224`, `UIL-225`, `UIL-226` |
| Formal report and member summary | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Ringkasan untuk Anda | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Laporan Dokter | `UIL-227`, `UIL-228`, `UIL-229`, `UIL-230`, `UIL-231`, `UIL-232`, `UIL-233`, `UIL-234`, `UIL-235` |
| Status terminology | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Scheduling | `UIL-236`, `UIL-237`, `UIL-238`, `UIL-239`, `UIL-240`, `UIL-241` |
| Preparation | `UIL-242`, `UIL-243`, `UIL-244`, `UIL-245` |
| Basic examination | `UIL-246`, `UIL-247`, `UIL-248`, `UIL-249` |
| Radiography session | `UIL-250`, `UIL-251`, `UIL-252`, `UIL-253`, `UIL-254` |
| Professional review | `UIL-255`, `UIL-256`, `UIL-257`, `UIL-258`, `UIL-259`, `UIL-260`, `UIL-261` |
| Follow-up | `UIL-262`, `UIL-263`, `UIL-264`, `UIL-265`, `UIL-266` |
| Recommended and required actions | `UIL-267`, `UIL-268`, `UIL-269`, `UIL-270`, `UIL-271`, `UIL-272`, `UIL-273`, `UIL-274` |
| Repeat-session language | `UIL-275`, `UIL-276`, `UIL-277`, `UIL-278`, `UIL-279`, `UIL-280`, `UIL-281`, `UIL-282`, `UIL-283`, `UIL-284` |
| Navigation conventions | `UIL-285`, `UIL-286`, `UIL-287`, `UIL-288`, `UIL-289`, `UIL-290`, `UIL-291`, `UIL-292` |
| Button conventions | `UIL-293`, `UIL-294`, `UIL-295`, `UIL-296`, `UIL-297`, `UIL-298`, `UIL-299`, `UIL-300`, `UIL-301`, `UIL-302`, `UIL-303`, `UIL-304`, `UIL-305`, `UIL-306`, `UIL-307`, `UIL-308`, `UIL-309` |
| Page-title conventions | `UIL-310`, `UIL-311`, `UIL-312`, `UIL-313`, `UIL-314`, `UIL-315`, `UIL-316`, `UIL-317`, `UIL-318`, `UIL-319`, `UIL-320`, `UIL-321`, `UIL-322`, `UIL-323`, `UIL-324`, `UIL-325` |
| Empty-state conventions | `UIL-326`, `UIL-327`, `UIL-328` |
| No session | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| No result yet | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| No upcoming schedule | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| No doctor report | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Notification conventions | `UIL-329`, `UIL-330`, `UIL-331`, `UIL-332`, `UIL-333`, `UIL-334`, `UIL-335`, `UIL-336`, `UIL-337`, `UIL-338` |
| Error-message conventions | `UIL-339`, `UIL-340`, `UIL-341`, `UIL-342`, `UIL-343`, `UIL-344`, `UIL-345`, `UIL-346` |
| Confirmation-message conventions | `UIL-347`, `UIL-348`, `UIL-349`, `UIL-350`, `UIL-351` |
| Date and time conventions | `UIL-352`, `UIL-353`, `UIL-354`, `UIL-355` |
| Capitalization and punctuation | `UIL-356`, `UIL-357`, `UIL-358`, `UIL-359`, `UIL-360`, `UIL-361`, `UIL-362`, `UIL-363`, `UIL-364`, `UIL-365`, `UIL-366`, `UIL-367`, `UIL-368`, `UIL-369`, `UIL-370`, `UIL-371`, `UIL-372` |
| Accessibility and comprehension | `UIL-373`, `UIL-374`, `UIL-375`, `UIL-376`, `UIL-377`, `UIL-378`, `UIL-379`, `UIL-380`, `UIL-381`, `UIL-382`, `UIL-383`, `UIL-384` |
| Doctor-facing and operator-facing boundary | `UIL-385`, `UIL-386`, `UIL-387`, `UIL-388`, `UIL-389`, `UIL-390`, `UIL-391` |
| Artificial intelligence and automation language | `UIL-392`, `UIL-393`, `UIL-394`, `UIL-395`, `UIL-396`, `UIL-397`, `UIL-398`, `UIL-399`, `UIL-400`, `UIL-401`, `UIL-402`, `UIL-403`, `UIL-404`, `UIL-405`, `UIL-406` |
| Marketing-language boundary | `UIL-407`, `UIL-408`, `UIL-409`, `UIL-410`, `UIL-411`, `UIL-412`, `UIL-413`, `UIL-414`, `UIL-415`, `UIL-416`, `UIL-417`, `UIL-418`, `UIL-419`, `UIL-420`, `UIL-421` |
| Examples | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Dashboard introduction | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| New-session card | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Queue display | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Image processing | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Result available | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| No immediate concern | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Follow-up | `UIL-262`, `UIL-263`, `UIL-264`, `UIL-265`, `UIL-266` |
| Repeat session | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| Safety explanation | No extracted list-item row; prose-only section remains an explicit manual audit item and is not implementation evidence. |
| UI-copy review procedure | `UIL-422`, `UIL-423`, `UIL-424`, `UIL-425`, `UIL-426`, `UIL-427`, `UIL-428`, `UIL-429`, `UIL-430`, `UIL-431`, `UIL-432`, `UIL-433`, `UIL-434`, `UIL-435`, `UIL-436`, `UIL-437`, `UIL-438`, `UIL-439`, `UIL-440` |
| Repository search guidance | Specific non-normative/procedural section; obligations are covered by parent or acceptance rows. |
| Acceptance checklist | Specific non-normative/procedural section; obligations are covered by parent or acceptance rows. |
| Authority and conflict resolution | `UIL-441`, `UIL-442`, `UIL-443`, `UIL-444`, `UIL-445`, `UIL-446`, `UIL-447`, `UIL-448`, `UIL-449`, `UIL-450`, `UIL-451`, `UIL-452`, `UIL-453`, `UIL-454`, `UIL-455`, `UIL-456`, `UIL-457`, `UIL-458`, `UIL-459`, `UIL-460`, `UIL-461`, `UIL-462`, `UIL-463` |

### Design item reconciliation

| Design item | Identifier |
|---|---|
| Approved HTML design locator `001` | `DES-001` |
| Approved HTML design locator `002` | `DES-002` |
| Approved HTML design locator `003` | `DES-003` |
| Approved HTML design locator `004` | `DES-004` |
| Approved HTML design locator `005` | `DES-005` |
| Approved HTML design locator `006` | `DES-006` |
| Approved HTML design locator `007` | `DES-007` |
| Approved HTML design locator `008` | `DES-008` |
| Approved HTML design locator `009` | `DES-009` |
| Approved HTML design locator `010` | `DES-010` |
| Approved HTML design locator `011` | `DES-011` |
| Approved HTML design locator `012` | `DES-012` |
| Approved HTML design locator `013` | `DES-013` |
| Approved HTML design locator `014` | `DES-014` |
| Approved HTML design locator `015` | `DES-015` |
| Approved HTML design locator `016` | `DES-016` |
| Approved HTML design locator `017` | `DES-017` |
| Approved HTML design locator `018` | `DES-018` |
| Approved HTML design locator `019` | `DES-019` |
| Approved HTML design locator `020` | `DES-020` |
| Approved HTML design locator `021` | `DES-021` |
| Approved HTML design locator `022` | `DES-022` |

### Exact reconciliation findings

- Matrix identifiers are unique across 982 rows: `ARCH` 36, `MEM` 212, `OPR` 128, `DOC` 66, `IMG` 55, `UIL` 463, `DES` 22.
- All rows are `applicable` and `not-started`; no product implementation or executable verification evidence exists.
- The declared source SHA is unavailable for direct comparison, so source correspondence remains `unverified`.

## Reconciliation contract

The matrix contains 982 unique extracted requirement identifiers: `ARCH-*` 36, `MEM-*` 212, `OPR-*` 128, `DOC-*` 66, `IMG-*` 55, `UIL-*` 463, and `DES-*` 22.
Every applicable identifier is assigned to exactly one primary work package in
the implementation plan. The plan's final audit package must re-read this
coverage index and fail while any applicable requirement is not `verified`.
