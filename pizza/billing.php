<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['order_user'])) {
    header('Location: login.php');
    exit;
}

// Get user information from session
$user_name = $_SESSION['order_user'];
$full_name = $_SESSION['order_full_name'];
$user_role = $_SESSION['is_admin'] ? 'admin' : 'user';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Účtování - Přehled stolů</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: linear-gradient(135deg, #ff5858 0%, #f09819 100%); min-height: 100vh; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { color: white; font-size: 2.5em; margin-bottom: 10px; text-shadow: 2px 2px 4px rgba(0,0,0,0.3);}
        .nav-links { display: flex; justify-content: center; gap: 15px; margin-bottom: 20px; flex-wrap: wrap;}
        .nav-link { padding: 10px 20px; background: rgba(255,255,255,0.2); color: white; text-decoration: none; border-radius: 25px; font-weight: 500; transition: all 0.3s ease;}
        .nav-link:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px);}
        .nav-link.active { background: white; color: #ff5858;}
        .container { max-width: 1200px; margin: 0 auto; background: white; border-radius: 15px; padding: 30px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);}
        .section-title { font-size: 1.6em; color: #ff5858; margin: 25px 0 10px 0; font-weight: bold;}
        .orders-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 20px; margin-bottom: 30px;}
        .order-card { border: 3px solid #ff5858; border-radius: 15px; padding: 20px; background: #fff; box-shadow: 0 5px 15px rgba(0,0,0,0.07); transition: transform 0.3s ease;}
        .order-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #eee;}
        .table-number { font-size: 1.3em; font-weight: bold; color: #f09819; display: flex; align-items: center; gap: 10px;}
        .order-time { color: #666; font-size: 0.95em;}
        .order-items { margin-bottom: 20px; }
        .bill-list { margin: 0 0 8px 0; padding: 0; list-style: none; }
        .bill-item { border-bottom: 1px solid #ffe4bf; padding: 6px 0; display: flex; justify-content: space-between; align-items: center;}
        .bill-item:last-child { border-bottom: none;}
        .bill-name {font-weight: bold;}
        .bill-qty {color: #999;}
        .bill-note {font-size: 0.93em; color: #b67100; font-style: italic;}
        .bill-status { font-size: 0.9em; border-radius: 5px; padding: 2px 8px; margin-left: 7px; color: #fff; background: #ff5858;}
        .bill-item.waiting { background: #ffeaea; }
        .bill-item.doruceno { background: #e0ffe5; }
        .bill-item.zaplaceno { background: #157347; color: #fff;}
        .bill-status.doruceno { background: #27ae60; color: #fff;}
        .bill-status.waiting { background: #ff5858; color: #fff;}
        .bill-status.zaplaceno { background: #0e472e; color: #fff;}
        .bill-total { font-weight: bold; color: #ff5858; font-size: 1.1em; text-align:right; margin-top: 7px;}
        .paid-info { color: #198754; font-size: 0.93em; margin-left: 8px;}
        .btn { padding: 8px 16px; border: none; border-radius: 8px; font-weight: bold; font-size: 14px; cursor: pointer; transition: all 0.3s ease;}
        .btn-pay { background: #ff5858; color: white; margin-top: 9px;}
        .btn-pay:hover { background: #f09819; }
        .btn-split { background: #f09819; color: #fff; margin-top: 9px; margin-left: 7px;}
        .btn-split:hover { background: #ff5858; }
        .last-update { text-align: center; color: #666; font-size: 0.95em; margin-top: 15px;}
        .empty-state { text-align: center; padding: 40px 20px; color: #999;}
        .empty-state .icon { font-size: 4em; margin-bottom: 20px; display: block;}
        
        /* GLOBÁLNÍ MODAL BACKGROUND - JEDNOTNÝ PRO VŠECHNY MODALY */
        .modal-bg { 
            position: fixed; 
            left: 0; 
            top: 0; 
            width: 100vw; 
            height: 100vh; 
            background: rgba(0,0,0,0.6); 
            display: none;
            align-items: center; 
            justify-content: center; 
            z-index: 1000;
        }
      /* Přidej k existujícím stylům */
.quantity-selector {
    margin: 20px 0;
    text-align: center;
}

.quantity-selector label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
    color: #333;
}

.quantity-controls {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
}
/* Přidej nebo uprav tyto CSS styly */
.bill-status.doruceno { 
    background: #28a745 !important;  /* ✅ Zelená místo červené */
    color: #fff !important;
}

.bill-status.waiting { 
    background: #ff5858 !important;  /* ✅ Červená pro čekající */
    color: #fff !important;
}

.bill-status.zaplaceno { 
    background: #0e472e !important;  /* ✅ Tmavě zelená pro zaplaceno */
    color: #fff !important;
}

.bill-status.cancelled { 
    background: #721c24 !important;  /* ✅ Tmavě červená pro zrušeno */
    color: white !important;
}

/* Styly pro celé řádky položek */
.bill-item.doruceno { 
    background: #e0ffe5 !important;  /* ✅ Světle zelená pro doručené */
}

.bill-item.waiting { 
    background: #ffeaea !important;  /* ✅ Světle červená pro čekající */
}

.bill-item.zaplaceno { 
    background: #157347 !important;  /* ✅ Zelená pro zaplacené */
    color: #fff !important;
}

.bill-item.cancelled {
    background: #f8d7da !important;  /* ✅ Růžová pro zrušené */
    color: #721c24 !important;
    text-decoration: line-through;
    opacity: 0.7;
}

.table-select-btn.selected {
    background: #007bff !important;
    color: white !important;
    border-color: #007bff !important;
}
.quantity-controls button {
    background: #007bff;
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    font-size: 18px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.quantity-controls button:hover {
    background: #0056b3;
    transform: scale(1.1);
}

.quantity-controls button:disabled {
    background: #6c757d;
    cursor: not-allowed;
    transform: none;
}

.quantity-controls input {
    width: 80px;
    height: 40px;
    text-align: center;
    font-size: 18px;
    font-weight: bold;
    border: 2px solid #007bff;
    border-radius: 8px;
}

.cancel-reason {
    margin: 20px 0;
}

.cancel-reason label {
    display: block;
    margin-bottom: 10px;
    font-weight: bold;
    color: #333;
}

.cancel-reason input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
}

.status-indicator {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
    margin-left: 5px;
}

.status-indicator.pending {
    background: #fff3cd;
    color: #856404;
}

.status-indicator.preparing {
    background: #d1ecf1;
    color: #0c5460;
}

.status-indicator.ready {
    background: #d4edda;
    color: #155724;
}

.status-indicator.delivered {
    background: #d1ecf1;
    color: #0c5460;
}
      
       /* Styly pro tlačítko zrušení */
.btn-cancel-item {
    background: #dc3545;
    color: white;
    font-size: 12px;
    padding: 4px 8px;
    margin-left: 8px;
    border-radius: 4px;
}

.btn-cancel-item:hover {
    background: #c82333;
}

/* Modal pro potvrzení zrušení */
.cancel-item-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.cancel-item-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    min-width: 400px;
    max-width: 90vw;
    text-align: center;
}

.cancel-item-title {
    color: #dc3545;
    margin-bottom: 20px;
    font-size: 1.5em;
    font-weight: bold;
}

.cancel-item-info {
    margin: 20px 0;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.cancel-item-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 25px;
}

.cancel-item-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.cancel-item-btn.confirm {
    background: #dc3545;
    color: white;
}

.cancel-item-btn.confirm:hover {
    background: #c82333;
}

.cancel-item-btn.cancel {
    background: #6c757d;
    color: white;
}

.cancel-item-btn.cancel:hover {
    background: #5a6268;
}
.status-indicator.paid {
    background: #157347;
    color: white;
}

.status-indicator.cancelled {
    background: #721c24;
    color: white;
}

.bill-status.cancelled {
    background: #721c24 !important;
    color: white !important;
}
       
        /* Přidej k existujícím stylům */
.btn-change-table {
    background: #17a2b8;
    color: white;
    margin-top: 9px;
    margin-left: 7px;
}
.btn-change-table:hover {
    background: #138496;
}

/* Modal pro změnu stolu */
.table-change-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.6);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.table-change-content {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.3);
    min-width: 400px;
    max-width: 90vw;
}

.table-change-title {
    color: #ff5858;
    margin-bottom: 20px;
    font-size: 1.5em;
    font-weight: bold;
    text-align: center;
}

.table-select-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 10px;
    margin: 20px 0;
    max-height: 300px;
    overflow-y: auto;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 8px;
}

.table-select-btn {
    min-height: 50px;
    border: 2px solid #ddd;
    background: #f8f9fa;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    transition: all 0.3s ease;
}

.table-select-btn:hover {
    border-color: #ff5858;
    background: #fff5f5;
}

.table-select-btn.occupied {
    background: #e53e3e;
    color: white;
    border-color: #e53e3e;
}

.table-select-btn.current {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.table-change-buttons {
    display: flex;
    justify-content: center;
    gap: 15px;
    margin-top: 20px;
}

.table-change-btn {
    padding: 12px 25px;
    border: none;
    border-radius: 8px;
    font-weight: bold;
    cursor: pointer;
    transition: all 0.3s ease;
}

.table-change-btn.confirm {
    background: #28a745;
    color: white;
}

.table-change-btn.confirm:hover {
    background: #218838;
}

.table-change-btn.cancel {
    background: #dc3545;
    color: white;
}

.table-change-btn.cancel:hover {
    background: #c82333;
}
        /* SPOLEČNÝ STYL PRO VŠECHNY MODALY */
        .modal, .confirm-modal { 
            min-width: 600px;
            max-width: 90vw;
            max-height: 90vh;
            background: white; 
            border-radius: 15px; 
            box-shadow: 0 15px 40px rgba(0,0,0,0.3); 
            padding: 40px 50px; 
            position: relative;
            overflow-y: auto;
            margin: 20px;
        }
        
        /* SPOLEČNÝ STYL PRO NADPISY VŠECH MODALŮ */
        .modal h3, .confirm-modal h3 {
            color: #ff5858; 
            margin-bottom: 30px; 
            font-size: 1.8em;
            font-weight: bold;
            text-align: center;
        }
        
        /* Styly pro seznam položek v rozdělovacím modalu */
        .modal-list {
            list-style: none; 
            padding: 0; 
            margin: 0 0 25px 0;
        }
        
        .modal-item {
            margin-bottom: 18px; 
            font-size: 1.1em;
        }
        
        /* Styl pro celkovou částku */
        .modal-total {
            margin-top: 20px; 
            font-weight: bold; 
            font-size: 1.4em; 
            text-align: center; 
            color: #ff5858;
        }
        
        /* Styly pro tlačítka + a - */
        .quantity-buttons {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .quantity-button {
            background-color: #ddd;
            border: none;
            padding: 8px 12px;
            cursor: pointer;
            border-radius: 5px;
            font-size: 1.2em;
            font-weight: bold;
            min-width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-button:hover {
            background-color: #bbb;
        }
        
        /* SPOLEČNÝ STYL PRO SEKCE VÝBĚRU PLATBY */
        .modal-payment-section, .payment-method-section {
            margin: 30px 0;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .modal-payment-label, .payment-method-label {
            font-size: 1.3em;
            font-weight: bold;
            color: #333;
            margin-bottom: 20px;
            text-align: center;
        }

        .modal-payment-options, .payment-options {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin: 25px 0;
        }

        .modal-payment-option, .payment-option {
            display: flex;
            flex-direction: column;
            align-items: center;
            cursor: pointer;
            padding: 20px;
            border: 3px solid #ddd;
            border-radius: 15px;
            transition: all 0.3s ease;
            min-width: 150px;
        }

        .modal-payment-option:hover, .payment-option:hover {
            border-color: #ff5858;
            background-color: #fff8f8;
        }

        .modal-payment-option.selected, .payment-option.selected {
            border-color: #ff5858;
            background-color: #fff0f0;
        }

        .modal-payment-option input[type="radio"], .payment-option input[type="radio"] {
            display: none;
        }

        .modal-payment-icon, .payment-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }

        .modal-payment-text, .payment-text {
            font-size: 1.2em;
            font-weight: bold;
            color: #333;
        }

        /* SPOLEČNÝ STYL PRO TLAČÍTKA VŠECH MODALŮ */
        .modal-btns, .confirm-modal-btns {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 40px;
        }

        .modal-btn, .confirm-modal-btn {
            padding: 18px 40px;
            border: none;
            border-radius: 12px;
            font-weight: bold;
            font-size: 1.3em;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 180px;
        }

        /* Zelené tlačítko pro zaplacení */
        .modal-btn:not(.cancel), .confirm-modal-btn.ok {
            background-color: #28a745;
            color: white;
        }

        .modal-btn:not(.cancel):hover, .confirm-modal-btn.ok:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        /* Červené tlačítko pro zrušení */
        .modal-btn.cancel, .confirm-modal-btn.cancel {
            background-color: #dc3545;
            color: white;
        }

        .modal-btn.cancel:hover, .confirm-modal-btn.cancel:hover {
            background-color: #c82333;
            transform: translateY(-2px);
        }

        /* Style pro informaci o platbě */
        .previous-payment-info {
            font-size: 0.9em;
            color: #777;
            margin-top: 5px;
        }

        /* Responsive design pro menší obrazovky */
        @media (max-width: 768px) {
            .modal, .confirm-modal {
                min-width: 95vw;
                padding: 30px 20px;
                margin: 10px;
            }
            
            .modal-btn, .confirm-modal-btn {
                padding: 15px 25px;
                font-size: 1.1em;
                min-width: 140px;
            }
            
            .modal-payment-options, .payment-options {
                flex-direction: column;
                gap: 15px;
                align-items: center;
            }
            
            .modal-payment-option, .payment-option {
                min-width: 200px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="login.php" style="background: rgba(255,255,255,0.9); color: #ff5858; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">← ZPĚT NA PŘIHLÁŠENÍ</a>
        </div>
        <div style="color: white; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            <button onclick="logout()" style="background: rgba(255,255,255,0.9); color: #ff5858; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-left: 15px; cursor: pointer;">🚪 Odhlásit se</button>
        </div>
    </div>
    
    <div class="header">
        <h1>💰 Účtování</h1>
        <div class="nav-links">
            <a href="index.php" class="nav-link" id="nav-obsluha">🏠 Objednávky</a>
            <a href="kitchen.php" class="nav-link" id="nav-kuchyn">👨‍🍳 Kuchyň</a>
<a href="pasta-kuchyn.php" class="nav-link " id="nav-pasta">🍝 Pasta kuchyň</a>
            <a href="bar.php" class="nav-link" id="nav-bar">🍺 Bar</a>
            <a href="serving.php" class="nav-link" id="nav-servirovani">🍽️ Servírování</a>
            <a href="billing.php" class="nav-link active" id="nav-uctovani">💰 Účtování</a>
            <a href="historie.php" class="nav-link">📋 Historie</a>
        </div>
    </div>
    <div class="container">
        <div class="section-title">Aktuální obsazené stoly s objednávkami</div>
        <div class="orders-grid" id="ordersContainer"></div>
        <div class="last-update">Poslední aktualizace: <span id="lastUpdate">-</span></div>
    </div>

    <!-- Globální kontejner pro VŠECHNY modaly -->
    <div id="modal-bg" class="modal-bg" style="display:none"></div>
<div id="tableChangeModal" class="table-change-modal">
    <div class="table-change-content">
        <div class="table-change-title">Změnit stůl objednávky</div>
        <div>
            <p><strong>Aktuální stůl:</strong> <span id="currentTableName"></span></p>
            <p><strong>Celková částka:</strong> <span id="orderTotalAmount"></span></p>
        </div>
        <div class="table-select-grid" id="tableSelectGrid">
            <!-- Stoly se načtou dynamicky -->
        </div>
        <div class="table-change-buttons">
            <button class="table-change-btn confirm" onclick="confirmTableChange()" id="confirmChangeBtn" disabled>
                Změnit stůl
            </button>
            <button class="table-change-btn cancel" onclick="closeTableChangeModal()">
                Zrušit
            </button>
        </div>
    </div>
</div>
    <script>
    const API = 'api/restaurant-api.php';
    let tables = [];
    let lastSplitTable = null, lastSplitGroups = [];
    let currentTableToPay = null;
    
    // Logout function
    function logout() {
        if (confirm('Opravdu se chcete odhlásit?')) {
            window.location.href = 'login.php?logout=1';
        }
    }

    // ✅ NOVÁ LOGIKA - Rozdělí položky podle statusu místo seskupování
    function groupBillItems(items) {
        let result = [];
        
        // Seskup podle názvu, ceny a poznámky
        let nameGroups = {};
        for (let item of items) {
            let groupKey = item.item_name + "|" + item.unit_price + "|" + (item.note||'');
            if (!nameGroups[groupKey]) {
                nameGroups[groupKey] = [];
            }
            nameGroups[groupKey].push(item);
        }
        
        // Pro každou skupinu názvu vytvořit samostatné řádky podle statusu
        for (let groupKey in nameGroups) {
            let itemsInGroup = nameGroups[groupKey];
            
            // Seskup podle statusu
            let statusGroups = {};
            for (let item of itemsInGroup) {
                if (!statusGroups[item.status]) {
                    statusGroups[item.status] = [];
                }
                statusGroups[item.status].push(item);
            }
            
            // Vytvoř řádek pro každý status
            for (let status in statusGroups) {
                let statusItems = statusGroups[status];
                let totalQty = statusItems.reduce((sum, item) => sum + Number(item.quantity), 0);
                
                result.push({
                    item_name: statusItems[0].item_name,
                    unit_price: Number(statusItems[0].unit_price),
                    note: statusItems[0].note,
                    qty: totalQty,
                    ids: statusItems.map(item => item.id),
                    all_items: statusItems,
                    status: status
                });
            }
        }
        
        return result;
    }

    // ✅ OPRAVENÁ FUNKCE - nahraďte existující loadTables() funkcí
async function loadTables() {
    console.log('=== LOAD TABLES DEBUG ===');
    
    // 1. Načti všechny stoly
    const tRes = await fetch(API + '?action=tables');
    let allTables = [];
    try { 
        const res = await tRes.json();
        console.log('All tables response:', res);
        allTables = res.data && res.data.tables ? res.data.tables : []; 
    } catch(e){
        console.error('Error loading tables:', e);
    }
    
    console.log('All tables count:', allTables.length);
    console.log('All tables:', allTables);
    
    // ✅ OPRAVA: Nezaměřovat jen na obsazené stoly, ale na všechny s aktivní session
    let filtered = [];
    
    for (const table of allTables) {
        console.log(`\n--- Processing table ${table.table_number} (${table.table_code}) ---`);
        console.log('Table status:', table.status);
        
        // ✅ OPRAVA: Načti bill pro každý stůl bez ohledu na status
        const billRes = await fetch(API + '?action=session-bill&table_number=' + table.table_number);
        let items = [];
        try { 
            const bill = await billRes.json();
            console.log('Bill response for table', table.table_number, ':', bill);
            items = bill.data && bill.data.items ? bill.data.items.filter(item => item.status !== 'cancelled') : [];
        } catch(e){
            console.error('Error loading bill for table', table.table_number, ':', e);
        }
        
        console.log('Items count for table', table.table_number, ':', items.length);
        console.log('Items:', items);
        
        // ✅ ZMĚNA: Zobraz stůl pokud má jakékoliv nezrušené položky
        if (items.length === 0) {
            console.log('Skipping table', table.table_number, '- no items');
            continue;
        }

        // ✅ OPRAVA: Zkontroluj jestli jsou všechny položky zaplacené
        const allItemsPaid = items.every(item => item.status === 'paid');
        console.log('All items paid for table', table.table_number, ':', allItemsPaid);
        
        if (allItemsPaid) {
            console.log('All items paid for table', table.table_number, '- marking as cleaned');
            try {
                await fetch(API + '?action=mark-table-as-cleaned', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ table_number: table.table_number })
                });
                console.log(`Table ${table.table_number} marked as cleaned`);
            } catch (e) {
                console.error('Error marking table as cleaned:', e);
            }
            continue;
        }

        // Spočítej částky
        let paidAmount = 0;
        let totalAmount = 0;
        items.forEach(item => {
            totalAmount += item.unit_price * item.quantity;
            if (item.status === 'paid') {
                paidAmount += item.unit_price * item.quantity;
            }
        });

        table.paidAmount = paidAmount;
        table.remainingAmount = totalAmount - paidAmount;
        table.billItems = groupBillItems(items.filter(item => item.status !== 'cancelled'));
        
        console.log('Adding table', table.table_number, 'to filtered list');
        console.log('Total amount:', totalAmount, 'Paid amount:', paidAmount);
        
        filtered.push(table);
    }
    
    console.log('Final filtered tables count:', filtered.length);
    console.log('Final filtered tables:', filtered.map(t => `${t.table_number}(${t.table_code})`));
    
    tables = filtered;
    renderTables();
    updateLastUpdateTime();
}

   // PŘIDEJ TYTO PROMĚNNÉ NA ZAČÁTEK <script> SEKCE
let currentChangeSession = null;
let selectedNewTable = null;
let availableTables = [];



// PŘIDEJ TYTO NOVÉ FUNKCE:
async function showTableChangeModal(tableNumber, tableCode, totalAmount) {
    currentChangeSession = tableNumber;
    selectedNewTable = null;
    
    document.getElementById('currentTableName').textContent = tableCode;
    document.getElementById('orderTotalAmount').textContent = formatCurrency(totalAmount);
    
    // Načti všechny stoly
    await loadAllTablesForChange();
    
    document.getElementById('tableChangeModal').style.display = 'flex';
}

function closeTableChangeModal() {
    document.getElementById('tableChangeModal').style.display = 'none';
    currentChangeSession = null;
    selectedNewTable = null;
}

async function loadAllTablesForChange() {
    try {
        const response = await fetch(`${API}?action=tables`);
        const result = await response.json();
        
        if (result.success) {
            availableTables = result.data.tables || [];
            renderTableSelectGrid();
        }
    } catch (error) {
        console.error('Chyba při načítání stolů:', error);
    }
}

function renderTableSelectGrid() {
    const grid = document.getElementById('tableSelectGrid');
    
    grid.innerHTML = availableTables.map(table => {
        let buttonClass = 'table-select-btn';
        let buttonText = table.table_code || `Stůl ${table.table_number}`;
        
        if (table.table_number === currentChangeSession) {
            buttonClass += ' current';
            buttonText += ' (Aktuální)';
        } else if (table.status === 'occupied') {
            buttonClass += ' occupied';
            buttonText += ' (Obsazen)';
        }
        
        return `
            <button class="${buttonClass}" 
                    onclick="selectNewTable(${table.table_number}, '${table.table_code || `Stůl ${table.table_number}`}')"
                    ${table.table_number === currentChangeSession ? 'disabled' : ''}>
                ${buttonText}
            </button>
        `;
    }).join('');
}

function selectNewTable(tableNumber, tableCode) {
    // Odeber předchozí výběr
    document.querySelectorAll('.table-select-btn').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Označ nový výběr
    event.target.classList.add('selected');
    selectedNewTable = tableNumber;
    
    // Povol tlačítko změny
    const confirmBtn = document.getElementById('confirmChangeBtn');
    confirmBtn.disabled = false;
    confirmBtn.textContent = `Přesunout na ${tableCode}`;
}

async function confirmTableChange() {
    if (!currentChangeSession || !selectedNewTable) return;
    
    const confirmBtn = document.getElementById('confirmChangeBtn');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Přesouvám...';
    
    try {
        const response = await fetch(`${API}?action=change-table`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                from_table: currentChangeSession,
                to_table: selectedNewTable
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Stůl byl úspěšně změněn!');
            closeTableChangeModal();
            loadTables(); // Reload účtovacích dat
        } else {
            alert('Chyba při změně stolu: ' + (result.error || 'Neznámá chyba'));
        }
    } catch (error) {
        console.error('Chyba při změně stolu:', error);
        alert('Chyba při změně stolu: ' + error.message);
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Změnit stůl';
    }
}

// Přidej helper funkci pro formátování měny (pokud není definovaná)
function formatCurrency(amount) {
    return new Intl.NumberFormat('cs-CZ', {
        style: 'currency',
        currency: 'CZK',
        minimumFractionDigits: 0
    }).format(amount);
}

 
    function statusLabel(status) {
    if(status === 'delivered') return {text:'Doručeno', css:'doruceno'};
    if(status === 'paid') return {text:'ZAPLACENO', css:'zaplaceno'};
    if(status === 'cancelled') return {text:'ZRUŠENO', css:'cancelled'};
    return {text:'Čeká na doručení', css:'waiting'};  // ✅ Změněno z 'Doručována'
}
function getBillItemClass(status) {
    switch(status) {
        case 'paid': return 'zaplaceno';
        case 'delivered': return 'doruceno';
        case 'cancelled': return 'cancelled';
        case 'pending':
        case 'preparing':
        case 'ready':
        default: return 'waiting';
    }
}

  function renderTables() {
    const container = document.getElementById('ordersContainer');
    if (tables.length === 0) {
        container.innerHTML = `<div class="empty-state">
            <span class="icon">🍽️</span>
            <h3>Žádný obsazený stůl s účtem</h3>
            <p>Momentálně není co účtovat</p>
        </div>`;
        return;
    }
    
    container.innerHTML = tables.map(t => {
        const items = t.billItems || [];
        const total = items.reduce((s,x)=>s+(x.qty*x.unit_price),0);
        const unpaid = items.filter(i => i.status !== 'paid');
        const allPaid = unpaid.length === 0;
        
        // ✅ OPRAVA: Zkontroluj jen nezaplacené položky
const unpaidItems = items.filter(i => i.status !== 'paid');
const unpaidDeliveredItems = unpaidItems.filter(i => i.status === 'delivered');
const allUnpaidAreDelivered = unpaidItems.length > 0 && unpaidItems.every(i => i.status === 'delivered');
const canPayAll = allUnpaidAreDelivered && !allPaid;

// ✅ PŘIDEJ TUTO LOGIKU PRO ZOBRAZENÍ VŠECH POLOŽEK
const hasAnyItems = items.length > 0;

console.log('Items:', items.map(i => `${i.item_name}: ${i.status}`)); // Debug
console.log('Unpaid items:', unpaidItems.length, 'Unpaid delivered:', unpaidDeliveredItems.length, 'Can pay all:', canPayAll); // Debug

        const canPaySplit = !allPaid;

        return `
        <div class="order-card${allPaid ? ' bill-item zaplaceno' : ''}">
    <div class="order-header">
        <div class="table-number">${t.table_code}</div>
        <div class="order-time">${t.status === 'occupied' ? 'Obsazeno' : t.status}</div>
    </div>
    <div class="order-items">
        <ul class="bill-list">
        ${items.map(i=>{
            const stav = statusLabel(i.status);
            const canCancel = i.status !== 'paid' && i.status !== 'cancelled';
            
            const displayClass = getBillItemClass(i.status);
            
            const modalData = {
                item_name: i.item_name,
                qty: i.qty,
                unit_price: i.unit_price,
                ids: i.ids,
                all_items: i.all_items || [i],
                status: i.status
            };
            
            return `
            <li class="bill-item ${displayClass}">
                <span>
                    <span class="bill-name">${i.item_name}</span>
                    ${i.qty > 1 ? `<span class="bill-qty">(${i.qty}×)</span>` : ""}
                    ${i.note ? `<span class="bill-note">📝 ${i.note}</span>` : ""}
                    <span class="bill-status ${stav.css}">${stav.text}</span>
                    ${canCancel ? `<button class="btn btn-cancel-item" onclick='showCancelItemModal(${JSON.stringify(modalData)})'>❌</button>` : ''}
                </span>
                <span>${i.status === 'cancelled' ? '0 Kč (zrušeno)' : (i.qty * i.unit_price + ' Kč')}</span>
            </li>
            `;
        }).join('')}
        </ul>
        <div class="bill-total">Celkem: ${total} Kč</div>
        ${allPaid 
        ? `<div style="font-size:1.16em;font-weight:bold;background:#198754;color:#fff;border-radius:8px;padding:7px 0;text-align:center;margin-top:14px;">ZAPLACENO</div>`
: `<button class="btn btn-pay" onclick="showConfirmModal(${t.table_number})" ${!canPayAll ? 'disabled' : ''} style="${!canPayAll ? 'opacity:0.5;background:#dc3545;' : 'background:#28a745;'}">
     ${!canPayAll ? 'ČEKÁ SE NA DORUČENÍ' : 'ZAPLATIT VŠE'}
   </button>
   <button class="btn btn-split" onclick="paySplit(${t.table_number}, this)" ${!hasAnyItems ? 'disabled' : ''} style="${!hasAnyItems ? 'opacity:0.5;' : ''}">
     ROZDĚLIT ÚČET
   </button>
   <button class="btn btn-change-table" onclick="showTableChangeModal(${t.table_number}, '${t.table_code}', ${total})">
     ZMĚNIT STŮL
   </button>`}
    </div>
    ${t.paidAmount > 0 ? `
    <div class="previous-payment-info">
        Uhrazeno: ${t.paidAmount} Kč<br>
        Zbývá: ${t.remainingAmount} Kč
    </div>
    ` : ''}
</div>
`;
    }).join('');
}

// MODAL PRO ZAPLACENÍ CELÉ OBJEDNÁVKY
function showConfirmModal(table_number) {
    currentTableToPay = table_number;
    
    console.log('=== SHOWCONFIRMMODAL DEBUG ===');
    console.log('Table number:', table_number);
    console.log('All tables:', tables);
    
    const table = tables.find(t => t.table_number == table_number); // ✅ Použij == místo ===
    console.log('Found table:', table);
    
    if (table && table.billItems) {
        console.log('Bill items for table:', table.billItems);
        
        // Projdi každou položku
        table.billItems.forEach(item => {
            console.log(`Item: ${item.item_name}, Status: ${item.status}, Qty: ${item.qty}, Unit price: ${item.unit_price}, Total: ${item.qty * item.unit_price}`);
        });
    }
    
    let totalAmount = 0;
    let deliveredAmount = 0;
    
    if (table && table.billItems) {
        // ✅ DEBUGOVÁNÍ - vypíšeme všechny položky
        console.log('Calculating amounts...');
        
        // Spočítej celkovou částku VŠECH nezrušených položek
        totalAmount = table.billItems
            .filter(item => item.status !== 'cancelled')
            .reduce((sum, item) => {
                const itemTotal = item.qty * item.unit_price;
                console.log(`Adding to total: ${item.item_name} (${item.status}) = ${itemTotal} Kč`);
                return sum + itemTotal;
            }, 0);
            
        // Jen doručené položky k zaplacení
        deliveredAmount = table.billItems
            .filter(item => item.status === 'delivered')
            .reduce((sum, item) => {
                const itemTotal = item.qty * item.unit_price;
                console.log(`Adding to delivered: ${item.item_name} = ${itemTotal} Kč`);
                return sum + itemTotal;
            }, 0);
    }
    
    console.log('Final amounts - Total:', totalAmount, 'Delivered:', deliveredAmount);
    console.log('=== END DEBUG ===');
    
    const modalBg = document.getElementById('modal-bg');
    modalBg.style.display = 'flex';

    modalBg.innerHTML = `
        <div class="confirm-modal">
            <h3>Zaplatit celou objednávku?</h3>
            
            <div class="payment-amounts" style="margin: 20px 0;">
                <div style="
                    background: #e3f2fd;
                    border: 2px solid #2196F3;
                    border-radius: 8px;
                    padding: 15px;
                    margin-bottom: 10px;
                    text-align: center;
                ">
                    <div style="font-size: 14px; color: #1976D2; margin-bottom: 8px;">💰 Celková částka objednávky</div>
                    <div style="font-size: 24px; font-weight: bold; color: #1976D2;">${totalAmount} Kč</div>
                </div>
                
                                
                ${totalAmount > deliveredAmount ? `
                    <div style="
                        background: #fff3e0;
                        border: 1px solid #ff9800;
                        border-radius: 5px;
                        padding: 8px;
                        margin-top: 10px;
                        text-align: center;
                        font-size: 12px;
                        color: #e65100;
                    ">
                        ℹ️ Zbývající položky (${totalAmount - deliveredAmount} Kč) se zaplatí po doručení
                    </div>
                ` : ''}
            </div>
            
            <div class="payment-method-section">
                <div class="payment-method-label">Vyberte způsob platby:</div>
                <div class="payment-options">
                    <label class="payment-option selected" for="payment-cash">
                        <input type="radio" id="payment-cash" name="payment-method" value="cash" checked>
                        <div class="payment-icon">💵</div>
                        <div class="payment-text">Hotovost</div>
                    </label>
                    <label class="payment-option" for="payment-card">
                        <input type="radio" id="payment-card" name="payment-method" value="card">
                        <div class="payment-icon">💳</div>
                        <div class="payment-text">Karta</div>
                    </label>
                </div>
            </div>

            <div class="confirm-modal-btns">
                <button class="confirm-modal-btn ok" onclick="confirmPayAll()">ZAPLATIT ${deliveredAmount} Kč</button>
                <button class="confirm-modal-btn cancel" onclick="closeModal()">ZPĚT</button>
            </div>
        </div>
    `;
    
    setupPaymentOptionListeners('.payment-option');
}

// MODAL PRO ROZDĚLENÍ ÚČTU
function showSplitModal(groups) {
    const modalBg = document.getElementById('modal-bg');
    modalBg.style.display = 'flex';

    if (!groups || groups.length === 0) {
        modalBg.innerHTML = `
            <div class="modal">
                <h3>Žádné položky k zaplacení</h3>
                <div class="modal-btns">
                    <button class="modal-btn cancel" onclick="closeModal()">ZPĚT</button>
                </div>
            </div>
        `;
        return;
    }

    // ✅ SPOČÍTEJ CELKOVOU ČÁSTKU VŠECH POLOŽEK
    const totalAmount = groups.reduce((sum, item) => sum + (item.qty * item.unit_price), 0);

    modalBg.innerHTML = `
        <div class="modal">
            <h3>Zaplacení vybraných položek</h3>
            
            <!-- ✅ PŘIDEJ CELKOVOU ČÁSTKU -->
            <div class="split-total-display" style="
                background: #f8f9fa;
                border: 2px solid #2196F3;
                border-radius: 8px;
                padding: 12px;
                margin: 10px 0;
                text-align: center;
                font-size: 16px;
                font-weight: bold;
                color: #2196F3;
            ">
                💰 Celková částka objednávky: ${totalAmount} Kč
            </div>
            
            <ul class="modal-list">
                ${groups.map((i,idx)=>{
                    if (i.qty === 1) {
                        return `
                        <li class="modal-item">
                            <label>
                                <input type="checkbox" class="split-check" data-groupidx="${idx}" checked>
                                ${i.item_name}
                                (${i.unit_price.toFixed(2)} Kč)
                                ${i.note ? `<span class="bill-note">📝 ${i.note}</span>` : ""}
                            </label>
                        </li>
                        `;
                    } else {
                        return `
                        <li class="modal-item">
                            <label>
                                ${i.item_name}
                                <div class="quantity-buttons">
                                    <button class="quantity-button" onclick="changeQuantity(${idx}, -1)">-</button>
                                    <input type="number" min="0" max="${i.qty}" value="0" class="split-qty" data-groupidx="${idx}" style="width:50px;margin:0 8px;font-size:1.1em;" oninput="updateModalTotal()">
                                    <button class="quantity-button" onclick="changeQuantity(${idx}, 1)">+</button>
                                </div>
                                × (${i.unit_price.toFixed(2)} Kč/ks)
                                ${i.note ? `<span class="bill-note">📝 ${i.note}</span>` : ""}
                            </label>
                        </li>
                        `;
                    }
                }).join('')}
            </ul>
            
            <div class="modal-payment-section">
                <div class="modal-payment-label">Vyberte způsob platby:</div>
                <div class="modal-payment-options">
                    <label class="modal-payment-option selected" for="split-payment-cash">
                        <input type="radio" id="split-payment-cash" name="split-payment-method" value="cash" checked>
                        <div class="modal-payment-icon">💵</div>
                        <div class="modal-payment-text">Hotovost</div>
                    </label>
                    <label class="modal-payment-option" for="split-payment-card">
                        <input type="radio" id="split-payment-card" name="split-payment-method" value="card">
                        <div class="modal-payment-icon">💳</div>
                        <div class="modal-payment-text">Karta</div>
                    </label>
                </div>
            </div>
            
            <div class="modal-total" id="modal-total"></div>
            <div class="modal-btns">
                <button class="modal-btn" onclick="confirmSplitPay()">ZAPLATIT VYBRANÉ</button>
                <button class="modal-btn cancel" onclick="closeModal()">ZPĚT</button>
            </div>
        </div>
    `;

    updateModalTotal();

    // Event listenery pro výběr platby
    setupPaymentOptionListeners('.modal-payment-option');
    
    // Event listenery pro quantity inputy a checkboxy
    Array.from(document.querySelectorAll('.split-qty')).forEach(input => {
        input.addEventListener('input', updateModalTotal);
    });
    Array.from(document.querySelectorAll('.split-check')).forEach(cb => {
        cb.addEventListener('change', updateModalTotal);
    });
}

// Společná funkce pro nastavení event listenerů pro výběr platby
function setupPaymentOptionListeners(selector) {
    document.querySelectorAll(selector).forEach(option => {
        option.addEventListener('click', function() {
            // Odstraníme selected ze všech možností tohoto typu
            document.querySelectorAll(selector).forEach(opt => opt.classList.remove('selected'));
            // Přidáme selected k aktuální možnosti
            this.classList.add('selected');
            // Označíme příslušný radio button
            this.querySelector('input[type="radio"]').checked = true;
        });
    });
}

// SPOLEČNÁ FUNKCE PRO ZAVŘENÍ VŠECH MODALŮ
function closeModal() {
    const modalBg = document.getElementById('modal-bg');
    modalBg.style.display = 'none';
    modalBg.innerHTML = '';
    currentTableToPay = null;
}

function confirmPayAll() {
    const paymentMethod = document.querySelector('input[name="payment-method"]:checked').value;
    console.log('Způsob platby:', paymentMethod);
    payAll(currentTableToPay, null, paymentMethod);  // ✅ PŘIDÁN paymentMethod
    closeModal();
}

function payAll(table_number, btn, paymentMethod = 'cash') {
    if (btn) { btn.disabled = true; btn.textContent = 'Zpracovávám...'; }
    console.log('PayAll called for table:', table_number, 'payment method:', paymentMethod);
    
    fetch(API + '?action=session-bill&table_number=' + table_number)
    .then(r=>r.json())
    .then(res => {
        console.log('Session bill response:', res);
        const items = res.data.items || [];
        
        // ✅ OPRAVA: Kontroluj jen nezrušené položky
        const activeItems = items.filter(i => i.status !== 'cancelled');
        const unpaidItems = activeItems.filter(i => i.status !== 'paid');
        const allDelivered = unpaidItems.every(i => i.status === 'delivered');
        
        console.log('Active items:', activeItems.length, 'Unpaid:', unpaidItems.length, 'All delivered:', allDelivered);

        if (!allDelivered) {
            if (btn) { 
                btn.disabled = true; 
                btn.textContent = 'ČEKÁ SE NA DORUČENÍ'; 
            }
            return;
        }

        const payItems = items
            .filter(i => i.status === 'delivered')
            .map(i => ({
                id: i.id,
                quantity: Number(i.quantity)
            }));

        if (payItems.length === 0) {
            if (btn) { btn.disabled = false; btn.textContent = 'ZAPLATIT VŠE'; }
            loadTables();
            return;
        }

        // ✅ NOVÁ LOGIKA: Zkontroluj jestli se platí všechny zbývající položky
        const allActiveItems = activeItems.length;
        const payingItemsCount = payItems.length;
        const alreadyPaidCount = items.filter(i => i.status === 'paid').length;
        const willBeFullyPaid = (payingItemsCount + alreadyPaidCount) >= allActiveItems;
        
        console.log('Will be fully paid:', willBeFullyPaid, 'Paying:', payingItemsCount, 'Already paid:', alreadyPaidCount, 'Total active:', allActiveItems);

        // ✅ PŘIDEJ payment_method a flag pro úplné zaplacení
        fetch(API + '?action=pay-items', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                items: payItems,
                payment_method: paymentMethod,
                table_number: table_number,  // ✅ PŘIDÁNO
                close_session: willBeFullyPaid  // ✅ PŘIDÁNO - signalizuje že se má uzavřít session
            })
        })
        .then(r=>r.json())
        .then(res => {
            console.log('Pay items response:', res);
            if (btn) { btn.disabled = false; btn.textContent = 'ZAPLATIT VŠE'; }
            loadTables();
        })
        .catch(error => {
            console.error('Error paying items:', error);
            if (btn) { btn.disabled = false; btn.textContent = 'ZAPLATIT VŠE'; }
        });
    })
    .catch(error => {
        console.error('Error loading session bill:', error);
        if (btn) { btn.disabled = false; btn.textContent = 'ZAPLATIT VŠE'; }
    });
}

function paySplit(table_number, btn) {
    lastSplitTable = table_number;
    fetch(API + '?action=session-bill&table_number=' + table_number)
    .then(r=>r.json())
    .then(res => {
        const items = res.data.items || [];
        const deliveredUnpaid = items.filter(i => i.status === 'delivered' && i.status !== 'paid' && i.status !== 'cancelled');
        lastSplitGroups = groupBillItems(deliveredUnpaid);
        showSplitModal(lastSplitGroups);
    });
}

function updateModalTotal() {
    let sum = 0;
    Array.from(document.querySelectorAll('.split-qty')).forEach((input) => {
        const groupIdx = parseInt(input.dataset.groupidx, 10);
        const val = parseInt(input.value, 10) || 0;
        sum += val * lastSplitGroups[groupIdx].unit_price;
    });
    Array.from(document.querySelectorAll('.split-check')).forEach((cb) => {
        if (cb.checked) {
            const groupIdx = parseInt(cb.dataset.groupidx, 10);
            sum += lastSplitGroups[groupIdx].unit_price;
        }
    });
    
    // ✅ SPOČÍTEJ CELKOVOU ČÁSTKU OBJEDNÁVKY
    const totalOrderAmount = lastSplitGroups.reduce((total, item) => total + (item.qty * item.unit_price), 0);
    const remaining = totalOrderAmount - sum;
    
    // ✅ NOVÉ ZOBRAZENÍ S ZVÝRAZNĚNÍM
    document.getElementById('modal-total').innerHTML = `
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
            <div style="
                background: #e8f5e8;
                border: 2px solid #4CAF50;
                border-radius: 8px;
                padding: 12px;
                text-align: center;
            ">
                <div style="font-size: 14px; color: #2E7D32; margin-bottom: 5px;">💳 Nyní zaplatit</div>
                <div style="font-size: 18px; font-weight: bold; color: #2E7D32;">${sum} Kč</div>
            </div>
            
            <div style="
                background: ${remaining > 0 ? '#ffebee' : '#e8f5e8'};
                border: 2px solid ${remaining > 0 ? '#f44336' : '#4CAF50'};
                border-radius: 8px;
                padding: 12px;
                text-align: center;
                ${remaining > 0 ? 'box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);' : ''}
            ">
                <div style="font-size: 14px; color: ${remaining > 0 ? '#c62828' : '#2E7D32'}; margin-bottom: 5px;">
                    ${remaining > 0 ? '⚠️ Zbývá zaplatit' : '✅ Kompletně zaplaceno'}
                </div>
                <div style="
                    font-size: ${remaining > 0 ? '22px' : '18px'}; 
                    font-weight: bold; 
                    color: ${remaining > 0 ? '#c62828' : '#2E7D32'};
                    ${remaining > 0 ? 'text-shadow: 1px 1px 2px rgba(0,0,0,0.1);' : ''}
                ">
                    ${remaining} Kč
                </div>
            </div>
        </div>
        
        ${remaining > 0 ? `
            <div style="
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                border-radius: 5px;
                padding: 8px;
                margin-top: 10px;
                text-align: center;
                font-size: 12px;
                color: #856404;
            ">
                💡 Po této platbě zůstane stůl aktivní pro doplacení zbývající částky
            </div>
        ` : `
            <div style="
                background: #d4edda;
                border: 1px solid #c3e6cb;
                border-radius: 5px;
                padding: 8px;
                margin-top: 10px;
                text-align: center;
                font-size: 12px;
                color: #155724;
            ">
                ✅ Stůl bude po této platbě automaticky označen jako volný
            </div>
        `}
    `;
}

function confirmSplitPay() {
    const splitPaymentMethod = document.querySelector('input[name="split-payment-method"]:checked').value;
    console.log('Způsob platby (rozdělení):', splitPaymentMethod);
    
    const payArr = [];
    Array.from(document.querySelectorAll('.split-qty')).forEach((input) => {
        const groupIdx = parseInt(input.dataset.groupidx, 10);
        let qty = parseInt(input.value, 10) || 0;
        let group = lastSplitGroups[groupIdx];
        let count = 0;
        for (const id of group.ids) {
            if (count < qty) {
                payArr.push({ id: id, quantity: 1 });
                count++;
            }
        }
    });
    Array.from(document.querySelectorAll('.split-check')).forEach((cb) => {
        if (cb.checked) {
            const groupIdx = parseInt(cb.dataset.groupidx, 10);
            let group = lastSplitGroups[groupIdx];
            payArr.push({ id: group.ids[0], quantity: 1 });
        }
    });

    const payFiltered = payArr.filter(x => x.quantity > 0);
    if (payFiltered.length === 0) return;

    // ✅ KONTROLA: Zjisti jestli se platí všechny zbývající položky
    fetch(API + '?action=session-bill&table_number=' + lastSplitTable)
    .then(r=>r.json())
    .then(sessionRes => {
        const allItems = sessionRes.data.items || [];
        const activeItems = allItems.filter(i => i.status !== 'cancelled');
        const unpaidItems = activeItems.filter(i => i.status !== 'paid');
        
        // Spočítej kolik položek se právě platí
        const payingItemsCount = payFiltered.reduce((sum, item) => sum + item.quantity, 0);
        const willBeFullyPaid = payingItemsCount >= unpaidItems.length;
        
        console.log('Split payment - Will be fully paid:', willBeFullyPaid, 'Paying:', payingItemsCount, 'Unpaid:', unpaidItems.length);

        // ✅ PŘIDEJ payment_method a close_session flag
        fetch(API + '?action=pay-items', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                items: payFiltered,
                payment_method: splitPaymentMethod,
                table_number: lastSplitTable,  // ✅ PŘIDÁNO
                close_session: willBeFullyPaid  // ✅ PŘIDÁNO
            })
        })
        .then(r=>r.json())
        .then(res => {
            console.log('Split pay response:', res);
            closeModal();
            loadTables();
        });
    });
}

function changeQuantity(groupIdx, change) {
    const input = document.querySelector(`.split-qty[data-groupidx="${groupIdx}"]`);
    let qty = parseInt(input.value, 10) || 0;
    qty += change;
    if (qty < 0) qty = 0;
    if (qty > lastSplitGroups[groupIdx].qty) qty = lastSplitGroups[groupIdx].qty;
    input.value = qty;
    updateModalTotal();
}

function updateLastUpdateTime() {
    document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString();
}

loadTables();
setInterval(loadTables, 7000);

// Globální funkce
window.closeModal = closeModal;
window.confirmSplitPay = confirmSplitPay;
window.updateModalTotal = updateModalTotal;
window.showConfirmModal = showConfirmModal;
window.confirmPayAll = confirmPayAll;
window.changeQuantity = changeQuantity;
window.paySplit = paySplit;
window.showTableChangeModal = showTableChangeModal;
window.closeTableChangeModal = closeTableChangeModal;
window.selectNewTable = selectNewTable;
window.confirmTableChange = confirmTableChange;
// Proměnné pro zrušení položky
let cancelItemIds = [];
let cancelItemData = {};

// Zobrazit modal pro zrušení položky - přijímá objekt místo jednotlivých parametrů
function showCancelItemModal(modalData) {
    console.log('Modal data:', modalData); // Debug
    
    cancelItemData = {
        name: modalData.item_name,
        maxQty: modalData.qty,
        unitPrice: modalData.unit_price,
        ids: modalData.ids,
        allItems: modalData.all_items
    };
    
    // Spočítej, kolik položek lze zrušit (jen nezaplacené)
    const cancellableItems = cancelItemData.allItems.filter(item => 
        item.status !== 'paid' && item.status !== 'cancelled'
    );
    
    const cancellableQty = cancellableItems.reduce((sum, item) => 
        sum + parseInt(item.quantity), 0
    );
    
    if (cancellableQty === 0) {
        alert('Žádné položky nelze zrušit - všechny jsou už zaplacené nebo zrušené.');
        return;
    }
    
        // Aktualizuj data pro zrušitelné množství
    cancelItemData.maxQty = cancellableQty;
    cancelItemData.cancellableItems = cancellableItems;
    
    document.getElementById('cancelItemName').textContent = modalData.item_name;
    document.getElementById('cancelItemQty').textContent = cancellableQty + '×';
    document.getElementById('cancelItemUnitPrice').textContent = modalData.unit_price + ' Kč';
    
    // Zobraz statusy všech položek
    const statusDisplay = renderStatusBreakdown(cancelItemData.allItems);
    document.getElementById('cancelItemStatus').innerHTML = statusDisplay;
    
    // Nastavit množství
    const quantityInput = document.getElementById('cancelQuantityInput');
    quantityInput.value = Math.min(1, cancellableQty);
    quantityInput.max = cancellableQty;
    
    // Aktualizovat tlačítka
    updateCancelQuantityButtons();
    updateCancelTotalPrice();
    
    // Nastavit varování
    const warningText = document.getElementById('cancelWarningText');
    warningText.textContent = 'Pozor! Tato akce je nevratná!';
    
    document.getElementById('cancelItemModal').style.display = 'flex';
}

function renderStatusBreakdown(allItems) {
    const statusGroups = {};
    allItems.forEach(item => {
        const status = item.status;
        if (!statusGroups[status]) {
            statusGroups[status] = 0;
        }
        statusGroups[status] += parseInt(item.quantity);
    });
    
    return Object.entries(statusGroups)
        .map(([status, count]) => 
            `<span class="status-indicator ${status}">${count}× ${getStatusText(status)}</span>`
        ).join(' ');
}

// Změnit množství ke zrušení
function changeCancelQuantity(change) {
    const input = document.getElementById('cancelQuantityInput');
    let newValue = parseInt(input.value) + change;
    
    if (newValue < 1) newValue = 1;
    if (newValue > cancelItemData.maxQty) newValue = cancelItemData.maxQty;
    
    input.value = newValue;
    updateCancelQuantityButtons();
    updateCancelTotalPrice();
}

function closeCancelItemModal() {
    document.getElementById('cancelItemModal').style.display = 'none';
    cancelItemData = {};
    document.getElementById('cancelReason').value = '';
}

// Aktualizovat tlačítka množství
function updateCancelQuantityButtons() {
    const input = document.getElementById('cancelQuantityInput');
    const decreaseBtn = document.getElementById('decreaseBtn');
    const increaseBtn = document.getElementById('increaseBtn');
    
    const currentValue = parseInt(input.value);
    
    decreaseBtn.disabled = currentValue <= 1;
    increaseBtn.disabled = currentValue >= cancelItemData.maxQty;
}

// Aktualizovat celkovou cenu
function updateCancelTotalPrice() {
    const quantity = parseInt(document.getElementById('cancelQuantityInput').value);
    const totalPrice = quantity * cancelItemData.unitPrice;
    document.getElementById('cancelTotalPrice').textContent = totalPrice + ' Kč';
}

// Potvrdit zrušení položky
async function confirmCancelItem() {
    if (!cancelItemData.cancellableItems || cancelItemData.cancellableItems.length === 0) {
        alert('Žádné položky k zrušení!');
        return;
    }
    
    const quantity = parseInt(document.getElementById('cancelQuantityInput').value);
    const reason = document.getElementById('cancelReason').value.trim();
    
    if (quantity < 1 || quantity > cancelItemData.maxQty) {
        alert('Neplatné množství!');
        return;
    }
    
    const confirmBtn = document.querySelector('.cancel-item-btn.confirm');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Ruším...';
    
    try {
        // Vyber ID položek k zrušení
        const itemsToCancel = [];
        let remainingToCancel = quantity;
        
        for (const item of cancelItemData.cancellableItems) {
            if (remainingToCancel <= 0) break;
            
            const itemQty = parseInt(item.quantity);
            const cancelFromThis = Math.min(remainingToCancel, itemQty);
            
            itemsToCancel.push({
                id: item.id,
                cancel_qty: cancelFromThis,
                current_qty: itemQty
            });
            
            remainingToCancel -= cancelFromThis;
        }
        
        console.log('Items to cancel:', itemsToCancel); // Debug
        
        const response = await fetch(`${API}?action=cancel-items`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                items_to_cancel: itemsToCancel,
                total_cancel_quantity: quantity,
                reason: reason,
                item_name: cancelItemData.name,
                unit_price: cancelItemData.unitPrice
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`Úspěšně zrušeno ${quantity}× ${cancelItemData.name}`);
            
            // Zavři modal
            document.getElementById('cancelItemModal').style.display = 'none';
            cancelItemData = {};
            document.getElementById('cancelReason').value = '';
            
            loadTables(); // Reload data
        } else {
            alert('Chyba při rušení položky: ' + (result.error || 'Neznámá chyba'));
        }
    } catch (error) {
        console.error('Chyba při rušení položky:', error);
        alert('Chyba při rušení položky: ' + error.message);
    } finally {
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'ZRUŠIT POLOŽKU';
    }
}

function getStatusText(status) {
    switch(status) {
        case 'pending': return 'Čeká';
        case 'preparing': return 'Připravuje se';
        case 'ready': return 'Hotovo';
        case 'delivered': return 'Doručeno';
        case 'paid': return 'Zaplaceno';
        case 'cancelled': return 'Zrušeno';
        default: return status;
    }
}

// Přidej globální funkce
window.renderStatusBreakdown = renderStatusBreakdown;
window.showCancelItemModal = showCancelItemModal;
window.closeCancelItemModal = closeCancelItemModal;
window.confirmCancelItem = confirmCancelItem;
window.changeCancelQuantity = changeCancelQuantity;
window.getStatusText = getStatusText;
window.getBillItemClass = getBillItemClass;

</script>

<!-- Modal pro zrušení položky -->
<div id="cancelItemModal" class="cancel-item-modal">
    <div class="cancel-item-content">
        <div class="cancel-item-title">⚠️ Zrušit položku</div>
        <div class="cancel-item-info">
            <p><strong>Položka:</strong> <span id="cancelItemName"></span></p>
            <p><strong>Dostupné množství:</strong> <span id="cancelItemQty"></span></p>
            <p><strong>Cena za kus:</strong> <span id="cancelItemUnitPrice"></span></p>
            <p><strong>Status:</strong> <span id="cancelItemStatus"></span></p>
        </div>
        
        <div class="quantity-selector">
            <label>Množství ke zrušení:</label>
            <div class="quantity-controls">
                <button type="button" onclick="changeCancelQuantity(-1)" id="decreaseBtn">-</button>
                <input type="number" id="cancelQuantityInput" value="1" min="1" readonly>
                <button type="button" onclick="changeCancelQuantity(1)" id="increaseBtn">+</button>
            </div>
        </div>
        
        <div class="cancel-reason">
            <label>Důvod zrušení (volitelné):</label>
            <input type="text" id="cancelReason" placeholder="např. špatně objednáno, změna objednávky...">
        </div>
        
        <div style="color: #dc3545; font-weight: bold; text-align: center;">
            <span id="cancelWarningText">Tato akce je nevratná!</span><br>
            <span>Celková částka ke zrušení: <span id="cancelTotalPrice">0 Kč</span></span>
        </div>
        
        <div class="cancel-item-buttons">
            <button class="cancel-item-btn confirm" onclick="confirmCancelItem()">
                ZRUŠIT POLOŽKU
            </button>
            <button class="cancel-item-btn cancel" onclick="closeCancelItemModal()">
                ZPĚT
            </button>
        </div>
    </div>
</div>

</body>
</html>