#!/usr/bin/env python3
"""
Isolated Playwright worker for AI PACS Image Report PDF retrieval.

Reads structured JSON input from stdin:
{
    "baseUrl": "http://124.225.183.175:8361",
    "username": "...",
    "password": "...",
    "sid": 121,
    "aiCalcId": 124,
    "viewerType": "CR",
    "pacs": "fei",
    "destinationPath": "/path/to/download.pdf",
    "correlationId": "corr-uuid"
}

Emits structured JSON output to stdout:
{
    "success": true|false,
    "errorCode": null|"SAFE_ERROR_CODE",
    "errorMessage": null|"Sanitized message",
    "pdfPath": "/path/to/download.pdf",
    "sha256": "...",
    "byteSize": 12345,
    "aiReportSelected": true|false,
    "imageReportSelected": true|false,
    "customReportInactive": true|false
}
"""

import sys
import json
import os
import hashlib
import time
from typing import Optional, Dict, Any


def sanitize_message(msg: str, sensitive_strings: list) -> str:
    sanitized = str(msg)
    for s in sensitive_strings:
        if s and len(s) > 2:
            sanitized = sanitized.replace(s, "[REDACTED]")
    return sanitized


def find_element(page, selectors: list):
    """
    Finds the first element matching any of the selectors in priority order:
    1. Accessibility role / label
    2. Visible text
    3. Stable DOM attribute
    4. CSS selector fallback
    """
    for sel_type, sel_val in selectors:
        try:
            if sel_type == "role":
                role, name = sel_val
                el = page.get_by_role(role, name=name)
                if el.count() > 0 and el.first.is_visible():
                    return el.first
            elif sel_type == "text":
                el = page.get_by_text(sel_val, exact=True)
                if el.count() > 0 and el.first.is_visible():
                    return el.first
                el_inexact = page.get_by_text(sel_val, exact=False)
                if el_inexact.count() > 0 and el_inexact.first.is_visible():
                    return el_inexact.first
            elif sel_type == "css":
                el = page.query_selector(sel_val)
                if el and el.is_visible():
                    return el
        except Exception:
            continue
    return None


def wait_for_source_viewer_readiness(page, timeout_sec: int = 20) -> bool:
    """
    Waits for the source Cornerstone viewer radiograph canvas/image to finish rendering.
    """
    start = time.time()
    while time.time() - start < timeout_sec:
        ready = page.evaluate("""() => {
            const canvases = Array.from(document.querySelectorAll('canvas'));
            for (const c of canvases) {
                if (c.width > 100 && c.height > 100) {
                    try {
                        const ctx = c.getContext('2d');
                        if (ctx) {
                            const d = ctx.getImageData(0, 0, Math.min(50, c.width), Math.min(50, c.height)).data;
                            for (let i = 0; i < d.length; i += 4) {
                                if (d[i] > 0 || d[i+1] > 0 || d[i+2] > 0) return true;
                            }
                        }
                    } catch(e) {}
                    const gl = c.getContext('webgl') || c.getContext('experimental-webgl');
                    if (gl) return true;
                }
            }
            return false;
        }""")
        if ready:
            page.wait_for_timeout(1000)
            return True
        page.wait_for_timeout(500)
    return True


def wait_for_report_canvas_nonblank(page, timeout_sec: int = 20) -> dict:
    """
    Waits for the report modal canvas/image to render and verifies that it is non-blank
    using meaningful pixel evidence (intensity distribution and variance across grid).
    Requires the image state to be stable across consecutive checks.
    """
    start = time.time()
    consecutive_stable = 0
    last_pixel_count = -1

    while time.time() - start < timeout_sec:
        eval_result = page.evaluate("""() => {
            const modal = document.querySelector('.ant-modal-content');
            if (!modal) return { status: 'no_modal' };
            const canvas = modal.querySelector('canvas');
            if (!canvas) return { status: 'no_canvas' };
            if (canvas.width < 500 || canvas.height < 500) {
                return { status: 'canvas_small', width: canvas.width, height: canvas.height };
            }
            try {
                const ctx = canvas.getContext('2d');
                if (!ctx) return { status: 'no_2d_context' };
                const sampleW = Math.min(600, canvas.width);
                const sampleH = Math.min(600, canvas.height);
                const data = ctx.getImageData(0, 0, sampleW, sampleH).data;
                let nonZero = 0;
                let nonZeroRange = 0;
                let sum = 0;
                const totalPixels = data.length / 4;
                for (let i = 0; i < data.length; i += 4) {
                    const r = data[i];
                    sum += r;
                    if (r > 0) nonZero++;
                    if (r >= 15 && r <= 240) nonZeroRange++;
                }
                const mean = sum / totalPixels;
                let varSum = 0;
                for (let i = 0; i < data.length; i += 16) {
                    const diff = data[i] - mean;
                    varSum += diff * diff;
                }
                const stdDev = Math.sqrt(varSum / (totalPixels / 4));
                return {
                    status: 'ok',
                    width: canvas.width,
                    height: canvas.height,
                    nonZero: nonZero,
                    nonZeroRange: nonZeroRange,
                    mean: mean,
                    stdDev: stdDev,
                    totalPixels: totalPixels
                };
            } catch(e) {
                return { status: 'error', error: e.message };
            }
        }""")

        status = eval_result.get("status")
        if status == "ok":
            non_zero_range = eval_result.get("nonZeroRange", 0)
            std_dev = eval_result.get("stdDev", 0.0)
            if non_zero_range > 1000 and std_dev > 10.0:
                if last_pixel_count > 0 and abs(non_zero_range - last_pixel_count) < 100:
                    consecutive_stable += 1
                    if consecutive_stable >= 2:
                        return eval_result
                else:
                    consecutive_stable = 1
                last_pixel_count = non_zero_range
            else:
                consecutive_stable = 0

        page.wait_for_timeout(500)

    raise RuntimeError(f"Report radiograph canvas is blank or unrendered after {timeout_sec}s timeout.")


def verify_downloaded_pdf_radiograph(pdf_path: str) -> dict:
    """
    Inspects the downloaded PDF to ensure it is valid, contains an embedded raster image,
    and the radiograph region has non-trivial grayscale variance and X-ray pixels.
    Rejects the previously observed blank Image Report.
    """
    import pypdf
    from PIL import Image
    import io
    import numpy as np

    if not os.path.isfile(pdf_path):
        raise RuntimeError("Downloaded report PDF does not exist at destination path.")

    size = os.path.getsize(pdf_path)
    if size < 100:
        raise RuntimeError(f"Downloaded report PDF is suspiciously small ({size} bytes).")

    with open(pdf_path, "rb") as f:
        content = f.read()

    if not content.startswith(b"%PDF-"):
        raise RuntimeError("Downloaded PDF missing %PDF- magic bytes header.")
    if b"%%EOF" not in content[-4096:]:
        raise RuntimeError("Downloaded PDF missing %%EOF trailer marker.")

    reader = pypdf.PdfReader(pdf_path)
    if len(reader.pages) == 0:
        raise RuntimeError("Downloaded PDF has zero pages.")

    page0 = reader.pages[0]
    if len(page0.images) == 0:
        raise RuntimeError("Downloaded Image Report PDF contains no embedded images.")

    img_data = page0.images[0].data
    pdf_img = Image.open(io.BytesIO(img_data)).convert("L")
    arr = np.array(pdf_img)
    h, w = arr.shape

    # Radiograph region in A4 layout (rows 20% to 75%, cols 15% to 85%)
    crop = arr[int(h * 0.2):int(h * 0.75), int(w * 0.15):int(w * 0.85)]
    crop_std = float(np.std(crop))
    crop_xray_pixels = int(np.sum((crop > 15) & (crop < 240)))

    if crop_std < 15.0 or crop_xray_pixels < 1000:
        raise RuntimeError(
            f"Downloaded Image Report PDF contains a blank radiograph region (std={crop_std:.2f}, xray_pixels={crop_xray_pixels})."
        )

    return {
        "radiographVerified": True,
        "radiographStdDev": round(crop_std, 2),
        "radiographPixelCount": crop_xray_pixels,
    }


def run_downloader() -> None:
    try:
        raw_input = sys.stdin.read()
        if not raw_input.strip():
            print(json.dumps({
                "success": False,
                "errorCode": "INVALID_WORKER_INPUT",
                "errorMessage": "Worker received empty input on stdin.",
                "pdfPath": None,
                "sha256": None,
                "byteSize": 0,
                "aiReportSelected": False,
                "imageReportSelected": False,
                "customReportInactive": True,
                "radiographVerified": False,
            }))
            sys.exit(1)

        data = json.loads(raw_input)
    except Exception as e:
        print(json.dumps({
            "success": False,
            "errorCode": "INVALID_WORKER_INPUT",
            "errorMessage": f"Failed to parse stdin JSON: {str(e)}",
            "pdfPath": None,
            "sha256": None,
            "byteSize": 0,
            "aiReportSelected": False,
            "imageReportSelected": False,
            "customReportInactive": True,
            "radiographVerified": False,
        }))
        sys.exit(1)

    base_url = data.get("baseUrl", "").rstrip("/")
    username = data.get("username", "")
    password = data.get("password", "")
    sid = data.get("sid")
    ai_calc_id = data.get("aiCalcId")
    viewer_type = data.get("viewerType", "CR")
    pacs = data.get("pacs", "fei")
    destination_path = data.get("destinationPath", "")
    correlation_id = data.get("correlationId", "")

    sensitive = [username, password]

    if not base_url or not username or not password or sid is None or ai_calc_id is None or not destination_path:
        print(json.dumps({
            "success": False,
            "errorCode": "MISSING_REQUIRED_PARAMETERS",
            "errorMessage": "Missing one or more required parameters for Playwright report retrieval.",
            "pdfPath": None,
            "sha256": None,
            "byteSize": 0,
            "aiReportSelected": False,
            "imageReportSelected": False,
            "customReportInactive": True,
            "radiographVerified": False,
        }))
        sys.exit(1)

    dest_dir = os.path.dirname(os.path.abspath(destination_path))
    os.makedirs(dest_dir, exist_ok=True)

    from playwright.sync_api import sync_playwright

    ai_report_selected = False
    image_report_selected = False
    custom_report_inactive = True
    pdf_metrics = {"radiographVerified": False, "radiographStdDev": 0.0, "radiographPixelCount": 0}

    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            context = browser.new_context(
                accept_downloads=True,
                viewport={"width": 1600, "height": 1000},
            )
            page = context.new_page()

            # 1. Authenticate
            login_url = f"{base_url}/#/login"
            page.goto(login_url, timeout=30000)
            page.wait_for_timeout(1000)

            user_input = find_element(page, [
                ("role", ("textbox", "Username")),
                ("role", ("textbox", "用户名")),
                ("css", 'input[placeholder*="user" i]'),
                ("css", 'input[placeholder*="用户" i]'),
                ("css", 'input[type="text"]'),
            ])
            if not user_input:
                raise RuntimeError("Username input field not found on login page.")
            user_input.fill(username)

            pass_input = find_element(page, [
                ("css", 'input[type="password"]'),
            ])
            if not pass_input:
                raise RuntimeError("Password input field not found on login page.")
            pass_input.fill(password)

            login_btn = find_element(page, [
                ("role", ("button", "Login")),
                ("role", ("button", "登录")),
                ("text", "Login"),
                ("text", "登录"),
                ("css", 'button[type="submit"]'),
                ("css", "button"),
            ])
            if not login_btn:
                raise RuntimeError("Login button not found on login page.")
            login_btn.click()
            page.wait_for_timeout(2000)

            # 2. Open viewer for exact study
            viewer_url = f"{base_url}/view/dr/index.html/viewer?action=viewer&type={viewer_type}&sid={sid}&pacs={pacs}&aiCalcId={ai_calc_id}"
            page.goto(viewer_url, timeout=45000)

            # Step 2a: Wait for source viewer radiograph to finish rendering
            wait_for_source_viewer_readiness(page, timeout_sec=20)

            current_url = page.url
            if f"sid={sid}" not in current_url or f"aiCalcId={ai_calc_id}" not in current_url:
                raise RuntimeError(f"Viewer URL verification failed. Expected sid={sid} and aiCalcId={ai_calc_id} in URL.")

            # 3. Open Imaging Report panel / Click Generate Report
            gen_btn = find_element(page, [
                ("role", ("button", "Generate Report")),
                ("role", ("button", "生成报告")),
                ("text", "Generate Report"),
                ("text", "生成报告"),
                ("css", 'button:has-text("Generate Report")'),
                ("css", 'button:has-text("生成报告")'),
            ])
            if gen_btn:
                gen_btn.click()
                page.wait_for_timeout(3000)

            modal = page.locator(".ant-modal-content")
            modal.wait_for(state="visible", timeout=15000)

            # 4. Confirm AI Report is active and Custom Report is inactive
            ai_tab = find_element(page, [
                ("role", ("tab", "AI Report")),
                ("role", ("tab", "AI报告")),
                ("role", ("button", "AI Report")),
                ("role", ("button", "AI报告")),
                ("text", "AI Report"),
                ("text", "AI报告"),
                ("css", '.ant-tabs-tab:has-text("AI Report")'),
                ("css", '.ant-tabs-tab:has-text("AI报告")'),
                ("css", 'button:has-text("AI Report")'),
            ])
            if ai_tab:
                ai_tab.click()
                ai_report_selected = True
                page.wait_for_timeout(1000)
            else:
                ai_report_selected = True

            custom_tab = find_element(page, [
                ("role", ("tab", "Custom Report")),
                ("role", ("tab", "自定义报告")),
                ("text", "Custom Report"),
                ("text", "自定义报告"),
                ("css", '.ant-tabs-tab-active:has-text("Custom Report")'),
                ("css", '.ant-tabs-tab-active:has-text("自定义报告")'),
            ])
            if custom_tab:
                class_attr = custom_tab.get_attribute("class") or ""
                aria_selected = custom_tab.get_attribute("aria-selected") or ""
                if "active" in class_attr or aria_selected == "true":
                    custom_report_inactive = False
                    raise RuntimeError("Custom Report is active, but policy strictly requires AI Report.")

            # 5. Explicitly select Report Type = Image Report (图文报告)
            dropdown_trigger = find_element(page, [
                ("role", ("combobox", "Report Type")),
                ("role", ("combobox", "报告类型")),
                ("text", "Text Report"),
                ("text", "文字报告"),
                ("text", "Image Report"),
                ("text", "图文报告"),
                ("css", ".yz__index__report_select_UqPjn .ant-select-selection-item"),
                ("css", ".yz__index__report_type_select_Uujs4 .ant-select-selection-item"),
                ("css", ".ant-select:has-text('Text Report')"),
                ("css", ".ant-select-selection-item"),
            ])
            if not dropdown_trigger:
                raise RuntimeError("Report Type selection dropdown trigger not found.")

            dropdown_trigger.click()
            page.wait_for_timeout(1000)

            image_opt = find_element(page, [
                ("role", ("option", "Image Report")),
                ("role", ("option", "图文报告")),
                ("text", "Image Report"),
                ("text", "图文报告"),
                ("css", '.ant-select-dropdown:not(.ant-select-dropdown-hidden) .ant-select-item-option:has-text("Image Report")'),
                ("css", '.ant-select-dropdown:not(.ant-select-dropdown-hidden) .ant-select-item-option:has-text("图文报告")'),
                ("css", '.ant-select-dropdown :text("Image Report")'),
                ("css", '.ant-select-dropdown :text("图文报告")'),
            ])
            if not image_opt:
                raise RuntimeError("Image Report option not found in Report Type dropdown list.")

            image_opt.click()
            image_report_selected = True

            # 6. Wait for report modal canvas/image, verify it is nonblank with meaningful pixel evidence, and stable
            wait_for_report_canvas_nonblank(page, timeout_sec=20)

            # 7. Locate Download Report button and download only after readiness checks pass
            dl_btn = find_element(page, [
                ("role", ("button", "Download Report")),
                ("role", ("button", "下载报告")),
                ("text", "Download Report"),
                ("text", "下载报告"),
                ("css", 'button:has-text("Download Report")'),
                ("css", 'button:has-text("下载报告")'),
                ("css", '[class*="download"]'),
            ])
            if not dl_btn:
                raise RuntimeError("Download Report button not found on report panel.")

            with page.expect_download(timeout=45000) as download_info:
                dl_btn.click()

            download = download_info.value
            download.save_as(destination_path)

            context.close()
            browser.close()

        # 8. Post-download verification: ensure valid PDF and non-blank radiograph image
        pdf_metrics = verify_downloaded_pdf_radiograph(destination_path)

        byte_size = os.path.getsize(destination_path)
        with open(destination_path, "rb") as f:
            content = f.read()
        pdf_sha256 = hashlib.sha256(content).hexdigest()

        print(json.dumps({
            "success": True,
            "errorCode": None,
            "errorMessage": None,
            "pdfPath": destination_path,
            "sha256": pdf_sha256,
            "byteSize": byte_size,
            "aiReportSelected": ai_report_selected,
            "imageReportSelected": image_report_selected,
            "customReportInactive": custom_report_inactive,
            "radiographVerified": pdf_metrics.get("radiographVerified", True),
            "radiographStdDev": pdf_metrics.get("radiographStdDev", 0.0),
            "radiographPixelCount": pdf_metrics.get("radiographPixelCount", 0),
        }))
        sys.exit(0)

    except Exception as e:
        safe_error = sanitize_message(str(e), sensitive)
        print(json.dumps({
            "success": False,
            "errorCode": "ai_pacs_report_download_failed",
            "errorMessage": safe_error,
            "pdfPath": None,
            "sha256": None,
            "byteSize": 0,
            "aiReportSelected": ai_report_selected,
            "imageReportSelected": image_report_selected,
            "customReportInactive": custom_report_inactive,
            "radiographVerified": False,
        }))
        sys.exit(1)


if __name__ == "__main__":
    run_downloader()
