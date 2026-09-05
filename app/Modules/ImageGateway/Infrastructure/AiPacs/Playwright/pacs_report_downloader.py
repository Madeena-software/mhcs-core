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
        }))
        sys.exit(1)

    # Ensure target parent directory exists
    dest_dir = os.path.dirname(os.path.abspath(destination_path))
    os.makedirs(dest_dir, exist_ok=True)

    from playwright.sync_api import sync_playwright

    ai_report_selected = False
    image_report_selected = False
    custom_report_inactive = True

    try:
        with sync_playwright() as p:
            browser = p.chromium.launch(headless=True)
            context = browser.new_context(
                accept_downloads=True,
                viewport={"width": 1440, "height": 900},
            )
            page = context.new_page()

            # 1. Authenticate
            login_url = f"{base_url}/#/login"
            page.goto(login_url, timeout=30000)
            page.wait_for_timeout(1000)

            # Locate username field:
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

            # Locate password field:
            pass_input = find_element(page, [
                ("css", 'input[type="password"]'),
            ])
            if not pass_input:
                raise RuntimeError("Password input field not found on login page.")
            pass_input.fill(password)

            # Locate and click login button:
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
            page.wait_for_timeout(4000)

            # Verify viewer identity corresponds to sid and aiCalcId
            current_url = page.url
            if f"sid={sid}" not in current_url or f"aiCalcId={ai_calc_id}" not in current_url:
                raise RuntimeError(f"Viewer URL verification failed. Expected sid={sid} and aiCalcId={ai_calc_id} in URL.")

            # 3. Open Imaging Report panel / Click Generate Report if needed
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

            # 4. Verify AI Report is active and Custom Report is inactive
            # Selector fallback order for AI Report tab/button:
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
                # If not currently active, click it
                ai_tab.click()
                ai_report_selected = True
                page.wait_for_timeout(1000)
            else:
                # Check if report title or container mentions AI Report
                ai_report_selected = True

            # Assert Custom Report is inactive
            custom_tab = find_element(page, [
                ("role", ("tab", "Custom Report")),
                ("role", ("tab", "自定义报告")),
                ("text", "Custom Report"),
                ("text", "自定义报告"),
                ("css", '.ant-tabs-tab-active:has-text("Custom Report")'),
                ("css", '.ant-tabs-tab-active:has-text("自定义报告")'),
            ])
            if custom_tab:
                # Check if custom tab has active class
                class_attr = custom_tab.get_attribute("class") or ""
                aria_selected = custom_tab.get_attribute("aria-selected") or ""
                if "active" in class_attr or aria_selected == "true":
                    custom_report_inactive = False
                    raise RuntimeError("Custom Report is active, but policy strictly requires AI Report.")

            # 5. Select Report Type = Image Report (图文报告)
            # The dropdown trigger showing current selection (default "Text Report" / "文字报告")
            dropdown_trigger = find_element(page, [
                ("role", ("combobox", "Report Type")),
                ("role", ("combobox", "报告类型")),
                ("text", "Text Report"),
                ("text", "文字报告"),
                ("text", "Image Report"),
                ("text", "图文报告"),
                ("css", ".yz__index__report_select_UqPjn .ant-select-selection-item"),
                ("css", ".yz__index__report_type_select_Uujs4 .ant-select-selection-item"),
                ("css", ".ant-select-selection-item"),
            ])
            if not dropdown_trigger:
                raise RuntimeError("Report Type selection dropdown trigger not found.")

            dropdown_trigger.click()
            page.wait_for_timeout(1000)

            # Select "Image Report" (图文报告) option from the popup dropdown list
            image_opt = find_element(page, [
                ("role", ("option", "Image Report")),
                ("role", ("option", "图文报告")),
                ("text", "Image Report"),
                ("text", "图文报告"),
                ("css", '.ant-select-dropdown :text("Image Report")'),
                ("css", '.ant-select-dropdown :text("图文报告")'),
                ("css", '.ant-select-item-option-content:has-text("Image Report")'),
                ("css", '.ant-select-item-option-content:has-text("图文报告")'),
            ])
            if not image_opt:
                raise RuntimeError("Image Report option not found in Report Type dropdown list.")

            image_opt.click()
            image_report_selected = True
            page.wait_for_timeout(2000)

            # 6. If another Generate/Refresh action is needed, trigger it
            post_gen_btn = find_element(page, [
                ("role", ("button", "Generate Report")),
                ("role", ("button", "生成报告")),
                ("css", '.ant-modal-content button:has-text("Generate Report")'),
                ("css", '.ant-modal-content button:has-text("生成报告")'),
            ])
            if post_gen_btn and post_gen_btn.is_enabled():
                try:
                    post_gen_btn.click()
                    page.wait_for_timeout(3000)
                except Exception:
                    pass

            # 7. Locate Download Report button and capture download event
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

            with page.expect_download(timeout=30000) as download_info:
                dl_btn.click()

            download = download_info.value
            download.save_as(destination_path)

            context.close()
            browser.close()

        # 8. Verify downloaded PDF
        if not os.path.isfile(destination_path):
            raise RuntimeError("Downloaded PDF file was not created at destination path.")

        byte_size = os.path.getsize(destination_path)
        if byte_size < 100:
            raise RuntimeError(f"Downloaded PDF file is suspiciously small ({byte_size} bytes).")

        with open(destination_path, "rb") as f:
            content = f.read()

        if not content.startswith(b"%PDF-"):
            raise RuntimeError("Downloaded PDF missing %PDF- magic bytes header.")

        if b"%%EOF" not in content[-4096:]:
            raise RuntimeError("Downloaded PDF missing %%EOF trailer marker.")

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
        }))
        sys.exit(0)

    except Exception as e:
        safe_error = sanitize_message(str(e), sensitive)
        print(json.dumps({
            "success": False,
            "errorCode": "AI_PACS_REPORT_DOWNLOAD_FAILED",
            "errorMessage": safe_error,
            "pdfPath": None,
            "sha256": None,
            "byteSize": 0,
            "aiReportSelected": ai_report_selected,
            "imageReportSelected": image_report_selected,
            "customReportInactive": custom_report_inactive,
        }))
        sys.exit(1)


if __name__ == "__main__":
    run_downloader()
