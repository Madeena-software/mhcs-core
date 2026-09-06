#!/usr/bin/env python3
"""
Deterministic derived Indonesian MHCS PDF generator.

Conforms strictly to the approved design reference in 05_final_indonesia_v10.
Does not translate, alter, or interpret clinical text; verbatim preservation only.

Reads JSON from stdin:
{
    "originalPdfPath": "/path/to/original.pdf",
    "destinationPath": "/path/to/derived.pdf",
    "logoPath": "resources/images/branding/rumah-skrining-logo.png",
    "facilityName": "Rumah Skrining CV Prestige",
    "organizationSubtitle": "oleh PT Madeena",
    "facilityAddressLine1": "Jl. Lowanu No.68-72, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta,",
    "facilityAddressLine2": "Daerah Istimewa Yogyakarta 55162",
    "patientName": "Purnomo",
    "patientDobAge": "15 Januari 1981 (45 tahun)",
    "patientGender": "Laki-laki",
    "patientMrn": "MRN-1787808860329",
    "examinationDate": "27 Agustus 2026",
    "examinationArea": "Toraks",
    "findings": "Toraks simetris, mediastinum di garis tengah...",
    "impression": "Tidak tampak kelainan pada foto polos toraks.",
    "radiographerName": "Ratih Hanjar Dewanti, A.Md.Rad.",
    "aiReviewer": "Madeena Intelligence (AI)",
    "reportDate": "1 September 2026",
    "disclaimerText": "Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)",
    "footerNote": "Laporan ini hanya sebagai acuan klinis."
}

Emits JSON to stdout:
{
    "success": true|false,
    "errorCode": null|"SAFE_ERROR_CODE",
    "errorMessage": null|"Sanitized message",
    "pdfPath": "/path/to/derived.pdf",
    "sha256": "...",
    "byteSize": 12345
}
"""

import hashlib
import json
import os
import sys
import tempfile
from typing import Optional, List, Tuple

from PIL import Image
from pypdf import PdfReader
from reportlab.lib.colors import Color
from reportlab.pdfgen import canvas


def sanitize_message(msg: str, sensitive_strings: list) -> str:
    sanitized = str(msg)
    for s in sensitive_strings:
        if s and len(s) > 2:
            sanitized = sanitized.replace(s, "[REDACTED]")
    return sanitized


def validate_pdf_file(path: str) -> None:
    if not os.path.isfile(path):
        raise ValueError(f"Original PDF file does not exist: {path}")
    
    size = os.path.getsize(path)
    if size < 50:
        raise ValueError(f"Original PDF file is suspiciously small ({size} bytes).")
    
    with open(path, "rb") as f:
        head = f.read(1024)
        f.seek(max(0, size - 4096))
        tail = f.read(4096)
        
    if not head.startswith(b"%PDF-"):
        raise ValueError("Original file missing %PDF- magic bytes header.")
    if b"%%EOF" not in tail:
        raise ValueError("Original file missing %%EOF trailer marker.")


def extract_radiograph_image(reader: PdfReader, temp_dir: str) -> Optional[str]:
    """
    Extracts the chest radiograph visual from the original PDF if present.
    """
    try:
        page = reader.pages[0]
        images = list(page.images)
        if not images:
            return None
        
        # If there are multiple images, pick the largest one (radiograph)
        if len(images) > 1:
            best_img = max(images, key=lambda img: len(img.data))
            extracted_path = os.path.join(temp_dir, f"radiograph_{best_img.name}")
            with open(extracted_path, "wb") as f:
                f.write(best_img.data)
            return extracted_path
        
        # Single image present
        img = images[0]
        extracted_path = os.path.join(temp_dir, f"vendor_img_{img.name}")
        with open(extracted_path, "wb") as f:
            f.write(img.data)
        
        # Check image dimensions
        with Image.open(extracted_path) as pil_im:
            w, h = pil_im.size
            # If it's a full-page composite (e.g. 794x1120), crop the radiograph area
            if w >= 700 and h >= 1000 and 1.3 <= (h / w) <= 1.6:
                # Radiograph is located roughly in middle vertically (y: 200..700, x: 100..694)
                crop_box = (int(w * 0.12), int(h * 0.18), int(w * 0.88), int(h * 0.63))
                cropped = pil_im.crop(crop_box)
                cropped_path = os.path.join(temp_dir, "cropped_radiograph.png")
                cropped.save(cropped_path, "PNG")
                return cropped_path
        
        return extracted_path
    except Exception:
        return None


def extract_verbatim_text_from_pdf(reader: PdfReader) -> Tuple[str, str]:
    """
    Extracts verbatim findings and impression text from original PDF if available.
    """
    findings = ""
    impression = ""
    try:
        full_text = reader.pages[0].extract_text() or ""
        lines = [line.strip() for line in full_text.splitlines() if line.strip()]
        
        # Check for headings in Indonesian or English
        in_findings = False
        in_impression = False
        findings_lines = []
        impression_lines = []
        
        for line in lines:
            line_lower = line.lower()
            if "temuan radiologis" in line_lower or "findings" in line_lower:
                in_findings = True
                in_impression = False
                continue
            elif "kesan" in line_lower or "impression" in line_lower or "conclusion" in line_lower:
                in_findings = False
                in_impression = True
                continue
            elif "radiografer" in line_lower or "penelaah" in line_lower or "laporan ini" in line_lower:
                in_findings = False
                in_impression = False
                continue
            
            if in_findings:
                findings_lines.append(line)
            elif in_impression:
                impression_lines.append(line)
                
        if findings_lines:
            findings = " ".join(findings_lines)
        if impression_lines:
            impression = " ".join(impression_lines)
    except Exception:
        pass
    
    return findings, impression


def render_derived_pdf(output_path: str, data: dict, radiograph_path: Optional[str]) -> None:
    # Page size A4: 595.2756 x 841.8898 pt (from 05_final_indonesia_v10 evidence)
    c = canvas.Canvas(output_path, pagesize=(595.2756, 841.8898))
    c.setTitle("Laporan Pemeriksaan Radiografi Digital (DR) Toraks")
    c.setAuthor(data.get("facilityName", "Rumah Skrining"))
    c.setSubject("Laporan Hasil Analisis Kecerdasan Buatan")
    
    # Exact colors from 05_final_indonesia_v10 stream inspection:
    c_primary = Color(0.192157, 0.337255, 0.431373)      # #31566e Primary Navy
    c_dark = Color(0.090196, 0.141176, 0.203922)         # #172434 Text Dark
    c_muted = Color(0.27451, 0.368627, 0.427451)         # #465e6d Label Slate
    c_border = Color(0.721569, 0.784314, 0.819608)       # #b8c8d1 Box Border
    c_banner_bg = Color(0.909804, 0.941176, 0.956863)     # #e8f0f4 Section Banner Fill
    c_sig_line = Color(0.768627, 0.815686, 0.843137)      # #c4d0d7 Signature Line
    c_footer_gray = Color(0.388235, 0.458824, 0.509804)   # #637582 Footer Text
    c_disclaimer_red = Color(0.729412, 0.109804, 0.109804) # #ba1c1c Notice Red
    
    # 1. Header Logo
    logo_path = data.get("logoPath")
    if logo_path and os.path.isfile(logo_path):
        c.drawImage(logo_path, 63.57, 725.67, width=103.88, height=89.29, mask='auto', preserveAspectRatio=True)
        
    # 2. Header Facility Title
    facility_name = data.get("facilityName", "Rumah Skrining CV Prestige")
    c.setFillColor(c_primary)
    c.setFont("Helvetica-Bold", 18)
    c.drawString(208.35, 800.22, facility_name)
    
    # Header Badge ("oleh PT Madeena")
    org_sub = data.get("organizationSubtitle", "oleh PT Madeena")
    c.setFillColor(c_primary)
    c.roundRect(215.72, 778.68, 63.5, 15.3, radius=4, fill=1, stroke=0)
    c.setFillColor(Color(1, 1, 1))
    c.setFont("Helvetica-Bold", 7)
    c.drawString(218.0, 783.35, org_sub)
    
    # Header Address
    c.setFillColor(c_dark)
    c.setFont("Helvetica", 7.9)
    addr1 = data.get("facilityAddressLine1", "Jl. Lowanu No.68-72, Sorosutan, Kec. Umbulharjo, Kota Yogyakarta,")
    addr2 = data.get("facilityAddressLine2", "Daerah Istimewa Yogyakarta 55162")
    c.drawString(208.35, 763.94, addr1)
    c.drawString(208.35, 752.60, addr2)
    
    # Header Divider Line (42.52 to 552.76, width 1.1)
    c.setStrokeColor(c_primary)
    c.setLineWidth(1.1)
    c.line(42.52, 722.27, 552.76, 722.27)
    
    # 3. Document Title
    c.setFillColor(c_dark)
    c.setFont("Helvetica-Bold", 14.8)
    c.drawString(72.77, 699.59, "LAPORAN PEMERIKSAAN RADIOGRAFI DIGITAL (DR) TORAKS")
    
    # 4. Patient Demographics Box (Rounded rect 42.52, 597.54, 510.24, 80.79)
    c.setStrokeColor(c_border)
    c.setLineWidth(0.95)
    c.roundRect(42.52, 597.54, 510.24, 80.79, radius=5, fill=0, stroke=1)
    
    fields = [
        (54.43, 659.62, 648.28, "NAMA", data.get("patientName", "-")),
        (212.88, 659.62, 648.28, "TANGGAL LAHIR / USIA", data.get("patientDobAge", "-")),
        (379.28, 659.62, 648.28, "JENIS KELAMIN", data.get("patientGender", "-")),
        (54.43, 623.91, 612.57, "ID PASIEN / MRN", data.get("patientMrn", "-")),
        (212.88, 623.91, 612.57, "TANGGAL PEMERIKSAAN", data.get("examinationDate", "-")),
        (379.28, 623.91, 612.57, "AREA PEMERIKSAAN", data.get("examinationArea", "Toraks")),
    ]
    for x, ly, vy, label, val in fields:
        c.setFillColor(c_muted)
        c.setFont("Helvetica-Bold", 6.9)
        c.drawString(x, ly, label)
        c.setFillColor(c_dark)
        c.setFont("Helvetica", 8.7)
        c.drawString(x, vy, str(val))
        
    # 5. Section 1: TEMUAN RADIOLOGIS Banner
    c.setFillColor(c_banner_bg)
    c.roundRect(42.52, 568.06, 510.24, 22.11, radius=4, fill=1, stroke=0)
    c.setFillColor(c_primary)
    c.setFont("Helvetica-Bold", 10.7)
    c.drawString(54.14, 574.30, "TEMUAN RADIOLOGIS")
    
    # Chest Radiograph Frame (x=193.89, y=278.89, w=207.50, h=280.96)
    c.setStrokeColor(c_border)
    c.setLineWidth(0.65)
    c.rect(193.89, 278.89, 207.50, 280.96, fill=0, stroke=1)
    
    if radiograph_path and os.path.isfile(radiograph_path):
        try:
            c.drawImage(radiograph_path, 197.01, 282.00, width=201.26, height=274.72, preserveAspectRatio=True)
        except Exception:
            pass
            
    # Findings verbatim text
    findings_raw = data.get("findings", "")
    c.setFillColor(c_dark)
    c.setFont("Helvetica", 9.5)
    
    # Word wrap into lines if necessary (max width 510 pt)
    y_find = 258.0
    if findings_raw:
        # Wrap preserving words verbatim
        words = findings_raw.split()
        curr_line = []
        for word in words:
            test_line = " ".join(curr_line + [word])
            if c.stringWidth(test_line, "Helvetica", 9.5) <= 510.0:
                curr_line.append(word)
            else:
                c.drawString(42.52, y_find, " ".join(curr_line))
                y_find -= 12.0
                curr_line = [word]
        if curr_line:
            c.drawString(42.52, y_find, " ".join(curr_line))
            y_find -= 12.0
            
    # 6. Section 2: KESAN Banner
    c.setFillColor(c_banner_bg)
    c.roundRect(42.52, 194.32, 510.24, 22.11, radius=4, fill=1, stroke=0)
    c.setFillColor(c_primary)
    c.setFont("Helvetica-Bold", 10.7)
    c.drawString(54.14, 200.56, "KESAN")
    
    # Impression verbatim text
    impression_raw = data.get("impression", "")
    c.setFillColor(c_dark)
    c.setFont("Helvetica-Bold", 9.5)
    y_imp = 169.5
    if impression_raw:
        words = impression_raw.split()
        curr_line = []
        for word in words:
            test_line = " ".join(curr_line + [word])
            if c.stringWidth(test_line, "Helvetica-Bold", 9.5) <= 510.0:
                curr_line.append(word)
            else:
                c.drawString(42.52, y_imp, " ".join(curr_line))
                y_imp -= 12.0
                curr_line = [word]
        if curr_line:
            c.drawString(42.52, y_imp, " ".join(curr_line))
            
    # 7. Attribution & Signatures Block
    c.setFillColor(c_muted)
    c.setFont("Helvetica-Bold", 7.3)
    c.drawString(42.52, 86.46, "RADIOGRAFER")
    c.drawString(212.60, 86.46, "PENELAAH AI")
    c.drawString(382.68, 86.46, "TANGGAL LAPORAN")
    
    c.setFillColor(c_dark)
    c.setFont("Helvetica", 7.9)
    c.drawString(42.52, 70.87, data.get("radiographerName", "Ratih Hanjar Dewanti, A.Md.Rad."))
    c.drawString(212.60, 70.87, data.get("aiReviewer", "Madeena Intelligence (AI)"))
    c.drawString(382.68, 70.87, data.get("reportDate", "-"))
    
    c.setStrokeColor(c_sig_line)
    c.setLineWidth(0.7)
    c.line(42.52, 54.71, 184.25, 54.71)
    c.line(212.60, 54.71, 354.33, 54.71)
    c.line(382.68, 54.71, 524.41, 54.71)
    
    # 8. Prominent AI Disclaimer (Non-clinical label, not a diagnosis)
    disclaimer = data.get(
        "disclaimerText",
        "Laporan Hasil Analisis Kecerdasan Buatan (Bukan Pengganti Diagnosis Dokter)",
    )
    c.setFillColor(c_disclaimer_red)
    c.setFont("Helvetica-Bold", 7.5)
    c.drawCentredString(297.64, 40.0, disclaimer)
    
    # 9. Footers
    footer_note = data.get("footerNote", "Laporan ini hanya sebagai acuan klinis.")
    c.setFillColor(c_footer_gray)
    c.setFont("Helvetica-Oblique", 6.8)
    c.drawString(42.52, 26.36, footer_note)
    
    c.setFillColor(c_primary)
    c.setFont("Helvetica-Bold", 6.8)
    c.drawRightString(552.76, 26.36, facility_name)
    
    c.showPage()
    c.save()


def run_generator() -> None:
    temp_dir = tempfile.mkdtemp(prefix="derived_pdf_")
    try:
        raw_input = sys.stdin.read()
        if not raw_input.strip():
            print(json.dumps({
                "success": False,
                "errorCode": "invalid_worker_input",
                "errorMessage": "Worker received empty input on stdin.",
                "pdfPath": None,
                "sha256": None,
                "byteSize": 0,
            }))
            sys.exit(1)
            
        data = json.loads(raw_input)
        
        orig_pdf = data.get("originalPdfPath", "")
        dest_pdf = data.get("destinationPath", "")
        
        if not orig_pdf or not dest_pdf:
            print(json.dumps({
                "success": False,
                "errorCode": "invalid_worker_input",
                "errorMessage": "Missing originalPdfPath or destinationPath.",
                "pdfPath": None,
                "sha256": None,
                "byteSize": 0,
            }))
            sys.exit(1)
            
        # Validate original PDF integrity
        try:
            validate_pdf_file(orig_pdf)
            reader = PdfReader(orig_pdf)
            if len(reader.pages) < 1:
                raise ValueError("Original PDF contains zero pages.")
        except Exception as e:
            print(json.dumps({
                "success": False,
                "errorCode": "ai_pacs_invalid_report",
                "errorMessage": f"Original PDF is invalid or malformed: {str(e)}",
                "pdfPath": None,
                "sha256": None,
                "byteSize": 0,
            }))
            sys.exit(0)
            
        # Extract source visual
        radiograph_path = extract_radiograph_image(reader, temp_dir)
        
        # If findings/impression not passed, try extracting verbatim from PDF text
        pdf_findings, pdf_impression = extract_verbatim_text_from_pdf(reader)
        if not data.get("findings") and pdf_findings:
            data["findings"] = pdf_findings
        if not data.get("impression") and pdf_impression:
            data["impression"] = pdf_impression
            
        # Ensure destination directory exists
        dest_dir = os.path.dirname(os.path.abspath(dest_pdf))
        os.makedirs(dest_dir, exist_ok=True)
        
        # Render derived PDF
        render_derived_pdf(dest_pdf, data, radiograph_path)
        
        # Verify rendered PDF
        validate_pdf_file(dest_pdf)
        byte_size = os.path.getsize(dest_pdf)
        with open(dest_pdf, "rb") as f:
            pdf_sha256 = hashlib.sha256(f.read()).hexdigest()
            
        print(json.dumps({
            "success": True,
            "errorCode": None,
            "errorMessage": None,
            "pdfPath": dest_pdf,
            "sha256": pdf_sha256,
            "byteSize": byte_size,
        }))
        
    except Exception as e:
        print(json.dumps({
            "success": False,
            "errorCode": "PROCESSING_ERROR",
            "errorMessage": f"Derived PDF generation failed: {str(e)}",
            "pdfPath": None,
            "sha256": None,
            "byteSize": 0,
        }))
        sys.exit(1)
    finally:
        import shutil
        shutil.rmtree(temp_dir, ignore_errors=True)


if __name__ == "__main__":
    run_generator()
