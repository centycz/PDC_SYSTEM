#!/usr/bin/env python3
"""
Unified Printer Server
- Kuchyňský tisk (direct USB text)
- Účtenky (ESC/POS + formátování)
- Legacy objednávky (order_id + items) – kompatibilita se starým /print-order
Datum: 2025-08-27 (merge + legacy kitchen patch)
"""

import os
import json
import logging
import datetime
from collections import deque
from typing import List, Dict, Any, Optional
from flask import Flask, request, jsonify

# ------------------ ENV / KONFIG ------------------

KITCHEN_DEVICE   = os.environ.get("KITCHEN_DEVICE", "/dev/usb/lp0")
KITCHEN_HAS_CUT = os.environ.get("KITCHEN_HAS_CUT","1") in ("1","true","True","yes","YES")
RECEIPT_DEVICE   = os.environ.get("RECEIPT_DEVICE", "/dev/usb/lp1")

USE_DIRECT_USB   = True

PHP_API_BASE = os.environ.get("PHP_API_BASE", "http://127.0.0.1/api/restaurant-api.php")
REQUEST_TIMEOUT = float(os.environ.get("REQUEST_TIMEOUT", "4"))

RECEIPT_WIDTH  = int(os.environ.get("RECEIPT_WIDTH","32"))
CURRENCY       = os.environ.get("CURRENCY","Kč")
PRICE_DECIMALS = int(os.environ.get("PRICE_DECIMALS","0"))
SHOW_REPRINT   = os.environ.get("SHOW_REPRINT","1") not in ("0","false","False","no","NO")
ENABLE_QR      = os.environ.get("ENABLE_QR","0") in ("1","true","True","yes","YES")

# Legacy (starý formát kuchyň / pizza lístku)
LEGACY_PRINT_WIDTH = int(os.environ.get("LEGACY_PRINT_WIDTH","42"))

HEADER_LINES: List[str] = []
for n in range(1, 10):
    v = os.environ.get(f"HEADER_LINE{n}")
    if v and v.strip():
        HEADER_LINES.append(v.strip())

FOOTER_LINES: List[str] = []
for n in range(1, 10):
    v = os.environ.get(f"FOOTER_LINE{n}")
    if v and v.strip():
        FOOTER_LINES.append(v.strip())

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s - %(levelname)s - %(message)s",
    handlers=[
        logging.FileHandler("/tmp/printer_server.log"),
        logging.StreamHandler()
    ]
)

app = Flask(__name__)

try:
    import requests
except ImportError:
    requests = None
    logging.error("Modul 'requests' nenalezen – nainstaluj: pip3 install requests")

# ------------------ DUPLICATE GUARD ------------------
_LAST_PRINTED = deque(maxlen=60)
def guard_duplicate(key: str) -> bool:
    if key in _LAST_PRINTED:
        logging.warning(f"⏭️  Přeskakuji duplicitní tisk {key}")
        return False
    _LAST_PRINTED.append(key)
    return True

# ------------------ UTIL ------------------
def _vis_len(s: str) -> int:
    return len(s)

def line(char: str = "-") -> str:
    return char * RECEIPT_WIDTH + "\n"

def center(text: str) -> str:
    t = text.strip()
    pad = RECEIPT_WIDTH - _vis_len(t)
    if pad <= 0:
        return t[:RECEIPT_WIDTH] + "\n"
    left = pad // 2
    right = pad - left
    return " " * left + t + " " * right + "\n"

def format_currency(val: Any) -> str:
    try:
        num = float(val)
    except:
        num = 0.0
    if PRICE_DECIMALS == 0:
        num = round(num)
        s = f"{int(num)}"
    else:
        s = f"{num:.{PRICE_DECIMALS}f}".replace(".", ",")
    return s + " " + CURRENCY

def translate_payment(method: Optional[str]) -> str:
    if not method:
        return ""
    m = method.lower()
    if m in ("cash","hotove","hotově"):
        return "Hotově"
    if m in ("card","card_terminal","card-terminal","karta","kartou","terminal"):
        return "Kartou"
    if m in ("bank","transfer","prevod","převod"):
        return "Převodem"
    if m in ("qr","qrplatba","qr_payment"):
        return "QR Platba"
    return method

def wrap_name(name: str, indent_len: int) -> List[str]:
    words = name.split()
    lines=[]
    cur=""
    maxw = RECEIPT_WIDTH - indent_len
    for w in words:
        candidate = (cur + " " + w).strip()
        if _vis_len(candidate) <= maxw:
            cur = candidate
        else:
            if cur:
                lines.append(cur)
            cur = w
    if cur:
        lines.append(cur)
    return lines

def format_item(name: str, qty: Any, total_price: Any) -> str:
    price_str = format_currency(total_price)
    prefix = f"{qty}x "
    main = prefix + name
    space = RECEIPT_WIDTH - _vis_len(main) - _vis_len(price_str)
    if space >= 1:
        return main + " " * space + price_str + "\n"
    name_lines = wrap_name(name, indent_len=len(prefix))
    out = ""
    first_line_full = prefix + name_lines[0]
    space_first = RECEIPT_WIDTH - _vis_len(first_line_full) - _vis_len(price_str)
    if space_first >= 1:
        out += first_line_full + " " * space_first + price_str + "\n"
    else:
        out += first_line_full + "\n"
        for extra in name_lines[1:]:
            out += " " * len(prefix) + extra + "\n"
        out += price_str.rjust(RECEIPT_WIDTH) + "\n"
        return out
    for extra in name_lines[1:]:
        out += " " * len(prefix) + extra + "\n"
    return out

def build_receipt(receipt: Dict[str, Any]) -> str:
    out: List[str] = []
    for h in HEADER_LINES:
        out.append(center(h))
    out.append(line("-"))
    rno = receipt.get("receipt_number")
    out.append(f"ÚČTENKA č.: {rno}\n")
    dt_raw = receipt.get("paid_at") or receipt.get("printed_at") or ""
    date_disp = dt_raw
    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%d %H:%M"):
        try:
            dt = datetime.datetime.strptime(dt_raw, fmt)
            date_disp = dt.strftime("%d.%m.%Y  %H:%M")
            break
        except:
            pass
    table = receipt.get("table_code") or receipt.get("table_number") or ""
    emp = receipt.get("employee_name") or ""
    out.append(f"Datum: {date_disp}\n")
    out.append(f"Stůl: {table}  Obsluha: {emp}\n")
    out.append(line("-"))
    total_calc = 0.0
    for it in receipt.get("items", []):
        qty = it.get("quantity", 1)
        line_total = it.get("total_price")
        if line_total is None:
            line_total = qty * it.get("unit_price", 0)
        try:
            total_calc += float(line_total)
        except:
            pass
        out.append(format_item(it.get("name","?"), qty, line_total))
    out.append(line("-"))
    total_reported = receipt.get("total_amount")
    use_total = total_calc
    try:
        if total_reported is not None and abs(float(total_reported) - total_calc) < 0.01:
            use_total = float(total_reported)
    except:
        pass
    total_str = format_currency(use_total)
    label = "CELKEM"
    space = RECEIPT_WIDTH - _vis_len(label) - _vis_len(total_str)
    if space < 1: space = 1
    out.append(label + " " * space + total_str + "\n")
    pay_m = translate_payment(receipt.get("payment_method",""))
    if pay_m:
        lbl = "Platba:"
        space2 = RECEIPT_WIDTH - _vis_len(lbl) - 1 - _vis_len(pay_m)
        if space2 < 1: space2 = 1
        out.append(lbl + " " * space2 + pay_m + "\n")
    out.append(line("-"))
    if SHOW_REPRINT and receipt.get("reprint_count",0) > 0:
        out.append(center(f"KOPIE #{receipt['reprint_count']}"))
        out.append(line("-"))
    for fl in FOOTER_LINES:
        out.append(center(fl))
    out.append("\n")
    return "".join(out)

# ------------------ DIRECT USB ------------------
def direct_usb_print(content: str, device: str) -> bool:
    try:
        data = content.encode("utf-8", "ignore")
        cut = KITCHEN_HAS_CUT  # zap/vyp řez podle proměnné
        with open(device, "wb") as f:
            if cut:
                f.write(b"\x1b\x40")          # ESC @ (INIT)
            f.write(data)
            if cut:
                f.write(b"\n\n\n")            # 3 prázdné řádky
                f.write(b"\x1d\x56\x42\x00")  # GS V B 0 (full cut)
        logging.info(f"✅ Přímý USB tisk ({device}): {len(content)} znaků (cut={cut})")
        return True
    except Exception as e:
        logging.error(f"❌ Chyba přímého USB tisku ({device}): {e}")
        return False


# ------------------ ESC/POS ------------------
def escpos_print(text: str, device_path: str) -> bool:
    INIT = b"\x1b\x40"
    CUT  = b"\x1d\x56\x42\x00"
    try:
        data = text.encode("cp852", "replace")
        with open(device_path, "wb") as f:
            f.write(INIT)
            f.write(data)
            f.write(b"\n\n\n")   # 3 prázdné řádky
            f.write(CUT)
        logging.info(f"✅ Účtenka vytištěna ({len(data)} bytů) -> {device_path}")
        return True
    except Exception as e:
        logging.error(f"❌ Chyba ESC/POS tisku ({device_path}): {e}")
        return False


# ------------------ TISKOVÉ FUNKCE ------------------
def print_receipt(receipt_data: Dict[str, Any]) -> bool:
    text = build_receipt(receipt_data)
    return escpos_print(text, RECEIPT_DEVICE)

def format_kitchen_ticket(order: Dict[str, Any]) -> str:
    lines = []
    lines.append("=== KUCHYŇ ===\n")
    lines.append(f"Objednávka: {order.get('order_number','?')}\n")
    table = order.get("table_code") or order.get("table_number") or ""
    if table:
        lines.append(f"Stůl: {table}\n")
    emp = order.get("employee_name") or order.get("employee") or ""
    if emp:
        lines.append(f"Obsluha: {emp}\n")
    lines.append("--------------------\n")
    for it in order.get("items", []):
        qty = it.get("quantity",1)
        name = it.get("name","?") or it.get("item_name","?")
        lines.append(f"{qty}x {name}\n")
    lines.append("--------------------\n\n")
    return "".join(lines)

def print_kitchen(order_data: Dict[str, Any]) -> bool:
    text = format_kitchen_ticket(order_data)
    ok = direct_usb_print(text, KITCHEN_DEVICE)
    if ok:
        logging.info(f"✅ Tisk kuchyň tiket ({len(text)} znaků)")
    return ok

# ------------------ LEGACY ORDER (starý formát) ------------------
def _legacy_split_items(items: List[Dict[str,Any]]):
    pizza_items = []
    kitchen_items = []
    for item in items:
        category = (item.get('category') or item.get('item_type') or "").lower()
        item_type = (item.get('item_type') or category).lower()
        if category in ['pizza'] or item_type in ['pizza']:
            pizza_items.append(item)
        elif category in ['pasta','predkrm','předkrm','dezert'] or item_type in ['pasta','predkrm','předkrm','dezert']:
            kitchen_items.append(item)
        else:
            # Zařadíme jako kuchyň aby nic nezmizelo
            kitchen_items.append(item)
    return pizza_items, kitchen_items

def _legacy_format_section(title: str, order_data: Dict[str,Any], items: List[Dict[str,Any]]) -> str:
    lines=[]
    lines.append("=" * LEGACY_PRINT_WIDTH)
    lines.append(title.center(LEGACY_PRINT_WIDTH))
    lines.append("=" * LEGACY_PRINT_WIDTH)
    lines.append(f"Objednavka: {order_data.get('order_id','N/A')}")
    lines.append(f"Stul: {order_data.get('table_code','N/A')}")
    lines.append(f"Obsluha: {order_data.get('employee_name','N/A')}")
    lines.append(f"Cas: {datetime.datetime.now().strftime('%d.%m.%Y %H:%M:%S')}")
    lines.append("")
    for it in items:
        name = it.get("item_name") or it.get("name") or "Neznama polozka"
        qty = it.get("quantity",1)
        note = it.get("note","")
        lines.append(f"{qty}x {name}")
        if note:
            lines.append(f"    Poznamka: {note}")
    lines.append("")
    lines.append("=" * LEGACY_PRINT_WIDTH)
    lines.append("")
    return "\n".join(lines)

def build_legacy_order_ticket(order_data: Dict[str,Any]) -> str:
    items = order_data.get("items", [])
    pizza_items, kitchen_items = _legacy_split_items(items)
    sections=[]
    if pizza_items:
        sections.append(_legacy_format_section("PIZZA OBJEDNAVKA", order_data, pizza_items))
    if kitchen_items:
        if pizza_items:
            sections.append("-" * 20 + "\n")
        sections.append(_legacy_format_section("KUCHYNE OBJEDNAVKA", order_data, kitchen_items))
    if not sections:
        # fallback – žádné položky
        lines = []
        lines.append("=" * LEGACY_PRINT_WIDTH)
        lines.append("PIZZA OBJEDNAVKA".center(LEGACY_PRINT_WIDTH))
        lines.append("Zadne polozky pro kuchyn.")
        lines.append("=" * LEGACY_PRINT_WIDTH)
        lines.append("\n\n\n")
        return "\n".join(lines)
    return "\n".join(sections)

def print_legacy_order(order_data: Dict[str,Any]) -> bool:
    content = build_legacy_order_ticket(order_data)
    return direct_usb_print(content, KITCHEN_DEVICE)

# ------------------ FETCH FUNKCE ------------------
def fetch_receipt(receipt_number: Any) -> Optional[Dict[str, Any]]:
    if not requests:
        logging.error("Nemohu fetchnout účtenku – chybí modul requests.")
        return None
    url = f"{PHP_API_BASE}?action=get-receipt&receipt_number={receipt_number}"
    logging.info(f"↗️  Fetch účtenka {receipt_number}: {url}")
    try:
        r = requests.get(url, timeout=REQUEST_TIMEOUT)
        r.raise_for_status()
        js = r.json()
    except Exception as e:
        logging.error(f"❌ Fetch účtenky #{receipt_number} selhal: {e}")
        return None
    if not js.get("success"):
        logging.error(f"❌ API success=false pro účtenku #{receipt_number}: {js}")
        return None
    data = js.get("data")
    if not data:
        logging.error(f"❌ Chybí data účtenky #{receipt_number}")
        return None
    logging.info(f"✅ Načteno {len(data.get('items', []))} položek účtenky #{receipt_number}")
    return data

def fetch_order(order_number: Any) -> Optional[Dict[str, Any]]:
    if not requests:
        logging.error("Chybí modul requests – nelze fetch objednávku.")
        return None
    url = f"{PHP_API_BASE}?action=get-order&order_number={order_number}"
    logging.info(f"↗️  Fetch objednávka {order_number}: {url}")
    try:
        r = requests.get(url, timeout=REQUEST_TIMEOUT)
        r.raise_for_status()
        js = r.json()
    except Exception as e:
        logging.error(f"❌ Fetch objednávky #{order_number} selhal: {e}")
        return None
    if not js.get("success"):
        logging.error(f"❌ API success=false pro objednávku #{order_number}: {js}")
        return None
    data = js.get("data")
    if not data:
        logging.error(f"❌ Chybí data objednávky #{order_number}")
        return None
    logging.info(f"✅ Načteno {len(data.get('items', []))} položek objednávky #{order_number}")
    return data

# ------------------ ENDPOINTY ------------------
@app.route("/health")
def health():
    return {
        "status": "ok",
        "php_api_base": PHP_API_BASE,
        "receipt_width": RECEIPT_WIDTH,
        "header_lines": len(HEADER_LINES),
        "footer_lines": len(FOOTER_LINES),
        "legacy_width": LEGACY_PRINT_WIDTH
    }, 200

@app.route("/print-order", methods=["POST"])
def print_order_endpoint():
    """
    Dual-mode endpoint:
    1) Nová účtenka:
       JSON: { "doc_type":"receipt", "receipt_number": N }
       nebo: { "receipt_number": N }
    2) Legacy objednávka (kuchyň/pizza):
       JSON: { "order_id":X, "table_code":"..", "employee_name":"..", "items":[...] }
    """
    payload = request.get_json(force=True, silent=True) or {}
    # DETEKCE RECEIPT
    is_receipt = (
        "receipt_number" in payload
        or payload.get("doc_type") == "receipt"
    ) and not ("order_id" in payload and "items" in payload and "receipt_number" not in payload)

    if is_receipt:
        receipt_number = payload.get("receipt_number")
        if receipt_number is None:
            return jsonify({"success": False, "error": "Missing receipt_number"}), 400
        key = f"receipt:{receipt_number}"
        if not guard_duplicate(key):
            return jsonify({"success": True, "skipped": True, "reason": "duplicate", "receipt_number": receipt_number}), 200
        data = fetch_receipt(receipt_number)
        if not data:
            return jsonify({"success": False, "receipt_number": receipt_number}), 502
        ok = print_receipt(data)
        return jsonify({
            "success": ok,
            "receipt_number": receipt_number,
            "type": "receipt"
        }), 200 if ok else 500

    # LEGACY OBSAH
    if "order_id" in payload and "items" in payload:
        # Validace základních polí
        missing = [f for f in ["table_code","employee_name","items"] if f not in payload]
        if missing:
            return jsonify({"error": f"Chybí povinné pole: {', '.join(missing)}"}), 400
        if not payload.get("items"):
            return jsonify({"error": "Objednávka musí obsahovat alespoň jednu položku"}), 400
        order_id = payload.get("order_id")
        key = f"legacy-order:{order_id}"
        guard_duplicate(key)  # pouze varování, necháme tisknout i duplicitně
        logging.info(f"🧾 Legacy objednávka přijata order_id={order_id} ({len(payload['items'])} položek)")
        ok = print_legacy_order(payload)
        if ok:
            logging.info(f"✅ Legacy objednávka {order_id} vytištěna")
            return jsonify({
                "success": True,
                "message": "Objednávka byla úspěšně vytištěna",
                "order_id": order_id,
                "printer": "legacy-kitchen",
                "timestamp": datetime.datetime.now().isoformat()
            }), 200
        else:
            logging.error(f"❌ Tisk legacy objednávky {order_id} selhal")
            return jsonify({"error": "Tisk selhal", "order_id": order_id}), 500

    return jsonify({"error": "Unsupported payload (require receipt_number or order_id+items)","success":False}), 400

@app.route("/reprint", methods=["POST"])
def reprint():
    payload = request.get_json(force=True, silent=True) or {}
    receipt_number = payload.get("receipt_number")
    if receipt_number is None:
        return jsonify({"success": False, "error": "Missing receipt_number"}), 400
    data = fetch_receipt(receipt_number)
    if not data:
        return jsonify({"success": False, "receipt_number": receipt_number}), 502
    data["reprint_count"] = (data.get("reprint_count") or 0) + 1
    ok = print_receipt(data)
    return jsonify({"success": ok, "receipt_number": receipt_number, "reprint": True}), 200 if ok else 500

@app.route("/print-kitchen", methods=["POST"])
def print_kitchen_endpoint():
    payload = request.get_json(force=True, silent=True) or {}
    items = payload.get("items") or []
    if not items:
        return jsonify({"success": False, "error": "Missing items"}), 400
    key = f"kitchen:{payload.get('order_number')}"
    guard_duplicate(key)
    ok = print_kitchen(payload)
    return jsonify({"success": ok, "order_number": payload.get("order_number")}), 200 if ok else 500

@app.route("/print-kitchen-order", methods=["POST"])
def print_kitchen_order():
    payload = request.get_json(force=True, silent=True) or {}
    order_number = payload.get("order_number")
    if order_number is None:
        return jsonify({"success": False, "error": "Missing order_number"}), 400
    key = f"kitchen-order:{order_number}"
    if not guard_duplicate(key):
        return jsonify({"success": True, "skipped": True, "reason": "duplicate", "order_number": order_number}), 200
    data = fetch_order(order_number)
    if not data:
        return jsonify({"success": False, "order_number": order_number}), 502
    ok = print_kitchen(data)
    return jsonify({"success": ok, "order_number": order_number}), 200 if ok else 500

@app.route("/print-both", methods=["POST"])
def print_both():
    payload = request.get_json(force=True, silent=True) or {}
    receipt_number = payload.get("receipt_number")
    if receipt_number is None:
        return jsonify({"success": False, "error": "Missing receipt_number"}), 400
    order_number = payload.get("order_number")
    if order_number is not None:
        if guard_duplicate(f"kitchen-order:{order_number}"):
            od = fetch_order(order_number)
            if od:
                print_kitchen(od)
    if not guard_duplicate(f"receipt:{receipt_number}"):
        return jsonify({"success": True, "skipped": True, "reason": "duplicate", "receipt_number": receipt_number}), 200
    rd = fetch_receipt(receipt_number)
    if not rd:
        return jsonify({"success": False, "receipt_number": receipt_number}), 502
    ok = print_receipt(rd)
    return jsonify({"success": ok, "receipt_number": receipt_number, "both": True}), 200 if ok else 500

# ------------------ START SERVERU ------------------
def run_server():
    logging.info("================================================")
    logging.info("🚀 Start Unified Printer Server (legacy kompatibilita ON)")
    logging.info(f"PHP_API_BASE = {PHP_API_BASE}")
    logging.info(f"✅ Kuchyň: {KITCHEN_DEVICE}")
    logging.info(f"✅ Účtenky: {RECEIPT_DEVICE}")
    logging.info(f"Konfigurace: RECEIPT_WIDTH={RECEIPT_WIDTH}, LEGACY_PRINT_WIDTH={LEGACY_PRINT_WIDTH}, HEADER_LINES={len(HEADER_LINES)}, FOOTER_LINES={len(FOOTER_LINES)}")
    try:
        from waitress import serve
        logging.info("Používám waitress server (production WSGI)")
        serve(app, host="0.0.0.0", port=5000)
    except Exception as e:
        logging.warning(f"Waitress nedostupná ({e}) – fallback Flask (dev server)")
        app.run(host="0.0.0.0", port=5000)

if __name__ == "__main__":
    run_server()
