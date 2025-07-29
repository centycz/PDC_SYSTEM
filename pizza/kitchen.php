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
$user_role = $_SESSION['user_role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kuchyň - Objednávky</title>
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
            color: #2f80ed;
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
            color: #2f80ed;
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
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #36d1c4 0%, #2f80ed 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .header { text-align: center; margin-bottom: 30px; }
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
        .nav-link.active { background: white; color: #2f80ed; }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }
        
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stats-section {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #36d1c4, #2f80ed);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            text-align: center;
            min-width: 120px;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .refresh-btn {
            background: #36d1c4;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .refresh-btn:hover {
            background: #2eb398;
            transform: translateY(-2px);
        }
        
        .orders-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .table-card {
            background: #f8f9fa;
            border-radius: 12px;
            overflow: hidden;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .table-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .table-card.has-pending {
            border-color: #e74c3c;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.2);
        }
        
        .table-card.has-preparing {
            border-color: #f39c12;
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.2);
        }
        
        .table-header {
            background: linear-gradient(135deg, #36d1c4, #2f80ed);
            color: white;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-number {
            font-size: 1.3em;
            font-weight: bold;
        }
        
        .table-time {
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .order-items {
            padding: 15px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            margin-bottom: 8px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .order-item.pending {
            border-left-color: #e74c3c;
            background: #fdf2f2;
        }
        
        .order-item.preparing {
            border-left-color: #f39c12;
            background: #fefaf2;
        }
        
        .order-item.ready {
            border-left-color: #27ae60;
            background: #f2f8f2;
        }
        
        .order-item.burnt {
            border-left-color: #8b4513;
            background: #f5f0e8;
        }
        
        .item-name {
            font-weight: 600;
            color: #333;
            flex: 1;
        }
        
        .item-quantity {
            background: #36d1c4;
            color: white;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.9em;
            font-weight: bold;
            margin-right: 10px;
        }
        
        .item-note {
            font-size: 0.8em;
            color: #666;
            font-style: italic;
            margin-top: 5px;
        }
        
        .item-actions {
            margin-top: 8px;
        }
        
        .btn {
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-ready {
            background: #27ae60;
            color: white;
        }
        
        .btn-ready:hover {
            background: #219a52;
            transform: translateY(-1px);
        }
        
        .table-controls {
            padding: 15px;
            background: #f8f9fa;
            border-top: 1px solid #eee;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }
        
        .loading {
            text-align: center;
            padding: 40px;
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
        
        /* Responzivní design */
        @media (max-width: 768px) {
            body { padding: 15px; }
            .container { padding: 20px; }
            .orders-grid { grid-template-columns: 1fr; }
            .stats-section { justify-content: center; }
            .header h1 { font-size: 2em; }
        }
        
        /* Notifikace */
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
            background: #27ae60;
        }
        
        .notification.error {
            background: #e74c3c;
        }
        
        .notification.info {
            background: #3498db;
        }
    </style>
</head>
<body>
    <!-- Navigation Header -->
    <div class="nav-header">
        <div class="nav-left">
            <a href="/index.php" class="back-btn">← ZPĚT NA HLAVNÍ STRÁNKU</a>
        </div>
        <div class="user-info">
            Přihlášen jako: <strong><?= htmlspecialchars($full_name) ?></strong> (<?= ucfirst($user_role) ?>)
        </div>
    </div>

    <div class="header">
        <h1>👨‍🍳 Kuchyň</h1>
    </div>

    <div class="nav-links">
        <a href="index.php" class="nav-link">🍽️ Obsluha</a>
        <a href="kitchen.php" class="nav-link active">👨‍🍳 Kuchyň</a>
        <a href="bar.php" class="nav-link">🍺 Bar</a>
        <a href="data.php" class="nav-link">📊 Statistiky</a>
    </div>

    <div class="container">
        <div class="stats-header">
            <div class="stats-section">
                <div class="stat-card">
                    <div class="stat-number" id="pendingCount">0</div>
                    <div class="stat-label">Čeká na přípravu</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="preparingCount">0</div>
                    <div class="stat-label">Připravuje se</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="readyCount">0</div>
                    <div class="stat-label">Hotové</div>
                </div>
            </div>
            <button class="refresh-btn" onclick="refreshOrders()">
                🔄 Aktualizovat
            </button>
        </div>

        <div id="ordersContainer">
            <div class="loading">Načítání objednávek</div>
        </div>
    </div>

    <script>
        let ordersData = [];
        
        // API endpoint
        const API_BASE = 'api/restaurant-api.php';

        // Funkce pro zobrazení notifikace
        function showNotification(message, type = 'info') {
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

        // Načtení objednávek
        async function refreshOrders() {
            try {
                console.log('🔄 Loading kitchen orders...');
                const response = await fetch(`${API_BASE}?action=kitchen-items`, {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📦 Kitchen API response:', data);
                
                if (data.success) {
                    ordersData = data.data?.items || [];
                    console.log(`✅ Loaded ${ordersData.length} kitchen items`);
                    renderOrders();
                    updateStats();
                } else {
                    console.error('❌ API Error:', data.error);
                    showNotification(`Chyba při načítání objednávek: ${data.error}`, 'error');
                }
            } catch (error) {
                console.error('❌ Network Error:', error);
                showNotification(`Chyba spojení se serverem: ${error.message}`, 'error');
            }
        }

        // Vykreslení objednávek
        function renderOrders() {
            const container = document.getElementById('ordersContainer');
            
            if (!ordersData || ordersData.length === 0) {
                container.innerHTML = `
                    <div class="empty-state">
                        <div class="icon">🍽️</div>
                        <h3>Žádné objednávky</h3>
                        <p>Momentálně nejsou žádné objednávky k přípravě</p>
                    </div>
                `;
                return;
            }

            // Seskupení podle stolů
            const tableGroups = {};
            ordersData.forEach(item => {
                const tableCode = item.table_code || item.table_number;
                if (!tableGroups[tableCode]) {
                    tableGroups[tableCode] = [];
                }
                tableGroups[tableCode].push(item);
            });

            const tablesHTML = Object.entries(tableGroups).map(([tableCode, items]) => {
                return renderTableCard(tableCode, items);
            }).join('');

            container.innerHTML = `<div class="orders-grid">${tablesHTML}</div>`;
        }

        // Vykreslení karty stolu
        function renderTableCard(tableCode, items) {
            const tableNumber = tableCode;
            
            // Určení stavu stolu
            const hasPending = items.some(item => item.status === 'pending');
            const hasPreparing = items.some(item => item.status === 'preparing');
            
            let cardClass = 'table-card';
            if (hasPending) cardClass += ' has-pending';
            else if (hasPreparing) cardClass += ' has-preparing';

            // Nejstarší objednávka pro čas
            const oldestTime = Math.min(...items.map(item => new Date(item.created_at).getTime()));
            const timeAgo = formatTimeAgo(new Date(oldestTime));

            // Rozdělení do kategorií pro postupné uvolňování
            const waitingPasta = items.filter(item => 
                item.item_type === 'pasta' && item.status === 'pending'
            );
            const waitingDesserts = items.filter(item => 
                item.item_type === 'dezert' && item.status === 'pending'
            );

            // Vykreslení položek
            const itemsHTML = items.map(item => {
                const name = item.item_name || 'Položka';
                const quantity = item.quantity || 1;
                const note = item.note || '';
                
                let itemClass = 'order-item';
                let statusText = '';
                let actionButton = '';
                
                if (item.status === 'pending') {
                    itemClass += ' pending';
                    if (note === 'Spalena') {
                        itemClass += ' burnt';
                    }
                    actionButton = `<button class="btn btn-ready" onclick="markReady('${item.id}', '${name}', this)">Hotovo</button>`;
                } else if (item.status === 'preparing') {
                    itemClass += ' preparing';
                    statusText = '<div style="color: #f39c12; font-weight: bold;">🔥 Připravuje se</div>';
                    actionButton = `<button class="btn btn-ready" onclick="markReady('${item.id}', '${name}', this)">Hotovo</button>`;
                } else if (item.status === 'ready') {
                    itemClass += ' ready';
                    statusText = '<div style="color: #27ae60; font-weight: bold;">✅ Hotová</div>';
                }

                return `
                    <div class="${itemClass}">
                        <div class="item-name">${name}</div>
                        <div class="item-quantity">${quantity}×</div>
                        ${statusText}
                        ${note ? `<div class="item-note">${note}</div>` : ''}
                        ${actionButton ? `<div class="item-actions">${actionButton}</div>` : ''}
                    </div>
                `;
            }).join('');

            // Ovládací tlačítka
            let controlButtons = '';
            if (waitingPasta.length > 0 || waitingDesserts.length > 0) {
                const pastaButton = waitingPasta.length > 0 ? 
                    `<button class="btn" style="background: #f39c12; color: white; flex: 1; padding: 8px;" 
                            onclick="releasePasta('${tableNumber}', this)">
                        🍝 Povolit pastu (${waitingPasta.reduce((sum, item) => sum + parseInt(item.quantity), 0)})
                    </button>` : '';
                
                const dessertButton = waitingDesserts.length > 0 ? 
                    `<button class="btn" style="background: #9b59b6; color: white; flex: 1; padding: 8px;" 
                            onclick="releaseDessert('${tableNumber}', this)">
                        🍰 Povolit dezert (${waitingDesserts.reduce((sum, item) => sum + parseInt(item.quantity), 0)})
                    </button>` : '';
                
                if (pastaButton || dessertButton) {
                    controlButtons = `
                        <div class="table-controls">
                            ${pastaButton}
                            ${dessertButton}
                        </div>
                    `;
                }
            }

            return `
                <div class="${cardClass}">
                    <div class="table-header">
                        <div class="table-number">Stůl ${tableNumber}</div>
                        <div class="table-time">${timeAgo}</div>
                    </div>
                    <div class="order-items">
                        ${itemsHTML}
                    </div>
                    ${controlButtons}
                </div>
            `;
        }

        // Označení jako hotové
        async function markReady(itemId, itemName, button) {
            try {
                button.disabled = true;
                button.textContent = 'Označování...';
                
                console.log(`🍕 Marking item ${itemId} as ready...`);
                const response = await fetch(`${API_BASE}?action=item-status`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        item_id: itemId,
                        status: 'ready'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📦 Mark ready response:', data);
                
                if (data.success) {
                    showNotification(`${itemName} označeno jako hotové!`, 'success');
                    refreshOrders();
                } else {
                    console.error('❌ API Error:', data.error);
                    showNotification(`Chyba: ${data.error}`, 'error');
                    button.disabled = false;
                    button.textContent = 'Hotovo';
                }
            } catch (error) {
                console.error('❌ Network Error:', error);
                showNotification(`Chyba spojení se serverem: ${error.message}`, 'error');
                button.disabled = false;
                button.textContent = 'Hotovo';
            }
        }

        // Uvolnění pasty
        async function releasePasta(tableCode, button) {
            try {
                button.disabled = true;
                button.textContent = 'Uvolňování...';
                
                console.log(`🍝 Releasing pasta for table ${tableCode}...`);
                const response = await fetch(`${API_BASE}?action=release-pasta-items`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        table_number: parseInt(tableCode),
                        release_type: 'pasta'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📦 Release pasta response:', data);
                
                if (data.success) {
                    showNotification('Pasta uvolněna pro přípravu!', 'success');
                    refreshOrders();
                } else {
                    console.error('❌ API Error:', data.error);
                    showNotification(`Chyba: ${data.error}`, 'error');
                    button.disabled = false;
                    button.textContent = button.textContent.replace('Uvolňování...', 'Povolit pastu');
                }
            } catch (error) {
                console.error('❌ Network Error:', error);
                showNotification(`Chyba spojení se serverem: ${error.message}`, 'error');
                button.disabled = false;
                button.textContent = button.textContent.replace('Uvolňování...', 'Povolit pastu');
            }
        }

        // Uvolnění dezertu
        async function releaseDessert(tableCode, button) {
            try {
                button.disabled = true;
                button.textContent = 'Uvolňování...';
                
                console.log(`🍰 Releasing dessert for table ${tableCode}...`);
                const response = await fetch(`${API_BASE}?action=release-pasta-items`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        table_number: parseInt(tableCode),
                        release_type: 'dessert'
                    })
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const data = await response.json();
                console.log('📦 Release dessert response:', data);
                
                if (data.success) {
                    showNotification('Dezert uvolněn pro přípravu!', 'success');
                    refreshOrders();
                } else {
                    console.error('❌ API Error:', data.error);
                    showNotification(`Chyba: ${data.error}`, 'error');
                    button.disabled = false;
                    button.textContent = button.textContent.replace('Uvolňování...', 'Povolit dezert');
                }
            } catch (error) {
                console.error('❌ Network Error:', error);
                showNotification(`Chyba spojení se serverem: ${error.message}`, 'error');
                button.disabled = false;
                button.textContent = button.textContent.replace('Uvolňování...', 'Povolit dezert');
            }
        }

        // Aktualizace statistik
        function updateStats() {
            const pending = ordersData.filter(item => item.status === 'pending').length;
            const preparing = ordersData.filter(item => item.status === 'preparing').length;
            const ready = ordersData.filter(item => item.status === 'ready').length;

            document.getElementById('pendingCount').textContent = pending;
            document.getElementById('preparingCount').textContent = preparing;
            document.getElementById('readyCount').textContent = ready;
        }

        // Formátování času
        function formatTimeAgo(date) {
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / (1000 * 60));
            
            if (diffMins < 1) return 'Právě teď';
            if (diffMins < 60) return `před ${diffMins} min`;
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `před ${diffHours}h ${diffMins % 60}min`;
            
            return date.toLocaleDateString('cs-CZ');
        }

        // Automatické obnovování
        function startAutoRefresh() {
            setInterval(refreshOrders, 10000); // každých 10 sekund
        }

        // Inicializace
        document.addEventListener('DOMContentLoaded', function() {
            refreshOrders();
            startAutoRefresh();
        });
    </script>
</body>
</html>