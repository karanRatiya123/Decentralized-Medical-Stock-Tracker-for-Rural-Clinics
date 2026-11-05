import os
import sys
import requests
import pymysql
from dataclasses import dataclass
from typing import Optional, List

BASE_URL = "http://localhost/Decentralized-Medical-Stock-Tracker-for-Rural-Clinics/"

DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "medical_stock_tracker",
    "charset": "utf8mb4",
    "cursorclass": pymysql.cursors.DictCursor
}

@dataclass
class TestResult:
    test_id: str
    module: str
    scenario: str
    technique: str
    status: str       # 'Pass', 'Fail', 'Blocked', 'Pending'
    actual_result: str
    priority: str

def check_preflight() -> bool:
    """Checks if Apache and MySQL are running before starting tests."""
    print("==================================================================")
    print("  Pre-flight Environment Check")
    print("==================================================================")
    
    # 1. Check Web Server
    web_ok = False
    try:
        r = requests.get(BASE_URL + "login.php", timeout=3)
        if r.status_code == 200:
            print("  [OK] Apache Web Server is running and responding (HTTP 200).")
            web_ok = True
        else:
            print(f"  [WARN] Web Server returned unexpected status: {r.status_code}")
    except Exception as e:
        print(f"  [FAIL] Cannot connect to Web Server at {BASE_URL}")
        print("         Please open XAMPP Control Panel and click START on Apache.")
    
    # 2. Check Database
    db_ok = False
    try:
        conn = pymysql.connect(**DB_CONFIG)
        with conn.cursor() as cur:
            cur.execute("SELECT DATABASE() AS db")
            res = cur.fetchone()
            print(f"  [OK] MySQL Database is connected (Database: {res['db']}).")
        conn.close()
        db_ok = True
    except Exception as e:
        print("  [FAIL] Cannot connect to MySQL on localhost:3306")
        print("         Please open XAMPP Control Panel and click START on MySQL.")
        print(f"         Error detail: {e}")
        
    print("==================================================================\n")
    return web_ok and db_ok

def get_db():
    """Returns a direct connection to MySQL database."""
    return pymysql.connect(**DB_CONFIG)

def get_default_accounts():
    """Returns verified active demo accounts from the database for automated tests."""
    return {
        "staff": ("naimish", "12345"),
        "district_admin": ("district_admin_gs", "admin123"),
        "system_admin": ("root_admin", "admin123")
    }
