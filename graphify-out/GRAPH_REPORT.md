# Graph Report - Decentralized-Medical-Stock-Tracker-for-Rural-Clinics  (2026-10-24)

## Corpus Check
- 59 files · ~545,255 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 25 file(s) not represented in the graph (top: .css 24, .doc 1)

## Summary
- 195 nodes · 276 edges · 56 communities (8 shown, 48 thin omitted)
- Extraction: 97% EXTRACTED · 3% INFERRED · 0% AMBIGUOUS · INFERRED: 9 edges (avg confidence: 0.87)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- reports_live.js
- Master Software Test Cases: Decentralized Medical Stock Tracker for Rural Clinics
- js/district_reports.js
- system_reports.js
- run_all_tests.py
- create_report
- district_medicine_requests.js
- update_test_artifacts
- Ponytail, lazy senior dev mode
- district_inventory.js
- medical_stock_tracker.sql

## God Nodes (most connected - your core abstractions)
1. `create_report()` - 18 edges
2. `TestResult` - 12 edges
3. `check_preflight()` - 10 edges
4. `get_default_accounts()` - 10 edges
5. `main()` - 9 edges
6. `update_test_artifacts()` - 8 edges
7. `get_db()` - 8 edges
8. ``phcs`` - 8 edges
9. `run_suite_1()` - 7 edges
10. `render()` - 6 edges

## Surprising Connections (you probably didn't know these)
- `update_test_artifacts()` --uses--> `TestResult`  [INFERRED]
  Testing/automated_tests/run_all_tests.py → Testing/automated_tests/test_config.py
- `main()` --uses--> `TestResult`  [INFERRED]
  Testing/automated_tests/run_all_tests.py → Testing/automated_tests/test_config.py
- `main()` --calls--> `check_preflight()`  [EXTRACTED]
  Testing/automated_tests/run_all_tests.py → Testing/automated_tests/test_config.py
- `main()` --calls--> `run_suite_1()`  [EXTRACTED]
  Testing/automated_tests/run_all_tests.py → Testing/automated_tests/test_suite1_auth.py
- `main()` --calls--> `run_suite_2()`  [EXTRACTED]
  Testing/automated_tests/run_all_tests.py → Testing/automated_tests/test_suite2_staff.py

## Import Cycles
- None detected.

## Communities (56 total, 48 thin omitted)

### Community 0 - "reports_live.js"
Cohesion: 0.23
Nodes (12): buildSnapshot(), chartConfig(), load(), render(), renderCharts(), renderKpis(), renderTable(), rng() (+4 more)

### Community 1 - "Master Software Test Cases: Decentralized Medical Stock Tracker for Rural Clinics"
Cohesion: 0.29
Nodes (6): Master Software Test Cases: Decentralized Medical Stock Tracker for Rural Clinics, Suite 1: Authentication & Role-Based Access Control (RBAC) - Phase 2, Suite 2: PHC Clinic Staff Operations - Phase 3, Suite 3: District Admin Operations - Phase 4, Suite 4 & 5: System Admin Master Data & Database Integrity - Phase 5, Test Execution Guidelines

### Community 7 - "run_all_tests.py"
Cohesion: 0.23
Nodes (12): main(), check_preflight(), get_db(), get_default_accounts(), TestResult, run_suite_1(), get_staff_session(), run_suite_2() (+4 more)

### Community 8 - "create_report"
Cohesion: 0.19
Nodes (11): create_report(), add_code_block(), add_field(), add_guidance_box(), add_image_figure(), add_table_data(), add_two_column_figures(), remove_table_borders() (+3 more)

### Community 10 - "district_medicine_requests.js"
Cohesion: 0.48
Nodes (6): closeProcessModal(), escapeHtml(), initProcessModalEvents(), openProcessModal(), showToast(), toggleSupplyOptions()

### Community 15 - "Ponytail, lazy senior dev mode"
Cohesion: 0.50
Nodes (3): Before you write, Ponytail, lazy senior dev mode, The smallest complete change

### Community 20 - "district_inventory.js"
Cohesion: 0.19
Nodes (19): capitalize(), closeDispatchModal(), closeInwardModal(), escapeHtml(), filterWarehouseTable(), handleDispatchSubmit(), handleInwardSubmit(), initDispatchValidation() (+11 more)

### Community 65 - "medical_stock_tracker.sql"
Cohesion: 0.33
Nodes (13): `district_admin`, `district_inventory`, `district_stock_in`, `doctors`, `inventory`, `medicine_requests`, `medicines`, `patient_visits` (+5 more)

## Knowledge Gaps
- **48 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `create_report()` connect `create_report` to `generate_report.py`?**
  _High betweenness centrality (0.041) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `TestResult` (e.g. with `main()` and `update_test_artifacts()`) actually correct?**
  _`TestResult` has 2 INFERRED edges - model-reasoned connections that need verification._
- **Why does `update_test_artifacts()` connect `update_test_artifacts` to `run_all_tests.py`?**
  _High betweenness centrality (0.012) - this node is a cross-community bridge._