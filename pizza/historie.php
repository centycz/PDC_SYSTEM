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
    <title>Historie objednávek</title>
    <style>
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            padding: 20px; 
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
        }
        .header h1 { 
            color: white; 
            font-size: 2.5em; 
            margin-bottom: 10px; 
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .nav-links { 
            display: flex; 
            justify-content: center; 
            gap: 15px; 
            margin-bottom: 20px; 
            flex-wrap: wrap;
        }
        .nav-link { 
            padding: 10px 20px; 
            background: rgba(255,255,255,0.2); 
            color: white; 
            text-decoration: none; 
            border-radius: 25px; 
            font-weight: 500; 
            transition: all 0.3s ease;
        }
        .nav-link:hover { 
            background: rgba(255,255,255,0.3); 
            transform: translateY(-2px);
        }
        .nav-link.active { 
            background: white; 
            color: #667eea;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 15px; 
            padding: 30px; 
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        .filters {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-weight: bold;
            color: #333;
        }
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .filter-group button {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .filter-group button:hover {
            background: #5a6fd8;
        }
        .section-title { 
            font-size: 1.6em; 
            color: #667eea; 
            margin: 25px 0 15px 0; 
            font-weight: bold;
        }
        .history-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px;
        }
        .history-card { 
            border: 2px solid #667eea; 
            border-radius: 15px; 
            padding: 20px; 
            background: #fff; 
            box-shadow: 0 5px 15px rgba(0,0,0,0.07); 
            transition: transform 0.3s ease;
        }
        .history-card:hover {
            transform: translateY(-2px);
        }
        .order-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid #eee;
        }
        .order-id { 
            font-size: 1.4em; 
            font-weight: bold; 
            color: #667eea; 
        }
        .table-info { 
            font-size: 1.2em; 
            font-weight: bold; 
            color: #764ba2; 
        }
        .order-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #666;
        }
        .meta-item {
            display: flex;
            justify-content: space-between;
        }
        .order-total { 
            font-weight: bold; 
            color: #667eea; 
            font-size: 1.3em; 
            text-align: center;
            background: #f8f9ff;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .items-toggle {
            background: #f8f9ff;
            border: 2px solid #667eea;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            width: 100%;
            transition: all 0.3s ease;
        }
        .items-toggle:hover {
            background: #667eea;
            color: white;
        }
        .items-toggle.expanded {
            background: #667eea;
            color: white;
        }
        .items-list {
            display: none;
            margin-top: 15px;
            background: #f8f9ff;
            border-radius: 8px;
            padding: 15px;
        }
        .items-list.show {
            display: block;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e0e6ff;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .item-details {
            flex: 1;
        }
        .item-name {
            font-weight: bold;
            color: #333;
        }
        .item-note {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
            margin-top: 2px;
        }
        .item-qty {
            color: #667eea;
            font-weight: bold;
            margin: 0 10px;
        }
        .item-price {
            font-weight: bold;
            color: #764ba2;
            min-width: 80px;
            text-align: right;
        }
        .last-update { 
            text-align: center; 
            color: #666; 
            font-size: 0.95em; 
            margin-top: 15px;
        }
        .empty-state { 
            text-align: center; 
            padding: 60px 20px; 
            color: #999;
        }
        .empty-state .icon { 
            font-size: 4em; 
            margin-bottom: 20px; 
            display: block;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-size: 1.1em;
        }
        @media (max-width: 768px) { 
            .history-grid { 
                grid-template-columns: 1fr; 
            } 
            .container { 
                padding: 15px; 
            }
            .filters {
                flex-direction: column;
                gap: 15px;
            }
            .order-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(255,255,255,0.15); padding: 10px 20px; border-radius: 10px; margin-bottom: 20px; backdrop-filter: blur(10px);">
        <div style="display: flex; align-items: center; gap: 15px;">
            <a href="login.php" style="background: rgba(255,255,255,0.9); color: #667eea; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; transition: all 0.3s ease;">← ZPĚT NA PŘIHLÁŠENÍ</a>
        </div>
        <div style="color: white; font-size: 14px; text-shadow: 1px 1px 2px rgba(0,0,0,0.3);">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
            <button onclick="logout()" style="background: rgba(255,255,255,0.9); color: #667eea; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; margin-left: 15px; cursor: pointer;">🚪 Odhlásit se</button>
        </div>
    </div>
    
    <div class="header">
        <h1>📋 Historie objednávek</h1>
        <div class="nav-links">
            <a href="index.php" class="nav-link">🏠 Objednávky</a>
            <a href="kitchen.php" class="nav-link">👨‍🍳 Kuchyň</a>
<a href="pasta-kuchyn.php" class="nav-link " id="nav-pasta">🍝 Pasta kuchyň</a>
            <a href="bar.php" class="nav-link">🍺 Bar</a>
            <a href="serving.php" class="nav-link">🍽️ Servírování</a>
            <a href="billing.php" class="nav-link">💰 Účtování</a>
            <a href="historie.php" class="nav-link active">📋 Historie</a>
        </div>
    </div>
    
    <div class="container">
        <div class="section-title">Filtry</div>
        <div class="filters">
            <div class="filter-group">
                <label>Datum od:</label>
                <input type="date" id="dateFrom" value="">
            </div>
            <div class="filter-group">
                <label>Datum do:</label>
                <input type="date" id="dateTo" value="">
            </div>
            <div class="filter-group">
                <label>Stůl:</label>
                <select id="tableFilter">
                    <option value="">Všechny stoly</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Zaměstnanec:</label>
                <select id="employeeFilter">
                    <option value="">Všichni zaměstnanci</option>
                </select>
            </div>
            <div class="filter-group">
                <label>&nbsp;</label>
                <button onclick="loadHistory()">🔍 Hledat</button>
            </div>
        </div>
        
        <div class="section-title">Historie vyúčtovaných objednávek</div>
        <div class="history-grid" id="historyContainer">
            <div class="loading">Načítání historie...</div>
        </div>
        <div class="last-update">Poslední aktualizace: <span id="lastUpdate">-</span></div>
    </div>

    <script>
        const API = 'api/restaurant-api.php';
        let historyData = [];
        
        // Logout function
        function logout() {
            if (confirm('Opravdu se chcete odhlásit?')) {
                window.location.href = 'login.php?logout=1';
            }
        }

        // Inicializace datumů
        function initializeDates() {
            const today = new Date();
            const oneWeekAgo = new Date(today);
            oneWeekAgo.setDate(today.getDate() - 7);
            
            document.getElementById('dateTo').value = formatDateForInput(today);
            document.getElementById('dateFrom').value = formatDateForInput(oneWeekAgo);
        }

        function formatDateForInput(date) {
            return date.toISOString().split('T')[0];
        }

        function formatDateTime(dateStr) {
            const date = new Date(dateStr);
            return date.toLocaleString('cs-CZ');
        }

        function formatCurrency(amount) {
            return new Intl.NumberFormat('cs-CZ', {
                style: 'currency',
                currency: 'CZK',
                minimumFractionDigits: 0
            }).format(amount);
        }

        // Načtení historie objednávek
        async function loadHistory() {
            const container = document.getElementById('historyContainer');
            container.innerHTML = '<div class="loading">Načítání historie...</div>';

            const dateFrom = document.getElementById('dateFrom').value;
            const dateTo = document.getElementById('dateTo').value;
            const tableFilter = document.getElementById('tableFilter').value;
            const employeeFilter = document.getElementById('employeeFilter').value;

            try {
                const params = new URLSearchParams({
                    action: 'order-history',
                    date_from: dateFrom,
                    date_to: dateTo
                });

                if (tableFilter) params.append('table_number', tableFilter);
                if (employeeFilter) params.append('employee_name', employeeFilter);

                const response = await fetch(`${API}?${params}`);
                const result = await response.json();

                if (result.success) {
                    historyData = result.data.orders || [];
                    renderHistory();
                } else {
                    container.innerHTML = `<div class="empty-state">
                        <span class="icon">❌</span>
                        <h3>Chyba při načítání</h3>
                        <p>${result.error || 'Neznámá chyba'}</p>
                    </div>`;
                }
            } catch (error) {
                console.error('Chyba při načítání historie:', error);
                container.innerHTML = `<div class="empty-state">
                    <span class="icon">❌</span>
                    <h3>Chyba připojení</h3>
                    <p>Nelze načíst historii objednávek</p>
                </div>`;
            }

            updateLastUpdateTime();
        }

        // Vykreslení historie
        function renderHistory() {
            const container = document.getElementById('historyContainer');
            
            if (historyData.length === 0) {
                container.innerHTML = `<div class="empty-state">
                    <span class="icon">📋</span>
                    <h3>Žádné objednávky</h3>
                    <p>Pro vybrané filtry nebyly nalezeny žádné objednávky</p>
                </div>`;
                return;
            }

            container.innerHTML = historyData.map(order => {
                const totalAmount = order.items.reduce((sum, item) => 
                    sum + (item.quantity * item.unit_price), 0
                );

                const itemsHtml = order.items.map(item => `
                    <div class="item-row">
                        <div class="item-details">
                            <div class="item-name">${item.item_name}</div>
                            ${item.note ? `<div class="item-note">📝 ${item.note}</div>` : ''}
                        </div>
                        <div class="item-qty">${item.quantity}×</div>
                        <div class="item-price">${formatCurrency(item.unit_price * item.quantity)}</div>
                    </div>
                `).join('');

                return `
                    <div class="history-card">
                        <div class="order-header">
                            <div class="order-id">📦 #${order.id}</div>
                            <div class="table-info">🍽️ ${order.table_code || `Stůl ${order.table_number}`}</div>
                        </div>
                        
                        <div class="order-meta">
                            <div class="meta-item">
                                <span>📅 Datum:</span>
                                <span>${formatDateTime(order.created_at)}</span>
                            </div>
                            <div class="meta-item">
                                <span>👤 Obsluha:</span>
                                <span>${order.employee_name || 'Neznámý'}</span>
                            </div>
                            <div class="meta-item">
                                <span>🏷️ Zákazník:</span>
                                <span>${order.customer_name || '-'}</span>
                            </div>
                            <div class="meta-item">
                                <span>📦 Položky:</span>
                                <span>${order.items.length}</span>
                            </div>
                        </div>

                        <div class="order-total">
                            Celkem: ${formatCurrency(totalAmount)}
                        </div>

                        <button class="items-toggle" onclick="toggleItems(${order.id}, this)">
                            📋 Zobrazit položky (${order.items.length})
                        </button>

                        <div class="items-list" id="items-${order.id}">
                            ${itemsHtml}
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Toggle zobrazení položek
        function toggleItems(orderId, button) {
            const itemsList = document.getElementById(`items-${orderId}`);
            const isVisible = itemsList.classList.contains('show');
            
            if (isVisible) {
                itemsList.classList.remove('show');
                button.classList.remove('expanded');
                button.textContent = `📋 Zobrazit položky (${itemsList.children.length})`;
            } else {
                itemsList.classList.add('show');
                button.classList.add('expanded');
                button.textContent = `📋 Skrýt položky (${itemsList.children.length})`;
            }
        }

        // Načtení filtrů
        async function loadFilters() {
            try {
                // Načtení stolů
                const tablesResponse = await fetch(`${API}?action=tables`);
                const tablesResult = await tablesResponse.json();
                
                if (tablesResult.success) {
                    const tableSelect = document.getElementById('tableFilter');
                    tablesResult.data.tables.forEach(table => {
                        const option = document.createElement('option');
                        option.value = table.table_number;
                        option.textContent = table.table_code || `Stůl ${table.table_number}`;
                        tableSelect.appendChild(option);
                    });
                }

                // Načtení zaměstnanců
                const employeesResponse = await fetch(`${API}?action=employees-list`);
                const employeesResult = await employeesResponse.json();
                
                if (employeesResult.success) {
                    const employeeSelect = document.getElementById('employeeFilter');
                    employeesResult.data.employees.forEach(employee => {
                        const option = document.createElement('option');
                        option.value = employee.name;
                        option.textContent = `${employee.name} (${employee.order_count} obj.)`;
                        employeeSelect.appendChild(option);
                    });
                }
            } catch (error) {
                console.error('Chyba při načítání filtrů:', error);
            }
        }

        function updateLastUpdateTime() {
            document.getElementById('lastUpdate').textContent = new Date().toLocaleTimeString('cs-CZ');
        }

        // Inicializace
        document.addEventListener('DOMContentLoaded', function() {
            initializeDates();
            loadFilters();
            loadHistory();
        });

        // Globální funkce
        window.toggleItems = toggleItems;
        window.loadHistory = loadHistory;
    </script>
</body>
</html>