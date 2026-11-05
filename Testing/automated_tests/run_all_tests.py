import sys
import os
import time

# Ensure current directory is on sys.path
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
if SCRIPT_DIR not in sys.path:
    sys.path.insert(0, SCRIPT_DIR)

from test_config import check_preflight, TestResult
from test_suite1_auth import run_suite_1
from test_suite2_staff import run_suite_2
from test_suite3_district import run_suite_3
from test_suite4_5_system_db import run_suite_4_5

import docx
from docx import Document
from docx.shared import Inches, Pt, RGBColor
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.enum.table import WD_TABLE_ALIGNMENT, WD_ALIGN_VERTICAL
from docx.enum.section import WD_ORIENT
from docx.oxml import parse_xml
from docx.oxml.ns import nsdecls

def update_test_artifacts(all_results: list[TestResult]):
    """Automatically updates the Word document and Markdown report with real execution results."""
    print("\nUpdating test specification documentation with actual results...")

    result_map = {r.test_id: r for r in all_results}

    # 1. Update Word Document
    doc_path = os.path.abspath(os.path.join(SCRIPT_DIR, "..", "Test_Cases_Specification.docx"))
    alt_doc_path = os.path.abspath(os.path.join(SCRIPT_DIR, "..", "Master_Test_Cases_Specification.docx"))

    def set_cell_background(cell, fill_hex):
        tcPr = cell._tc.get_or_add_tcPr()
        shd = parse_xml(f'<w:shd {nsdecls("w")} w:fill="{fill_hex}"/>')
        tcPr.append(shd)

    def set_cell_margins(cell, top=80, bottom=80, left=90, right=90):
        tcPr = cell._tc.get_or_add_tcPr()
        tcMar = parse_xml(f'<w:tcMar {nsdecls("w")}><w:top w:w="{top}" w:type="dxa"/><w:bottom w:w="{bottom}" w:type="dxa"/><w:left w:w="{left}" w:type="dxa"/><w:right w:w="{right}" w:type="dxa"/></w:tcMar>')
        tcPr.append(tcMar)

    def set_table_borders(table, color="D1D5DB"):
        tblPr = table._tbl.tblPr
        borders = parse_xml(
            f'<w:tblBorders {nsdecls("w")}>'
            f'<w:top w:val="single" w:sz="6" w:space="0" w:color="{color}"/>'
            f'<w:left w:val="none"/>'
            f'<w:bottom w:val="single" w:sz="8" w:space="0" w:color="{color}"/>'
            f'<w:right w:val="none"/>'
            f'<w:insideH w:val="single" w:sz="4" w:space="0" w:color="{color}"/>'
            f'<w:insideV w:val="single" w:sz="4" w:space="0" w:color="{color}"/>'
            f'</w:tblBorders>'
        )
        tblPr.append(borders)

    doc = Document()
    section = doc.sections[0]
    section.orientation = WD_ORIENT.LANDSCAPE
    section.page_width = Inches(11.69)
    section.page_height = Inches(8.27)
    section.top_margin = Inches(0.5)
    section.bottom_margin = Inches(0.5)
    section.left_margin = Inches(0.5)
    section.right_margin = Inches(0.5)

    title_p = doc.add_paragraph()
    title_run = title_p.add_run("Decentralized Medical Stock Tracker for Rural Clinics")
    title_run.font.name = "Segoe UI"
    title_run.font.size = Pt(18)
    title_run.font.bold = True
    title_run.font.color.rgb = RGBColor(15, 76, 129)

    passed_count = sum(1 for r in all_results if r.status == "Pass")
    failed_count = sum(1 for r in all_results if r.status == "Fail")

    sub_p = doc.add_paragraph()
    sub_run = sub_p.add_run(f"Automated Test Execution Report - 77 Tests ({passed_count} Passed, {failed_count} Failed)")
    sub_run.font.name = "Segoe UI"
    sub_run.font.size = Pt(11)
    sub_run.font.color.rgb = RGBColor(16, 185, 129) if failed_count == 0 else RGBColor(220, 38, 38)

    # Metadata block
    meta_table = doc.add_table(rows=2, cols=4)
    meta_table.alignment = WD_TABLE_ALIGNMENT.CENTER
    set_table_borders(meta_table, "CBD5E1")
    meta_rows = [
        [("Execution Date:", True), (time.strftime("%d %b %Y, %H:%M:%S"), False), ("Environment:", True), ("Localhost (XAMPP - Apache & MySQL)", False)],
        [("Total Executed:", True), (f"{len(all_results)} Tests", False), ("Verdict:", True), (f"{passed_count} Passed / {failed_count} Failed", False)]
    ]
    for r_idx, row in enumerate(meta_table.rows):
        for c_idx, cell in enumerate(row.cells):
            label, is_bold = meta_rows[r_idx][c_idx]
            set_cell_background(cell, "F1F5F9" if is_bold else "FFFFFF")
            set_cell_margins(cell, top=60, bottom=60, left=80, right=80)
            p = cell.paragraphs[0]
            run = p.add_run(label)
            run.font.name = "Segoe UI"
            run.font.size = Pt(8.5)
            run.font.bold = is_bold

    headers = [
        ("Test ID", 0.9), ("Module", 0.95), ("Test Scenario", 1.6), ("Technique", 1.0),
        ("Test Steps", 1.8), ("Test Data", 1.1), ("Expected Result", 1.6),
        ("Actual Result", 1.2), ("Status", 0.75), ("Priority", 0.6)
    ]

    # Group by suite
    suites = [
        ("Suite 1: Authentication & RBAC", [r for r in all_results if r.test_id.startswith("TC_AUTH_")]),
        ("Suite 2: PHC Clinic Staff Operations", [r for r in all_results if r.test_id.startswith("TC_STAFF_")]),
        ("Suite 3: District Admin Operations", [r for r in all_results if r.test_id.startswith("TC_DIST_")]),
        ("Suite 4 & 5: System Admin & Database Integrity", [r for r in all_results if r.test_id.startswith("TC_SYS_")])
    ]

    for suite_name, suite_cases in suites:
        doc.add_paragraph()
        sp = doc.add_paragraph()
        srun = sp.add_run(suite_name)
        srun.font.name = "Segoe UI"
        srun.font.size = Pt(13)
        srun.font.bold = True
        srun.font.color.rgb = RGBColor(15, 76, 129)

        table = doc.add_table(rows=1, cols=10)
        table.alignment = WD_TABLE_ALIGNMENT.CENTER
        set_table_borders(table, "CBD5E1")

        hdr_cells = table.rows[0].cells
        for idx, (title, width) in enumerate(headers):
            cell = hdr_cells[idx]
            cell.width = Inches(width)
            set_cell_background(cell, "0F4C81")
            set_cell_margins(cell, top=80, bottom=80, left=60, right=60)
            p = cell.paragraphs[0]
            p.alignment = WD_ALIGN_PARAGRAPH.CENTER
            run = p.add_run(title)
            run.font.name = "Segoe UI"
            run.font.size = Pt(8)
            run.font.bold = True
            run.font.color.rgb = RGBColor(255, 255, 255)

        for row_idx, r in enumerate(suite_cases):
            row_cells = table.add_row().cells
            bg_color = "F8FAFC" if (row_idx % 2 == 1) else "FFFFFF"

            row_data = [
                r.test_id, r.module, r.scenario, r.technique,
                "Executed automated test sequence.", "Standard test payload.",
                "System behaves according to specification.", r.actual_result,
                r.status, r.priority
            ]

            for c_idx, val in enumerate(row_data):
                cell = row_cells[c_idx]
                cell.width = Inches(headers[c_idx][1])
                set_cell_background(cell, bg_color)
                set_cell_margins(cell, top=60, bottom=60, left=50, right=50)
                cell.vertical_alignment = WD_ALIGN_VERTICAL.CENTER
                p = cell.paragraphs[0]
                if c_idx in [0, 1, 3, 8, 9]:
                    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
                else:
                    p.alignment = WD_ALIGN_PARAGRAPH.LEFT
                run = p.add_run(val)
                run.font.name = "Segoe UI"
                run.font.size = Pt(7.5)

                if c_idx == 8: # Status
                    run.font.bold = True
                    run.font.color.rgb = RGBColor(22, 163, 74) if r.status == "Pass" else RGBColor(220, 38, 38)
                elif c_idx == 0:
                    run.font.bold = True
                    run.font.color.rgb = RGBColor(15, 76, 129)

    try:
        doc.save(doc_path)
        print(f"  [OK] Successfully updated Word document: {doc_path}")
    except PermissionError:
        doc.save(alt_doc_path)
        print(f"  [OK] Saved updated Word document to: {alt_doc_path}")

    # 2. Update Markdown Document
    md_path = os.path.abspath(os.path.join(SCRIPT_DIR, "..", "TEST_CASES.md"))
    if os.path.exists(md_path):
        try:
            with open(md_path, "r", encoding="utf-8") as f:
                content = f.read()
            for r in all_results:
                # Replace "| Pending |" with "| Pass |" for this test_id
                old_row_frag = f"| `{r.test_id}` |"
                if old_row_frag in content:
                    lines = content.splitlines()
                    for idx, line in enumerate(lines):
                        if old_row_frag in line:
                            # Replace empty actual result and Pending status
                            parts = line.split("|")
                            if len(parts) >= 11:
                                parts[8] = f" {r.actual_result} "
                                parts[9] = f" {r.status} "
                                lines[idx] = "|".join(parts)
                    content = "\n".join(lines)
            with open(md_path, "w", encoding="utf-8") as f:
                f.write(content)
            print(f"  [OK] Successfully updated Markdown document: {md_path}")
        except Exception as e:
            print(f"  [WARN] Could not update TEST_CASES.md: {e}")

def main():
    print("\n" + "="*70)
    print("  DECENTRALIZED MEDICAL STOCK TRACKER - AUTOMATED TEST RUNNER")
    print("="*70)

    if not check_preflight():
        print("[!] Pre-flight checks failed.")
        print("    Please start Apache and MySQL in XAMPP Control Panel.")
        print("    Then re-run this command: python Testing/automated_tests/run_all_tests.py\n")
        return

    start_time = time.time()
    all_results: list[TestResult] = []

    # Run each suite
    all_results.extend(run_suite_1())
    all_results.extend(run_suite_2())
    all_results.extend(run_suite_3())
    all_results.extend(run_suite_4_5())

    duration = time.time() - start_time
    total = len(all_results)
    passed = sum(1 for r in all_results if r.status == "Pass")
    failed = sum(1 for r in all_results if r.status == "Fail")

    print("\n" + "="*70)
    print("  AUTOMATED TEST EXECUTION SUMMARY")
    print("="*70)
    print(f"  Total Test Cases Executed : {total}")
    print(f"  Passed                    : {passed}")
    print(f"  Failed                    : {failed}")
    print(f"  Pass Rate                 : {(passed/total)*100:.1f}%")
    print(f"  Total Duration            : {duration:.2f} seconds")
    print("="*70)

    update_test_artifacts(all_results)
    print("\n[Done] All tests executed and reports updated successfully!\n")

if __name__ == "__main__":
    main()
