"""
CubeSpace - Mobile Responsiveness & Cross-Platform Testing Suite
Tests all pages across 11 viewports (320px -> 1920px) on 5 pages.
Reports layout overflow, nav behaviour, touch targets, font scaling, and image issues.
"""

import json
import os
from datetime import datetime
from playwright.sync_api import sync_playwright

BASE_URL = "http://localhost"

VIEWPORTS = {
    "Desktop 1920": {"width": 1920, "height": 1080},
    "Desktop 1440": {"width": 1440, "height": 900},
    "Desktop 1280": {"width": 1280, "height": 800},
    "Laptop 1024":  {"width": 1024, "height": 768},
    "Tablet 768":   {"width": 768,  "height": 1024},
    "Mobile 540":   {"width": 540,  "height": 812},
    "Mobile 480":   {"width": 480,  "height": 896},
    "Mobile 414":   {"width": 414,  "height": 896},
    "Mobile 375":   {"width": 375,  "height": 667},
    "Mobile 360":   {"width": 360,  "height": 740},
    "Mobile 320":   {"width": 320,  "height": 568},
}

TEST_PAGES = [
    {"path": "/",                    "name": "Home"},
    {"path": "/managed_offices.php", "name": "Managed Offices"},
    {"path": "/furnished_offices.php", "name": "Furnished Offices"},
    {"path": "/unfurnished_offices.php", "name": "Unfurnished Offices"},
    {"path": "/contact.php",         "name": "Contact"},
]

MOBILE_BREAKPOINT = 993


def find_overflow_elements(page):
    return page.evaluate("""
        () => {
            const results = [];
            const all = document.querySelectorAll('*');
            const docWidth = document.documentElement.clientWidth;
            for (const el of all) {
                const style = getComputedStyle(el);
                if (style.visibility === 'hidden' || style.display === 'none') continue;
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.right > docWidth + 1) {
                    const tag = el.tagName.toLowerCase();
                    const id = el.id ? '#' + el.id : '';
                    const cls = el.className && typeof el.className === 'string'
                        ? '.' + el.className.trim().split(/\\s+/).slice(0,2).join('.') : '';
                    if (results.length < 30) {
                        results.push({ tag: tag + id + cls, width: Math.round(rect.width), right: Math.round(rect.right) });
                    }
                }
            }
            return results;
        }
    """)

def check_touch_targets(page):
    return page.evaluate("""
        () => {
            const results = [];
            const clickables = document.querySelectorAll('a, button, input[type=\"submit\"], [role=\"button\"], .tab-btn, .locality-chip');
            for (const el of clickables) {
                const rect = el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0 && rect.width < 44 && rect.height < 44) {
                    results.push({ tag: el.tagName.toLowerCase(), text: (el.textContent||'').trim().substring(0,30), w: Math.round(rect.width), h: Math.round(rect.height) });
                    if (results.length > 20) break;
                }
            }
            return results;
        }
    """)

def check_tiny_fonts(page):
    return page.evaluate("""
        () => {
            const results = [];
            const texts = document.querySelectorAll('h1, h2, h3, h4, p, span, a, li, button');
            for (const el of texts) {
                const fs = parseFloat(getComputedStyle(el).fontSize);
                if (fs && fs > 0 && fs < 12 && el.textContent.trim().length > 0) {
                    results.push({ tag: el.tagName.toLowerCase(), text: el.textContent.trim().substring(0,30), fs: fs + 'px' });
                    if (results.length > 15) break;
                }
            }
            return results;
        }
    """)

def check_images(page):
    return page.evaluate("""
        () => {
            const results = [];
            const imgs = document.querySelectorAll('img[width][height]');
            for (const img of imgs) {
                if (!img.naturalWidth || !img.naturalHeight) continue;
                const nr = img.naturalWidth / img.naturalHeight;
                const ar = img.getAttribute('width') / img.getAttribute('height');
                if (Math.abs(nr - ar) > 0.05) {
                    results.push({ src: (img.src||'').substring(0,50), natural: nr.toFixed(2), attr: ar.toFixed(2) });
                    if (results.length > 10) break;
                }
            }
            return results;
        }
    """)


def run_tests():
    results = []

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)

        for vp_name, vp in VIEWPORTS.items():
            context = browser.new_context(viewport=vp)
            ctx_results = []

            for page_info in TEST_PAGES:
                page = context.new_page()
                url = BASE_URL + page_info["path"]
                issues = []
                pname = page_info["name"]

                try:
                    resp = page.goto(url, wait_until="domcontentloaded", timeout=15000)
                    page.wait_for_load_state("networkidle", timeout=10000)
                    page.wait_for_timeout(500)
                except Exception as e:
                    ctx_results.append({ "page": pname, "viewport": vp_name, "status": "ERROR", "issues": [f"Page load issue: {type(e).__name__}"] })
                    page.close()
                    continue

                if resp and resp.status >= 400:
                    issues.append(f"HTTP {resp.status}")

                overflow_els = find_overflow_elements(page)
                if overflow_els:
                    top = overflow_els[:3]
                    details = "; ".join([f'{e["tag"]}(r:{e["right"]})' for e in top])
                    issues.append(f"Overflow: {len(overflow_els)} el(s). {details}")

                is_mobile = vp["width"] < MOBILE_BREAKPOINT
                menu_visible = page.locator('.menu').first.is_visible()
                mobile_btn_visible = page.locator('.mobile-menu').first.is_visible()
                if is_mobile and menu_visible:
                    issues.append("Desktop menu visible on mobile")
                if not is_mobile and mobile_btn_visible:
                    issues.append("Mobile menu button visible on desktop")

                if vp["width"] < 768:
                    st = check_touch_targets(page)
                    if st:
                        issues.append(f"Small touch targets (<44px): {len(st)} found")

                tf = check_tiny_fonts(page)
                if tf:
                    issues.append(f"Tiny fonts (<12px): {len(tf)} found")

                bad_img = check_images(page)
                if bad_img:
                    issues.append(f"Image aspect ratio mismatch: {len(bad_img)} img(s)")

                if is_mobile:
                    try:
                        btn = page.locator('.mobile-menu').first
                        if btn.is_visible():
                            btn.click()
                            page.wait_for_timeout(300)
                            if page.locator('.mobile-nav.active').first.is_visible():
                                c = page.locator('.mobile-nav-close').first
                                if c.is_visible(): c.click()
                                page.wait_for_timeout(200)
                    except Exception:
                        pass

                try:
                    page.locator('.logo img').first.wait_for(state="visible", timeout=2000)
                except Exception:
                    issues.append("Logo missing")

                try:
                    page.locator('.footer').first.wait_for(state="visible", timeout=2000)
                except Exception:
                    issues.append("Footer missing")

                status = "PASS" if not issues else "FAIL"
                ctx_results.append({ "page": pname, "url": url, "viewport": vp_name, "viewport_size": f"{vp['width']}x{vp['height']}", "status": status, "issues": issues })
                print(f"  [{status}] {pname} @ {vp_name} ({vp['width']}x{vp['height']})")
                page.close()

            results.extend(ctx_results)
            context.close()

        browser.close()

    return results


def generate_reports(results):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    output_dir = os.path.join(os.path.dirname(__file__), "reports")
    os.makedirs(output_dir, exist_ok=True)
    ts = datetime.now().strftime('%Y%m%d_%H%M%S')

    passed = [r for r in results if r["status"] == "PASS"]
    failed = [r for r in results if r["status"] == "FAIL"]

    summary_by_page = {}
    for r in results:
        p = r["page"]
        if p not in summary_by_page:
            summary_by_page[p] = {"pass": 0, "fail": 0, "issues": []}
        summary_by_page[p]["pass" if r["status"] == "PASS" else "fail"] += 1
        if r["issues"]:
            summary_by_page[p]["issues"].extend(r["issues"])

    # JSON
    with open(os.path.join(output_dir, f"responsiveness_report_{ts}.json"), "w") as f:
        json.dump({
            "timestamp": timestamp, "base_url": BASE_URL,
            "total_tests": len(results), "passed": len(passed), "failed": len(failed),
            "results": results, "summary_by_page": summary_by_page
        }, f, indent=2)

    # MD report
    lines = [
        f"# CubeSpace Mobile Responsiveness Test Report",
        f"",
        f"**Date:** {timestamp}",
        f"**Base URL:** {BASE_URL}",
        f"**Total Tests:** {len(results)}",
        f"**Passed:** {len(passed)}",
        f"**Failed:** {len(failed)}",
        f"**Pass Rate:** {len(passed)/len(results)*100:.1f}%" if results else "",
        f"",
        f"---",
        f"",
        f"## Summary by Page",
        f"",
        f"| Page | Pass | Fail | Common Issues |",
        f"|------|-----:|-----:|---------------|",
    ]
    for p_name, data in sorted(summary_by_page.items()):
        unique_issues = list(set(data["issues"]))[:5]
        issues_str = "; ".join(unique_issues) if unique_issues else "None"
        lines.append(f"| {p_name} | {data['pass']} | {data['fail']} | {issues_str} |")

    lines.extend(["", "---", "", "## Viewport Coverage", "", "| Viewport | Width | Height |", "|----------|------:|-------:|"])
    for vn, vs in VIEWPORTS.items():
        lines.append(f"| {vn} | {vs['width']} | {vs['height']} |")

    all_issues_count = {}
    for r in failed:
        for iss in r["issues"]:
            key = iss.split(":")[0] if ":" in iss else iss[:50]
            all_issues_count[key] = all_issues_count.get(key, 0) + 1

    lines.extend(["", "---", "", "## Issues by Frequency", "", "| Issue Type | Occurrences |", "|------------|------------:|"])
    for iss, cnt in sorted(all_issues_count.items(), key=lambda x: -x[1]):
        lines.append(f"| {iss} | {cnt} |")

    if failed:
        lines.extend(["", "---", "", "## Detailed Failures", ""])
        for r in failed:
            lines.extend([
                f"### ❌ {r['page']} @ {r['viewport']} ({r.get('viewport_size','')})",
                f"- {'; '.join(r['issues'])}",
                f""
            ])

    with open(os.path.join(output_dir, f"responsiveness_report_{ts}.md"), "w") as f:
        f.write("\n".join(lines) + "\n")

    print(f"\n{'='*60}")
    print(f"  Reports saved in: {output_dir}")
    print(f"  Passed: {len(passed)} | Failed: {len(failed)} | Total: {len(results)}")
    print(f"{'='*60}")


if __name__ == "__main__":
    total = len(TEST_PAGES) * len(VIEWPORTS)
    print(f"{'='*60}")
    print(f"  CubeSpace Responsiveness Test Suite")
    print(f"  {len(TEST_PAGES)} pages x {len(VIEWPORTS)} viewports = {total} tests")
    print(f"{'='*60}")
    results = run_tests()
    generate_reports(results)
