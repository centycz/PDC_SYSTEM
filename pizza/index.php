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
    <title>Obsluha - Restaurace</title>
   <style>
        /* Navigation header styles */
        .nav-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(255,255,255,0.15);
            padding: 10px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }
        
        .nav-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .back-btn {
            background: rgba(255,255,255,0.9);
            color: #ee5a24;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .back-btn:hover {
            background: white;
            text-decoration: none;
            color: #ee5a24;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .user-info {
            color: white;
            font-size: 14px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
        }
        
        .user-info strong {
            font-weight: 600;
        }
        
        /* Všechny CSS styly zůstávají stejné... */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
            padding: 15px;
        }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 {
            color: white;
            font-size: 2.2em;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        /* KONTEJNER PRO PERGOLY - GRID LAYOUT */
        .pergolas-container {
            display: grid;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        /* RESPONZIVNÍ GRID PRO PERGOLY */
        @media (min-width: 769px) and (max-width: 1024px) {
            /* iPad - 2 sloupce */
            .pergolas-container {
                grid-template-columns: 1fr 1fr;
                gap: 12px;
            }
        }
        
        @media (min-width: 1025px) {
            /* Desktop - 2-3 sloupce podle šířky */
            .pergolas-container {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 15px;
            }
        }
        
        @media (max-width: 768px) {
            /* Mobil - 1 sloupec */
            .pergolas-container {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }
        
        /* PERGOLA SEKCE */
        .pergola-section {
            break-inside: avoid;
            page-break-inside: avoid;
            width: 100%;
            display: flex;
            flex-direction: column;
        }
        
        /* NADPISY PERGOL */
        .location-title {
            color: #ee5a24;
            font-size: 1.1em;
            font-weight: bold;
            margin: 0 0 8px 0;
            padding: 8px 15px;
            background: linear-gradient(135deg, rgba(238, 90, 36, 0.1), rgba(238, 90, 36, 0.05));
            border-left: 4px solid #ee5a24;
            border-radius: 8px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            box-sizing: border-box;
        }
        
        .pergola-title-main {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pergola-count {
            font-size: 0.75em;
            opacity: 0.7;
            background: rgba(238,90,36,0.1);
            padding: 2px 6px;
            border-radius: 10px;
        }
        
        /* TABLES GRID */
        .tables-grid {
            display: grid;
            gap: 8px;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            width: 100%;
        }
        
        /* STOLY */
        .table-button {
            aspect-ratio: 1;
            padding: 8px;
            border: 2px solid transparent;
            border-radius: 12px;
            background: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85em;
            font-weight: 600;
            color: #333;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 4px;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            min-height: 80px;
        }
        
        .table-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
            border-color: #ee5a24;
        }
        
        .table-button.selected {
            border-color: #ff6b6b;
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            box-shadow: 0 6px 16px rgba(238, 90, 36, 0.3);
        }
        
        .table-button.occupied {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            border-color: #e67e22;
        }
        
        .table-code {
            font-size: 1.1em;
            font-weight: bold;
        }
        
        .table-status {
            font-size: 0.7em;
            opacity: 0.8;
        }
        
        .table-order-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7em;
            font-weight: bold;
        }
        
        /* SPRÁVA OBJEDNÁVEK */
        .management-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .section-title {
            font-size: 1.4em;
            color: #333;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-info {
            background: linear-gradient(135deg, #ff6b6b, #ee5a24);
            color: white;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        /* MENU A KOŠÍK */
        .order-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .order-section {
                grid-template-columns: 1fr;
                gap: 15px;
            }
        }
        
        .menu-section, .cart-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .menu-categories {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 8px 16px;
            border: 2px solid #ddd;
            background: white;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
            font-weight: 500;
        }
        
        .category-btn.active {
            background: #ff6b6b;
            color: white;
            border-color: #ff6b6b;
        }
        
        .menu-items {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .menu-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s ease;
        }
        
        .menu-item:hover {
            background: #f8f9fa;
        }
        
        .menu-item-name {
            font-weight: 500;
            color: #333;
        }
        
        .menu-item-price {
            color: #ff6b6b;
            font-weight: bold;
        }
        
        .add-item-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.8em;
            transition: all 0.2s ease;
        }
        
        .add-item-btn:hover {
            background: #218838;
            transform: translateY(-1px);
        }
        
        /* KOŠÍK */
        .cart-items {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            background: #f8f9fa;
            margin-bottom: 5px;
            border-radius: 5px;
        }
        
        .cart-item-info {
            flex: 1;
        }
        
        .cart-item-name {
            font-weight: 500;
            color: #333;
        }
        
        .cart-item-price {
            color: #666;
            font-size: 0.9em;
        }
        
        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-btn {
            background: #ee5a24;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
        }
        
        .quantity-btn:hover {
            background: #dc4928;
        }
        
        .cart-total {
            font-size: 1.2em;
            font-weight: bold;
            color: #ff6b6b;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .order-btn {
            width: 100%;
            padding: 12px;
            background: #ff6b6b;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .order-btn:hover {
            background: #ee5a24;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(238, 90, 36, 0.3);
        }
        
        .order-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        /* AKTIVNÍ OBJEDNÁVKY */
        .orders-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .order-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #ff6b6b;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .order-id {
            font-weight: bold;
            color: #333;
        }
        
        .order-time {
            color: #666;
            font-size: 0.9em;
        }
        
        .order-items-list {
            margin-bottom: 10px;
        }
        
        .order-item-detail {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
            font-size: 0.9em;
        }
        
        .order-total {
            font-weight: bold;
            color: #ff6b6b;
            text-align: right;
        }
        
        .order-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s ease;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-success:hover {
            background: #218838;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        /* RYCHLÉ AKCE */
        .quick-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .quick-action-btn {
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 25px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .quick-action-btn:hover {
            background: rgba(255,255,255,0.3);
            border-color: rgba(255,255,255,0.5);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        
        /* EMPLOYEE INPUT */
        .employee-input {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .employee-dialog {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            width: 90%;
            max-width: 400px;
            text-align: center;
        }
        
        .employee-dialog h2 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .employee-dialog input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1.1em;
            margin-bottom: 20px;
            box-sizing: border-box;
        }
        
        .employee-dialog input:focus {
            outline: none;
            border-color: #ff6b6b;
        }
        
        .employee-dialog button {
            width: 100%;
            padding: 12px;
            background: #ff6b6b;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .employee-dialog button:hover {
            background: #ee5a24;
        }
        
        /* EMPTY STATES */
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 3em;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        /* LOADING STATES */
        .loading {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .loading::after {
            content: '...';
            animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
            0% { content: '...'; }
            33% { content: '.'; }
            66% { content: '..'; }
            100% { content: '...'; }
        }
        
        /* RESPONSIVE ADJUSTMENTS */
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .header h1 {
                font-size: 1.8em;
            }
            
            .management-section,
            .menu-section,
            .cart-section,
            .orders-section {
                padding: 15px;
            }
            
            .quick-actions {
                gap: 8px;
            }
            
            .quick-action-btn {
                padding: 8px 16px;
                font-size: 0.9em;
            }
        }
        
        /* NOTIFIKACE */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
        }
        
        .notification.success {
            background: #28a745;
        }
        
        .notification.error {
            background: #dc3545;
        }
        
        .notification.warning {
            background: #ffc107;
            color: #212529;
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div class="nav-header">
        <div class="nav-left">
            <a href="login.php" class="back-btn">← ZPĚT NA PŘIHLÁŠENÍ</a>
        </div>
        <div class="user-info">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            <button onclick="logout()" class="back-btn" style="margin-left: 15px;">🚪 Odhlásit se</button>
        </div>
    </div>

    <div class="header">
        <h1>🍽️ Obsluha restaurace</h1>
    </div>

    <!-- Rychlé akce -->
    <div class="quick-actions">
        <a href="kitchen.php" class="quick-action-btn">👨‍🍳 Kuchyň</a>
        <a href="pasta-kuchyn.php" class="quick-action-btn">🍝 Pasta kuchyň</a>
        <a href="bar.php" class="quick-action-btn">🍺 Bar</a>
        <a href="serving.php" class="quick-action-btn">🍽️ Servírování</a>
        <a href="billing.php" class="quick-action-btn">💰 Účtování</a>
        <a href="orders_system.php" class="quick-action-btn">📋 Správa objednávek</a>
        <a href="status_dashboard.php" class="quick-action-btn">📊 Status dashboard</a>
        <a href="shifts_system.php" class="quick-action-btn">⏰ Směny</a>
        <a href="reservations.php" class="quick-action-btn">📅 Rezervace</a>
        <a href="historie.php" class="quick-action-btn">📋 Historie</a>
        <a href="data.php" class="quick-action-btn">📊 Statistiky</a>
        <a href="admin.php" class="quick-action-btn">⚙️ Admin panel</a>
    </div>

    <!-- Pergoly container -->
    <div class="pergolas-container" id="pergolasContainer">
        <!-- Pergoly se načtou dynamicky -->
    </div>

    <!-- Správa objednávek -->
    <div class="management-section" id="managementSection" style="display: none;">
        <div class="section-title">
            🛎️ Správa objednávek
        </div>
        <div class="table-info" id="tableInfo">
            Není vybrán stůl
        </div>

        <div class="order-section">
            <!-- Menu -->
            <div class="menu-section">
                <div class="section-title">📋 Menu</div>
                <div class="menu-categories" id="menuCategories">
                    <!-- Kategorie se načtou dynamicky -->
                </div>
                <div class="menu-items" id="menuItems">
                    <!-- Menu položky se načtou dynamicky -->
                </div>
            </div>

            <!-- Košík -->
            <div class="cart-section">
                <div class="section-title">🛒 Košík</div>
                <div class="cart-items" id="cartItems">
                    <!-- Košík se načte dynamicky -->
                </div>
                <div class="cart-total" id="cartTotal">
                    Celkem: 0 Kč
                </div>
                <button class="order-btn" id="orderBtn" onclick="submitOrder()" disabled>
                    Odeslat objednávku
                </button>
            </div>
        </div>
    </div>

    <!-- Aktivní objednávky -->
    <div class="orders-section">
        <div class="section-title">📋 Aktivní objednávky pro vybraný stůl</div>
        <div id="activeOrders">
            <!-- Objednávky se načtou dynamicky -->
        </div>
    </div>

    <!-- Employee input modal -->
    <div class="employee-input" id="employeeModal">
        <div class="employee-dialog">
            <h2>👤 Identifikace obsluhy</h2>
            <input type="text" id="employeeNameInput" placeholder="Zadejte vaše jméno..." maxlength="50" />
            <button onclick="setEmployeeName()">Potvrdit</button>
        </div>
    </div>

    <script>
        // Globální proměnné
        let selectedTable = null;
        let menuData = [];
        let cart = [];
        let employeeName = localStorage.getItem('employeeName') || '';

        // API endpoints
        const API_BASE = 'api/restaurant-api.php';

        // Funkce pro zobrazení notifikace
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);

            setTimeout(() => notification.classList.add('show'), 100);
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => document.body.removeChild(notification), 300);
            }, 3000);
        }

        // Kontrola jména obsluhy
        function checkEmployeeName() {
            if (!employeeName) {
                document.getElementById('employeeModal').style.display = 'flex';
                document.getElementById('employeeNameInput').focus();
            } else {
                document.getElementById('employeeModal').style.display = 'none';
            }
        }

        // Nastavení jména obsluhy
        function setEmployeeName() {
            const input = document.getElementById('employeeNameInput');
            const name = input.value.trim();
            
            if (name) {
                employeeName = name;
                localStorage.setItem('employeeName', name);
                document.getElementById('employeeModal').style.display = 'none';
                showNotification(`Vítejte, ${name}!`, 'success');
            } else {
                showNotification('Prosím zadejte vaše jméno', 'error');
            }
        }

        // Enter key pro employee modal
        document.getElementById('employeeNameInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                setEmployeeName();
            }
        });

        // Logout funkce
        function logout() {
            if (confirm('Opravdu se chcete odhlásit?')) {
                localStorage.removeItem('employeeName');
                window.location.href = 'login.php?logout=1';
            }
        }

        // Načtení stolů
        async function refreshTables() {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_tables' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    renderTables(data.data);
                } else {
                    console.error('Chyba při načítání stolů:', data.message);
                }
            } catch (error) {
                console.error('Chyba API:', error);
            }
        }

        // Vykreslení stolů podle pergol
        function renderTables(tables) {
            const container = document.getElementById('pergolasContainer');
            
            // Seskupení stolů podle lokace
            const tablesByLocation = {};
            tables.forEach(table => {
                const location = table.location || 'Ostatní';
                if (!tablesByLocation[location]) {
                    tablesByLocation[location] = [];
                }
                tablesByLocation[location].push(table);
            });

            // Vykreslení pergol
            container.innerHTML = '';
            Object.entries(tablesByLocation).forEach(([location, locationTables]) => {
                const pergola = document.createElement('div');
                pergola.className = 'pergola-section';
                
                pergola.innerHTML = `
                    <div class="location-title">
                        <div class="pergola-title-main">
                            <span>📍 ${location}</span>
                        </div>
                        <span class="pergola-count">${locationTables.length} stolů</span>
                    </div>
                    <div class="tables-grid">
                        ${locationTables.map(table => {
                            const isSelected = selectedTable === table.table_code;
                            const isOccupied = table.active_orders > 0;
                            
                            return `
                                <div class="table-button ${isSelected ? 'selected' : ''} ${isOccupied ? 'occupied' : ''}" 
                                     onclick="selectTable('${table.table_code}')">
                                    <div class="table-code">${table.table_code}</div>
                                    <div class="table-status">
                                        ${isOccupied ? '🔴 Obsazeno' : '🟢 Volný'}
                                    </div>
                                    ${table.active_orders > 0 ? `<div class="table-order-count">${table.active_orders}</div>` : ''}
                                </div>
                            `;
                        }).join('')}
                    </div>
                `;
                
                container.appendChild(pergola);
            });
        }

        // Výběr stolu
        function selectTable(tableCode) {
            selectedTable = tableCode;
            
            // Aktualizace UI
            document.querySelectorAll('.table-button').forEach(btn => {
                btn.classList.remove('selected');
            });
            
            event.target.closest('.table-button').classList.add('selected');
            
            // Zobrazení správy objednávek
            const managementSection = document.getElementById('managementSection');
            managementSection.style.display = 'block';
            
            // Aktualizace info o stolu
            document.getElementById('tableInfo').textContent = `Vybraný stůl: ${tableCode}`;
            
            // Načtení objednávek pro stůl
            refreshOrders();
            
            // Scroll k management sekci
            managementSection.scrollIntoView({ behavior: 'smooth' });
        }

        // Načtení menu
        async function refreshMenu() {
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_menu' })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    menuData = data.data;
                    renderMenuCategories();
                    renderMenuItems();
                } else {
                    console.error('Chyba při načítání menu:', data.message);
                }
            } catch (error) {
                console.error('Chyba API:', error);
            }
        }

        // Vykreslení kategorií menu
        function renderMenuCategories() {
            const categories = [...new Set(menuData.map(item => item.kategorie))];
            const container = document.getElementById('menuCategories');
            
            container.innerHTML = `
                <div class="category-btn active" onclick="filterMenu('all')">Vše</div>
                ${categories.map(cat => `
                    <div class="category-btn" onclick="filterMenu('${cat}')">${cat}</div>
                `).join('')}
            `;
        }

        // Filtrování menu podle kategorie
        function filterMenu(category) {
            // Aktualizace aktivní kategorie
            document.querySelectorAll('.category-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Vykreslení filtrovaných položek
            renderMenuItems(category);
        }

        // Vykreslení položek menu
        function renderMenuItems(category = 'all') {
            const container = document.getElementById('menuItems');
            const filteredItems = category === 'all' 
                ? menuData 
                : menuData.filter(item => item.kategorie === category);
            
            container.innerHTML = filteredItems.map(item => `
                <div class="menu-item">
                    <div>
                        <div class="menu-item-name">${item.nazev}</div>
                        <div class="menu-item-price">${item.cena} Kč</div>
                    </div>
                    <button class="add-item-btn" onclick="addToCart(${item.id})">
                        + Přidat
                    </button>
                </div>
            `).join('');
        }

        // Přidání do košíku
        function addToCart(itemId) {
            const item = menuData.find(i => i.id == itemId);
            if (!item) return;
            
            const existingItem = cart.find(c => c.id == itemId);
            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({ ...item, quantity: 1 });
            }
            
            renderCart();
            showNotification(`${item.nazev} přidáno do košíku`, 'success');
        }

        // Vykreslení košíku
        function renderCart() {
            const container = document.getElementById('cartItems');
            const totalContainer = document.getElementById('cartTotal');
            const orderBtn = document.getElementById('orderBtn');
            
            if (cart.length === 0) {
                container.innerHTML = '<div class="empty-state">Košík je prázdný</div>';
                totalContainer.textContent = 'Celkem: 0 Kč';
                orderBtn.disabled = true;
                return;
            }
            
            const total = cart.reduce((sum, item) => sum + (item.cena * item.quantity), 0);
            
            container.innerHTML = cart.map(item => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.nazev}</div>
                        <div class="cart-item-price">${item.cena} Kč × ${item.quantity}</div>
                    </div>
                    <div class="cart-item-controls">
                        <button class="quantity-btn" onclick="updateCartQuantity(${item.id}, -1)">-</button>
                        <span>${item.quantity}</span>
                        <button class="quantity-btn" onclick="updateCartQuantity(${item.id}, 1)">+</button>
                    </div>
                </div>
            `).join('');
            
            totalContainer.textContent = `Celkem: ${total} Kč`;
            orderBtn.disabled = false;
        }

        // Aktualizace množství v košíku
        function updateCartQuantity(itemId, change) {
            const item = cart.find(c => c.id == itemId);
            if (!item) return;
            
            item.quantity += change;
            
            if (item.quantity <= 0) {
                cart = cart.filter(c => c.id != itemId);
            }
            
            renderCart();
        }

        // Odeslání objednávky
        async function submitOrder() {
            if (!selectedTable || cart.length === 0 || !employeeName) {
                showNotification('Nejprve vyberte stůl, přidejte položky a nastavte jméno obsluhy', 'error');
                return;
            }
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create_order',
                        table_code: selectedTable,
                        items: cart,
                        employee_name: employeeName
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Objednávka byla úspěšně odeslána!', 'success');
                    cart = [];
                    renderCart();
                    refreshTables();
                    refreshOrders();
                } else {
                    showNotification('Chyba při odesílání objednávky: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Chyba API:', error);
                showNotification('Chyba spojení se serverem', 'error');
            }
        }

        // Načtení objednávek pro stůl
        async function refreshOrders() {
            if (!selectedTable) return;
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'get_table_orders',
                        table_code: selectedTable 
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    renderOrders(data.data);
                } else {
                    console.error('Chyba při načítání objednávek:', data.message);
                }
            } catch (error) {
                console.error('Chyba API:', error);
            }
        }

        // Vykreslení objednávek
        function renderOrders(orders) {
            const container = document.getElementById('activeOrders');
            
            if (!orders || orders.length === 0) {
                container.innerHTML = '<div class="empty-state">Žádné aktivní objednávky</div>';
                return;
            }
            
            container.innerHTML = orders.map(order => {
                const items = JSON.parse(order.items || '[]');
                const total = items.reduce((sum, item) => sum + (item.cena * item.quantity), 0);
                
                return `
                    <div class="order-item">
                        <div class="order-header">
                            <div class="order-id">Objednávka #${order.id}</div>
                            <div class="order-time">${new Date(order.created_at).toLocaleString('cs-CZ')}</div>
                        </div>
                        <div class="order-items-list">
                            ${items.map(item => `
                                <div class="order-item-detail">
                                    <span>${item.nazev} × ${item.quantity}</span>
                                    <span>${item.cena * item.quantity} Kč</span>
                                </div>
                            `).join('')}
                        </div>
                        <div class="order-total">Celkem: ${total} Kč</div>
                        <div class="order-actions">
                            <button class="btn btn-success" onclick="completeOrder(${order.id})">
                                ✅ Hotovo
                            </button>
                            <button class="btn btn-warning" onclick="printOrder(${order.id})">
                                🖨️ Tisk
                            </button>
                            <button class="btn btn-danger" onclick="cancelOrder(${order.id})">
                                ❌ Zrušit
                            </button>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Dokončení objednávky
        async function completeOrder(orderId) {
            if (!confirm('Označit objednávku jako dokončenou?')) return;
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'complete_order',
                        order_id: orderId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Objednávka dokončena!', 'success');
                    refreshTables();
                    refreshOrders();
                } else {
                    showNotification('Chyba: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Chyba API:', error);
                showNotification('Chyba spojení se serverem', 'error');
            }
        }

        // Tisk objednávky
        function printOrder(orderId) {
            window.open(`print-order.php?id=${orderId}`, '_blank');
        }

        // Zrušení objednávky
        async function cancelOrder(orderId) {
            if (!confirm('Opravdu chcete zrušit tuto objednávku?')) return;
            
            try {
                const response = await fetch(API_BASE, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'cancel_order',
                        order_id: orderId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showNotification('Objednávka zrušena!', 'success');
                    refreshTables();
                    refreshOrders();
                } else {
                    showNotification('Chyba: ' + data.message, 'error');
                }
            } catch (error) {
                console.error('Chyba API:', error);
                showNotification('Chyba spojení se serverem', 'error');
            }
        }

        // Hlavní smyčka
        function loop() {
            refreshTables();
            if (selectedTable) {
                refreshOrders();
            }
            setTimeout(loop, 6000);
        }

        // Global functions pro onclick handlery
        window.logout = logout;

        // Inicializace
        checkEmployeeName();
        refreshTables();
        refreshMenu();
        renderCart();
        refreshOrders();
        setTimeout(loop, 6000);
    </script>
</body>
</html>