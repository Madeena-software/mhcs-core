# Physical Thermal Printer Validation Protocol: 57×47P Queue Slips

## 1. Context and Consumable Specification
- **System:** `Madeena-software/mhcs-core` Operator Portal Queue Ticket Thermal Print
- **Target Consumable:** 57×47P thermal paper roll:
  - Nominal media width: **57 mm**
  - Roll outer diameter: **47 mm**
  - Dynamic length: continuous roll feed, cut or torn to content length
  - Usable/safe printable width: **~48 mm** (with 4.5 mm safe side margins)
- **Styling Enforcement:** CSS `@page { size: 57mm auto; margin: 0; }` suppresses browser headers, footers, URL, and page numbering.

## 2. Hardware Dependency and Pre-deployment Prerequisites
Physical validation **must** be executed on the actual target operational printer model and driver stack before deploying field operational printing to staging or production.

### Operational Hardware Checklist:
1. Operational thermal printer powered and connected via USB, Ethernet, or serial to the operator terminal or DDR workstation.
2. Verified roll of 57×47P thermal paper loaded with the heat-sensitive side facing the printhead.
3. Host OS printer driver / CUPS queue configured with page width 57 mm or roll mode enabled.
4. Client web browser (Chromium / Google Chrome / Firefox / Safari) configured to print background graphics (`print-color-adjust: exact`).

## 3. Physical Validation Step-by-Step Procedure

### Step 1: Open Operator Ticket Print View
- In the Operator Portal, issue or reprint a queue ticket (e.g., via `/operator/paper-tickets/{id}/print`).
- Observe the browser print dialog window.

### Step 2: Configure Web Print Dialog
- **Destination:** Select the physical 57mm thermal printer.
- **Paper Size:** Select `57mm Roll`, `58mm (48mm printable)`, or custom size `57 mm × auto`.
- **Margins:** Set to `None` (ensures CSS `@page { margin: 0; }` is respected).
- **Headers and Footers:** Ensure unchecked.
- **Scale:** Set to `100%` / `Default` (do not scale to fit A4).

### Step 3: Trigger Physical Print
- Click **Print** to produce the physical ticket slip.

### Step 4: Physical Printout Inspection Criteria

| Inspection Item | Acceptance Standard | Pass / Fail Criteria |
|---|---|---|
| **Safe Printable Width** | Content fits within the ~48 mm central area. | **PASS** if no characters or borders are horizontally cropped or truncated at the left or right paper edge. |
| **Dynamic Height & Feed** | The paper feeds only the length of the ticket content + tear margin. | **PASS** if printer does not feed an entire 297 mm (A4) blank sheet. |
| **Operational Fields** | Site name, shift/schedule window, prominent queue ticket number, and issue timestamp are clearly legible. | **PASS** if ticket number is prominently readable at an arm's distance. |
| **Privacy Preservation** | Strict exclusion of sensitive patient data. | **CRITICAL PASS** only if NIK, phone number, DOB, MRN, consent signatures, and clinical notes are **completely absent**. |
| **Auto-Cut vs Manual-Tear** | - **Printers with Auto-Cutter:** Ticket cuts automatically after the tear margin.<br>- **Printers without Auto-Cutter:** Content ends before the manual-tear margin (`-- TEAR HERE --`). | **PASS** if manual tearing across the serrated edge does not clip, tear, or damage any ticket numbers, site name, or issue timestamp. |

## 4. Troubleshooting and Edge Cases
- **Horizontal Clipping:** If the leftmost or rightmost 1–2 mm is clipped, adjust printer driver printable area from 58mm to 57mm, or increase container padding from `4.5mm` to `5.5mm`.
- **Spurious Extra Feed:** If the printer feeds excess paper after printing, ensure the printer driver paper size is set to `Receipt` / `Continuous` rather than a fixed page format.
- **Inverted / Blank Output:** If paper feeds but remains completely white, check that the thermal roll is inserted in the correct roll orientation.
